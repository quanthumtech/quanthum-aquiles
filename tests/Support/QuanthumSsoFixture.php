<?php

namespace Tests\Support;

use App\Services\QuanthumSso\Exceptions\QuanthumSsoException;
use App\Services\QuanthumSso\QuanthumSsoClient;
use App\Services\QuanthumSso\QuanthumSsoFailure;
use Closure;
use Firebase\JWT\JWT;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Provedor OIDC simulado: par RSA real, JWKS, discovery e token endpoint via
 * Http::fake. Nada aqui fala com o SSO de verdade.
 */
final class QuanthumSsoFixture
{
    public const ISSUER = 'https://sso.example.test';

    public const CLIENT_ID = 'app-client';

    public const CLIENT_SECRET = 'test-client-secret';

    public const REDIRECT_URI = 'https://app.example.test/quanthum-sso/callback';

    public const POST_LOGOUT_URI = 'https://app.example.test/login';

    /** @var array<string, array{private: string, jwk: array<string, string>}> */
    private static array $keys = [];

    private static bool $providerFaked = false;

    /** @var array{nonce: string|null, claims: array<string, mixed>, options: array<string, mixed>, overrides: array<string, mixed>} */
    private static array $login = ['nonce' => null, 'claims' => [], 'options' => [], 'overrides' => []];

    /**
     * @param  array<string, mixed>  $overrides
     */
    public static function configure(array $overrides = []): void
    {
        self::$providerFaked = false;
        self::$login = ['nonce' => null, 'claims' => [], 'options' => [], 'overrides' => []];

        config([
            'quanthum_sso.enabled' => true,
            'quanthum_sso.auto_provision' => false,
            'quanthum_sso.issuer' => self::ISSUER,
            'quanthum_sso.client_id' => self::CLIENT_ID,
            'quanthum_sso.client_secret' => self::CLIENT_SECRET,
            'quanthum_sso.redirect_uri' => self::REDIRECT_URI,
            'quanthum_sso.post_logout_redirect_uri' => self::POST_LOGOUT_URI,
            'quanthum_sso.allow_insecure_http' => false,
            ...collect($overrides)->mapWithKeys(fn ($value, $key) => ["quanthum_sso.{$key}" => $value])->all(),
        ]);
    }

    /**
     * @return array{private: string, jwk: array<string, string>}
     */
    public static function key(string $kid = 'kid-1'): array
    {
        if (isset(self::$keys[$kid])) {
            return self::$keys[$kid];
        }

        $resource = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($resource, $pem);
        $details = openssl_pkey_get_details($resource);

        return self::$keys[$kid] = [
            'private' => $pem,
            'jwk' => [
                'kty' => 'RSA',
                'use' => 'sig',
                'alg' => 'RS256',
                'kid' => $kid,
                'n' => JWT::urlsafeB64Encode($details['rsa']['n']),
                'e' => JWT::urlsafeB64Encode($details['rsa']['e']),
            ],
        ];
    }

    /**
     * @param  list<string>  $kids
     * @return array{keys: list<array<string, string>>}
     */
    public static function jwks(array $kids = ['kid-1']): array
    {
        return ['keys' => array_map(fn (string $kid) => self::key($kid)['jwk'], $kids)];
    }

    /**
     * @param  array<string, mixed>  $overrides  valor null remove a chave
     * @return array<string, mixed>
     */
    public static function discovery(array $overrides = []): array
    {
        return self::withOverrides([
            'issuer' => self::ISSUER,
            'authorization_endpoint' => self::ISSUER.'/oauth/authorize',
            'token_endpoint' => self::ISSUER.'/oauth/token',
            'jwks_uri' => self::ISSUER.'/oauth/jwks',
            'end_session_endpoint' => self::ISSUER.'/oauth/logout',
            'response_types_supported' => ['code'],
            'id_token_signing_alg_values_supported' => ['RS256'],
            'code_challenge_methods_supported' => ['S256'],
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $claims  valor null remove o claim
     * @param  array<string, mixed>  $options  kid (string|null), alg, signing_key
     */
    public static function idToken(array $claims = [], array $options = []): string
    {
        $now = time();

        $payload = self::withOverrides([
            'iss' => self::ISSUER,
            'aud' => self::CLIENT_ID,
            'sub' => 'sub-123',
            'iat' => $now,
            'exp' => $now + 300,
            'nonce' => 'nonce-value',
            'email' => 'ana@example.test',
            'email_verified' => true,
            'name' => 'Ana Teste',
        ], $claims);

        $kid = array_key_exists('kid', $options) ? $options['kid'] : 'kid-1';
        $signingKey = $options['signing_key'] ?? self::key($kid ?? 'kid-1')['private'];

        return JWT::encode($payload, $signingKey, $options['alg'] ?? 'RS256', $kid);
    }

    /**
     * Resposta do token endpoint com um id_token assinado.
     *
     * @param  array<string, mixed>  $claims
     * @param  array<string, mixed>  $options
     */
    public static function tokenResponse(array $claims = [], array $options = []): mixed
    {
        return Http::response([
            'token_type' => 'Bearer',
            'expires_in' => 900,
            'access_token' => 'access-token-that-must-not-be-kept',
            'refresh_token' => 'refresh-token-that-must-not-be-kept',
            'id_token' => self::idToken($claims, $options),
        ]);
    }

    /**
     * Registra os stubs do provedor de uma vez (o primeiro stub que casa
     * vence). Cada valor pode ser array, resposta Http ou Closure.
     *
     * @param  array{discovery?: mixed, jwks?: mixed, token?: mixed}  $overrides
     */
    public static function fake(array $overrides = []): void
    {
        $wrap = fn (mixed $value) => is_array($value) ? Http::response($value) : (is_string($value) ? self::stub($value) : $value);

        Http::fake([
            self::ISSUER.'/.well-known/openid-configuration' => $wrap($overrides['discovery'] ?? self::discovery()),
            self::ISSUER.'/oauth/jwks' => $wrap($overrides['jwks'] ?? self::jwks()),
            self::ISSUER.'/oauth/token' => $wrap($overrides['token'] ?? Http::response(['error' => 'invalid_request'], 400)),
        ]);
    }

    public static function request(array $query = []): Request
    {
        $request = Request::create(self::REDIRECT_URI, 'GET', $query);
        $request->setLaravelSession(app('session.store'));

        return $request;
    }

    /**
     * Inicia o fluxo como o navegador faria e devolve o que foi para o
     * provedor (query da URL de autorização) e o que ficou na sessão.
     *
     * @return array{query: array<string, string>, flow: array<string, mixed>}
     */
    public static function startFlow(): array
    {
        $url = app(QuanthumSsoClient::class)->authorizationUrl(self::request());

        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        return ['query' => $query, 'flow' => session()->get(QuanthumSsoClient::FLOW_SESSION_KEY)];
    }

    /**
     * Inicia o fluxo e prepara o provedor para devolver um id_token com o
     * nonce REAL da sessão (a menos que $claims o sobrescreva).
     *
     * @param  array<string, mixed>  $claims
     * @param  array<string, mixed>  $options
     * @param  array<string, mixed>  $fakeOverrides
     * @return array{query: array{code: string, state: string}, challenge: string, nonce: string}
     */
    public static function prepareLogin(array $claims = [], array $options = [], array $fakeOverrides = []): array
    {
        if (! self::$providerFaked) {
            self::fake([
                'discovery' => fn () => self::resolve('discovery', fn () => self::discovery()),
                'jwks' => fn () => self::resolve('jwks', fn () => self::jwks()),
                'token' => fn () => self::resolve('token', fn () => self::tokenResponse(
                    ['nonce' => self::$login['nonce'], ...self::$login['claims']],
                    self::$login['options'],
                )),
            ]);
            self::$providerFaked = true;
        }

        self::$login = ['nonce' => null, 'claims' => $claims, 'options' => $options, 'overrides' => $fakeOverrides];

        $started = self::startFlow();
        self::$login['nonce'] = $started['flow']['nonce'];

        return [
            'query' => ['code' => 'auth-code-123', 'state' => $started['query']['state']],
            'challenge' => $started['query']['code_challenge'],
            'nonce' => $started['flow']['nonce'],
        ];
    }

    /**
     * Resposta atual de um endpoint do provedor: a sobrescrita do teste
     * (array, nome de stub, resposta ou Closure) ou o padrão.
     */
    private static function resolve(string $endpoint, Closure $default): mixed
    {
        $override = self::$login['overrides'][$endpoint] ?? null;

        return match (true) {
            $override === null => is_array($value = $default()) ? Http::response($value) : $value,
            is_string($override) => self::stub($override),
            is_array($override) => Http::response($override),
            $override instanceof Closure => $override(),
            default => $override,
        };
    }

    /**
     * Respostas de falha nomeadas (datasets do Pest invocam closures, então os
     * testes passam só o nome).
     */
    public static function stub(string $name): mixed
    {
        return match ($name) {
            'invalid_grant' => Http::response(['error' => 'invalid_grant'], 400),
            'invalid_client' => Http::response(['error' => 'invalid_client'], 401),
            'server_error' => Http::response('boom', 503),
            'http_500' => Http::response('', 500),
            'unreachable' => fn () => throw new ConnectionException('timeout'),
            'no_id_token' => Http::response(['access_token' => 'a'], 200),
            'id_token_not_a_string' => Http::response(['id_token' => ['x']], 200),
            'not_json' => Http::response('<html>', 200),
            'without_keys' => Http::response(['nope' => []], 200),
            'redirect' => Http::response('', 302, ['Location' => 'https://evil.example.test/steal']),
        };
    }

    public static function publicPem(string $kid = 'kid-1'): string
    {
        $details = openssl_pkey_get_details(openssl_pkey_get_private(self::key($kid)['private']));

        return $details['key'];
    }

    public static function failureOf(Closure $callback): ?QuanthumSsoFailure
    {
        try {
            $callback();
        } catch (QuanthumSsoException $e) {
            return $e->failure;
        }

        return null;
    }

    /**
     * @param  Closure(HttpRequest): bool  $matcher
     */
    public static function sentCount(Closure $matcher): int
    {
        return Http::recorded()->filter(fn (array $pair) => $matcher($pair[0]))->count();
    }

    /**
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private static function withOverrides(array $base, array $overrides): array
    {
        foreach ($overrides as $key => $value) {
            if ($value === null) {
                unset($base[$key]);
            } else {
                $base[$key] = $value;
            }
        }

        return $base;
    }
}
