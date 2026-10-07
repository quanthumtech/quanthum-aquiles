<?php

namespace App\Services\QuanthumSso;

use App\Services\QuanthumSso\Exceptions\QuanthumSsoException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Descobre os endpoints a partir do issuer configurado. O documento só é
 * aceito (e cacheado) se o issuer for IGUAL ao configurado e todos os
 * endpoints forem HTTPS.
 */
class QuanthumSsoDiscovery
{
    public function __construct(private readonly QuanthumSsoConfig $config) {}

    /**
     * @return array{issuer: string, authorization_endpoint: string, token_endpoint: string, jwks_uri: string, end_session_endpoint: string|null}
     */
    public function document(): array
    {
        if ($this->config->issuer() === '') {
            throw new QuanthumSsoException(QuanthumSsoFailure::NotConfigured);
        }

        $cacheKey = 'quanthum_sso:discovery:'.hash('sha256', $this->config->issuer());
        $cached = Cache::get($cacheKey);

        if (is_array($cached)) {
            return $cached;
        }

        $document = $this->fetch();

        Cache::put($cacheKey, $document, (int) config('quanthum_sso.discovery_ttl_seconds'));

        return $document;
    }

    /**
     * @return array{issuer: string, authorization_endpoint: string, token_endpoint: string, jwks_uri: string, end_session_endpoint: string|null}
     */
    private function fetch(): array
    {
        $timeout = (int) config('quanthum_sso.http_timeout_seconds');

        try {
            $response = Http::timeout($timeout)
                ->connectTimeout($timeout)
                ->withoutRedirecting()
                ->acceptJson()
                ->get($this->config->issuer().'/.well-known/openid-configuration');
        } catch (ConnectionException $e) {
            throw new QuanthumSsoException(QuanthumSsoFailure::DiscoveryFailed, 'discovery unreachable', $e);
        }

        $json = $response->successful() ? $this->decode($response->body()) : null;

        if ($json === null) {
            throw new QuanthumSsoException(QuanthumSsoFailure::DiscoveryFailed, 'discovery unavailable');
        }

        if (($json['issuer'] ?? null) !== $this->config->issuer()) {
            throw new QuanthumSsoException(QuanthumSsoFailure::DiscoveryFailed, 'discovery issuer mismatch');
        }

        $endpoints = [];

        foreach (['authorization_endpoint', 'token_endpoint', 'jwks_uri'] as $name) {
            $endpoints[$name] = $this->requiredEndpoint($json, $name);
        }

        $endSession = $json['end_session_endpoint'] ?? null;

        if ($endSession !== null && (! is_string($endSession) || ! $this->config->isAllowedUrl($endSession))) {
            throw new QuanthumSsoException(QuanthumSsoFailure::DiscoveryFailed, 'discovery end_session_endpoint not allowed');
        }

        $this->assertAdvertises($json, 'id_token_signing_alg_values_supported', 'RS256');
        $this->assertAdvertises($json, 'code_challenge_methods_supported', 'S256');

        return [
            'issuer' => $this->config->issuer(),
            ...$endpoints,
            'end_session_endpoint' => $endSession,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decode(string $body): ?array
    {
        try {
            $decoded = json_decode($body, true, 16, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param  array<string, mixed>  $json
     */
    private function requiredEndpoint(array $json, string $name): string
    {
        $value = $json[$name] ?? null;

        if (! is_string($value) || ! $this->config->isAllowedUrl($value)) {
            throw new QuanthumSsoException(QuanthumSsoFailure::DiscoveryFailed, "discovery {$name} missing or not allowed");
        }

        return $value;
    }

    /**
     * Quando o provedor anuncia a lista, ela precisa conter o valor exigido.
     *
     * @param  array<string, mixed>  $json
     */
    private function assertAdvertises(array $json, string $key, string $required): void
    {
        if (array_key_exists($key, $json) && (! is_array($json[$key]) || ! in_array($required, $json[$key], true))) {
            throw new QuanthumSsoException(QuanthumSsoFailure::DiscoveryFailed, "discovery does not support {$required}");
        }
    }
}
