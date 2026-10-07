<?php

namespace App\Services\QuanthumSso;

use App\Services\QuanthumSso\Exceptions\QuanthumSsoException;
use Firebase\JWT\JWT;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Cliente OIDC web confidencial: Authorization Code + PKCE S256, state e nonce
 * de uso único na sessão, troca do code no backend, validação do id_token.
 * Access e refresh token nunca são guardados nem usados; só o id_token fica na
 * sessão, cifrado, para o id_token_hint do logout.
 */
class QuanthumSsoClient
{
    public const FLOW_SESSION_KEY = 'quanthum_sso.flow';

    public const ID_TOKEN_SESSION_KEY = 'quanthum_sso.id_token';

    private const SAFE_PROVIDER_ERROR = '/^[a-z_]{1,40}$/';

    public function __construct(
        private readonly QuanthumSsoConfig $config,
        private readonly QuanthumSsoDiscovery $discovery,
        private readonly QuanthumSsoIdTokenValidator $validator,
    ) {}

    public function authorizationUrl(Request $request): string
    {
        $this->assertAvailable();

        $endpoint = $this->discovery->document()['authorization_endpoint'];

        $state = $this->randomToken(32);
        $nonce = $this->randomToken(32);
        $codeVerifier = $this->randomToken(64);

        $request->session()->put(self::FLOW_SESSION_KEY, [
            'state' => $state,
            'nonce' => $nonce,
            'code_verifier' => $codeVerifier,
            'created_at' => time(),
        ]);

        $query = http_build_query([
            'response_type' => 'code',
            'client_id' => $this->config->clientId(),
            'redirect_uri' => $this->config->redirectUri(),
            'scope' => $this->config->scopes(),
            'state' => $state,
            'nonce' => $nonce,
            'code_challenge' => $this->codeChallenge($codeVerifier),
            'code_challenge_method' => 'S256',
        ], '', '&', PHP_QUERY_RFC3986);

        return $endpoint.(str_contains($endpoint, '?') ? '&' : '?').$query;
    }

    public function completeAuthorization(Request $request): QuanthumSsoAssertion
    {
        $this->assertAvailable();

        $flow = $this->consumeFlow($request);

        $state = $request->query('state');

        if (! is_string($state) || ! hash_equals($flow['state'], $state)) {
            throw new QuanthumSsoException(QuanthumSsoFailure::InvalidState);
        }

        $providerError = $request->query('error');

        if ($providerError !== null) {
            throw new QuanthumSsoException($this->failureForProviderError($providerError));
        }

        $code = $request->query('code');

        if (! is_string($code) || $code === '' || strlen($code) > 2048) {
            throw new QuanthumSsoException(QuanthumSsoFailure::MissingCode);
        }

        $document = $this->discovery->document();
        $idToken = $this->exchangeCode($document['token_endpoint'], $code, $flow['code_verifier']);
        $claims = $this->validator->validate($idToken, $flow['nonce'], $document['jwks_uri']);

        return new QuanthumSsoAssertion(
            issuer: $this->config->issuer(),
            sub: $claims['sub'],
            email: $this->normalizedEmail($claims['email'] ?? null),
            emailVerified: ($claims['email_verified'] ?? null) === true,
            name: is_string($claims['name'] ?? null) ? mb_substr(trim($claims['name']), 0, 255) : null,
            idToken: $idToken,
        );
    }

    public function rememberIdToken(Request $request, string $idToken): void
    {
        $request->session()->put(self::ID_TOKEN_SESSION_KEY, Crypt::encryptString($idToken));
    }

    public function idToken(Request $request): ?string
    {
        $encrypted = $request->session()->get(self::ID_TOKEN_SESSION_KEY);

        if (! is_string($encrypted)) {
            return null;
        }

        try {
            return Crypt::decryptString($encrypted);
        } catch (DecryptException) {
            return null;
        }
    }

    /**
     * O provedor rejeita id_token_hint expirado (HTTP 400), então só vale a
     * pena mandar o navegador para o end_session_endpoint com um id_token que
     * ainda viva além da margem. O token vem da sessão cifrada, validado no
     * login: aqui só se lê o exp.
     */
    public function idTokenIsUsableForLogout(string $idToken): bool
    {
        $segments = explode('.', $idToken);

        if (count($segments) !== 3) {
            return false;
        }

        try {
            $claims = (array) JWT::jsonDecode(JWT::urlsafeB64Decode($segments[1]));
        } catch (Throwable) {
            return false;
        }

        $exp = $claims['exp'] ?? null;

        return (is_int($exp) || is_float($exp))
            && $exp - now()->getTimestamp() > (int) config('quanthum_sso.logout_expiry_margin_seconds');
    }

    public function forgetIdToken(Request $request): void
    {
        $request->session()->forget(self::ID_TOKEN_SESSION_KEY);
    }

    /**
     * URL do end_session_endpoint com id_token_hint e a post-logout URI EXATA
     * da configuração (nunca do request). Sem endpoint anunciado, null.
     */
    public function endSessionUrl(string $idToken): ?string
    {
        $this->assertAvailable();

        $endpoint = $this->discovery->document()['end_session_endpoint'];

        if ($endpoint === null) {
            return null;
        }

        $query = http_build_query([
            'id_token_hint' => $idToken,
            'post_logout_redirect_uri' => $this->config->postLogoutRedirectUri(),
            'client_id' => $this->config->clientId(),
        ], '', '&', PHP_QUERY_RFC3986);

        return $endpoint.(str_contains($endpoint, '?') ? '&' : '?').$query;
    }

    private function assertAvailable(): void
    {
        if (! $this->config->isAvailable()) {
            throw new QuanthumSsoException(QuanthumSsoFailure::NotConfigured);
        }
    }

    /**
     * state, nonce e code_verifier valem uma vez: saem da sessão ao serem
     * lidos, mesmo que a validação seguinte falhe.
     *
     * @return array{state: string, nonce: string, code_verifier: string}
     */
    private function consumeFlow(Request $request): array
    {
        $flow = $request->session()->pull(self::FLOW_SESSION_KEY);

        if (! is_array($flow) || ! isset($flow['state'], $flow['nonce'], $flow['code_verifier'], $flow['created_at'])) {
            throw new QuanthumSsoException(QuanthumSsoFailure::InvalidState, 'no flow in session');
        }

        if (time() - (int) $flow['created_at'] > (int) config('quanthum_sso.flow_ttl_seconds')) {
            throw new QuanthumSsoException(QuanthumSsoFailure::ExpiredFlow);
        }

        return [
            'state' => (string) $flow['state'],
            'nonce' => (string) $flow['nonce'],
            'code_verifier' => (string) $flow['code_verifier'],
        ];
    }

    private function exchangeCode(string $tokenEndpoint, string $code, string $codeVerifier): string
    {
        $timeout = (int) config('quanthum_sso.http_timeout_seconds');

        try {
            $response = Http::asForm()
                ->timeout($timeout)
                ->connectTimeout($timeout)
                ->withoutRedirecting()
                ->acceptJson()
                ->withBasicAuth(rawurlencode($this->config->clientId()), rawurlencode($this->config->clientSecret()))
                ->post($tokenEndpoint, [
                    'grant_type' => 'authorization_code',
                    'code' => $code,
                    'redirect_uri' => $this->config->redirectUri(),
                    'code_verifier' => $codeVerifier,
                ]);
        } catch (ConnectionException $e) {
            throw new QuanthumSsoException(QuanthumSsoFailure::ProviderUnavailable, 'token endpoint unreachable', $e);
        }

        if ($response->serverError()) {
            throw new QuanthumSsoException(QuanthumSsoFailure::ProviderUnavailable, 'token endpoint server error');
        }

        if (! $response->successful()) {
            $error = $response->json('error');

            throw new QuanthumSsoException(
                $error === 'invalid_grant' ? QuanthumSsoFailure::InvalidGrant : QuanthumSsoFailure::TokenExchangeFailed,
            );
        }

        $idToken = $response->json('id_token');

        if (! is_string($idToken) || $idToken === '') {
            throw new QuanthumSsoException(QuanthumSsoFailure::TokenExchangeFailed, 'token response without id_token');
        }

        return $idToken;
    }

    private function failureForProviderError(mixed $error): QuanthumSsoFailure
    {
        if (! is_string($error) || preg_match(self::SAFE_PROVIDER_ERROR, $error) !== 1) {
            return QuanthumSsoFailure::ProviderError;
        }

        return match ($error) {
            'access_denied' => QuanthumSsoFailure::ProviderDenied,
            'login_required', 'interaction_required', 'consent_required', 'account_selection_required' => QuanthumSsoFailure::ProviderLoginRequired,
            'temporarily_unavailable', 'server_error' => QuanthumSsoFailure::ProviderUnavailable,
            'invalid_grant' => QuanthumSsoFailure::InvalidGrant,
            default => QuanthumSsoFailure::ProviderError,
        };
    }

    private function normalizedEmail(mixed $email): ?string
    {
        if (! is_string($email)) {
            return null;
        }

        $normalized = mb_strtolower(trim($email));

        return filter_var($normalized, FILTER_VALIDATE_EMAIL) !== false ? $normalized : null;
    }

    private function randomToken(int $bytes): string
    {
        return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');
    }

    private function codeChallenge(string $codeVerifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');
    }
}
