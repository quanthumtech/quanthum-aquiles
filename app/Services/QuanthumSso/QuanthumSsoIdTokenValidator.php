<?php

namespace App\Services\QuanthumSso;

use App\Services\QuanthumSso\Exceptions\QuanthumSsoException;
use Firebase\JWT\JWT;
use Throwable;

/**
 * Valida o id_token do SSO: só RS256 (rejeita none, HS256 e demais ANTES de tocar
 * numa chave), assinatura por JWKS/kid, e iss, aud, azp, exp, iat e nonce.
 */
class QuanthumSsoIdTokenValidator
{
    public function __construct(
        private readonly QuanthumSsoConfig $config,
        private readonly QuanthumSsoJwks $jwks,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function validate(string $idToken, string $expectedNonce, string $jwksUri): array
    {
        $kid = $this->kidOfAllowedAlgorithm($idToken);

        $key = $this->jwks->keyFor($kid, $jwksUri);

        $previousLeeway = JWT::$leeway;
        JWT::$leeway = (int) config('quanthum_sso.clock_leeway_seconds');

        try {
            $claims = (array) JWT::decode($idToken, $key);
        } catch (Throwable $e) {
            throw new QuanthumSsoException(QuanthumSsoFailure::InvalidIdToken, 'id_token rejected: '.class_basename($e), $e);
        } finally {
            JWT::$leeway = $previousLeeway;
        }

        $this->assertClaims($claims, $expectedNonce);

        return $claims;
    }

    private function kidOfAllowedAlgorithm(string $idToken): string
    {
        $segments = explode('.', $idToken);

        if (count($segments) !== 3 || in_array('', $segments, true)) {
            throw new QuanthumSsoException(QuanthumSsoFailure::InvalidIdToken, 'malformed id_token');
        }

        try {
            $header = JWT::jsonDecode(JWT::urlsafeB64Decode($segments[0]));
        } catch (Throwable $e) {
            throw new QuanthumSsoException(QuanthumSsoFailure::InvalidIdToken, 'malformed id_token header', $e);
        }

        $header = (array) $header;
        $alg = $header['alg'] ?? null;
        $kid = $header['kid'] ?? null;

        if (! is_string($alg) || ! in_array($alg, (array) config('quanthum_sso.allowed_algorithms'), true)) {
            throw new QuanthumSsoException(QuanthumSsoFailure::InvalidIdToken, 'id_token algorithm not allowed');
        }

        if (! is_string($kid) || $kid === '') {
            throw new QuanthumSsoException(QuanthumSsoFailure::InvalidIdToken, 'id_token without kid');
        }

        return $kid;
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    private function assertClaims(array $claims, string $expectedNonce): void
    {
        $clientId = $this->config->clientId();

        $this->require(($claims['iss'] ?? null) === $this->config->issuer(), 'iss');
        $this->require(is_string($claims['sub'] ?? null) && $claims['sub'] !== '' && strlen($claims['sub']) <= 255, 'sub');
        $this->require(is_int($claims['exp'] ?? null) || is_float($claims['exp'] ?? null), 'exp');
        $this->require(is_int($claims['iat'] ?? null) || is_float($claims['iat'] ?? null), 'iat');
        $this->require($claims['iat'] <= (JWT::$timestamp ?? time()) + (int) config('quanthum_sso.clock_leeway_seconds'), 'iat');

        $audience = $claims['aud'] ?? null;
        $audience = is_string($audience) ? [$audience] : $audience;

        $this->require(is_array($audience) && in_array($clientId, $audience, true), 'aud');

        if (array_key_exists('azp', $claims) || count($audience) > 1) {
            $this->require(($claims['azp'] ?? null) === $clientId, 'azp');
        }

        $nonce = $claims['nonce'] ?? null;

        $this->require(is_string($nonce) && $expectedNonce !== '' && hash_equals($expectedNonce, $nonce), 'nonce');
    }

    private function require(bool $condition, string $claim): void
    {
        if (! $condition) {
            throw new QuanthumSsoException(QuanthumSsoFailure::InvalidIdToken, "id_token claim invalid: {$claim}");
        }
    }
}
