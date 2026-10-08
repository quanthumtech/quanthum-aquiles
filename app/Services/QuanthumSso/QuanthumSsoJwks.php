<?php

namespace App\Services\QuanthumSso;

use App\Services\QuanthumSso\Exceptions\QuanthumSsoException;
use Firebase\JWT\JWK;
use Firebase\JWT\Key;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * JWKS do provedor por `kid`, com cache. Um `kid` desconhecido dispara UM
 * refresh, limitado por jwks_refresh_min_interval_seconds, para tolerar a
 * rotação de chaves sem virar vetor de DoS. Só chaves RSA/RS256 entram: uma
 * chave `oct` ou de outro algoritmo no JWKS nunca vira chave de verificação.
 */
class QuanthumSsoJwks
{
    public function keyFor(string $kid, string $jwksUri): Key
    {
        $keys = $this->parse($this->load($jwksUri));

        if (! isset($keys[$kid]) && Cache::add($this->cooldownKey($jwksUri), true, $this->minInterval())) {
            $keys = $this->parse($this->fetch($jwksUri));
        }

        return $keys[$kid] ?? throw new QuanthumSsoException(QuanthumSsoFailure::InvalidIdToken, 'unknown signing key');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function load(string $jwksUri): array
    {
        $cached = Cache::get($this->cacheKey($jwksUri));

        return is_array($cached) ? $cached : $this->fetch($jwksUri);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetch(string $jwksUri): array
    {
        $timeout = (int) config('quanthum_sso.http_timeout_seconds');

        try {
            $response = Http::timeout($timeout)
                ->connectTimeout($timeout)
                ->withoutRedirecting()
                ->acceptJson()
                ->get($jwksUri);
        } catch (ConnectionException $e) {
            throw new QuanthumSsoException(QuanthumSsoFailure::JwksUnavailable, 'jwks unreachable', $e);
        }

        $keys = $response->successful() ? $response->json('keys') : null;

        if (! is_array($keys)) {
            throw new QuanthumSsoException(QuanthumSsoFailure::JwksUnavailable, 'jwks unavailable');
        }

        $rsaKeys = array_values(array_filter(
            $keys,
            fn ($jwk) => is_array($jwk)
                && ($jwk['kty'] ?? null) === 'RSA'
                && ($jwk['alg'] ?? 'RS256') === 'RS256'
                && ($jwk['use'] ?? 'sig') === 'sig'
                && is_string($jwk['kid'] ?? null)
                && $jwk['kid'] !== ''
        ));

        Cache::put($this->cacheKey($jwksUri), $rsaKeys, (int) config('quanthum_sso.jwks_ttl_seconds'));
        Cache::put($this->cooldownKey($jwksUri), true, $this->minInterval());

        return $rsaKeys;
    }

    /**
     * @param  list<array<string, mixed>>  $jwks
     * @return array<string, Key>
     */
    private function parse(array $jwks): array
    {
        $keys = [];

        foreach ($jwks as $jwk) {
            try {
                $key = JWK::parseKey($jwk, 'RS256');
            } catch (Throwable) {
                continue;
            }

            if ($key !== null) {
                $keys[$jwk['kid']] = $key;
            }
        }

        return $keys;
    }

    private function minInterval(): int
    {
        return (int) config('quanthum_sso.jwks_refresh_min_interval_seconds');
    }

    private function cacheKey(string $jwksUri): string
    {
        return 'quanthum_sso:jwks:'.hash('sha256', $jwksUri);
    }

    private function cooldownKey(string $jwksUri): string
    {
        return 'quanthum_sso:jwks_cooldown:'.hash('sha256', $jwksUri);
    }
}
