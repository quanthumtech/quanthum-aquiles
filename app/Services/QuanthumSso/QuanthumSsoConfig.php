<?php

namespace App\Services\QuanthumSso;

/**
 * Leitura tipada de config/quanthum_sso.php. O botão e as rotas do SSO só
 * existem quando isAvailable(): flag ligada E configuração completa.
 */
class QuanthumSsoConfig
{
    public function enabled(): bool
    {
        return (bool) config('quanthum_sso.enabled');
    }

    public function autoProvision(): bool
    {
        return (bool) config('quanthum_sso.auto_provision');
    }

    public function issuer(): string
    {
        return rtrim($this->string('issuer'), '/');
    }

    public function clientId(): string
    {
        return $this->string('client_id');
    }

    public function clientSecret(): string
    {
        return $this->string('client_secret');
    }

    public function redirectUri(): string
    {
        return $this->string('redirect_uri');
    }

    public function postLogoutRedirectUri(): string
    {
        return $this->string('post_logout_redirect_uri');
    }

    public function scopes(): string
    {
        return $this->string('scopes');
    }

    public function allowInsecureHttp(): bool
    {
        return (bool) config('quanthum_sso.allow_insecure_http');
    }

    public function isConfigured(): bool
    {
        foreach ([$this->issuer(), $this->redirectUri(), $this->postLogoutRedirectUri()] as $url) {
            if (! $this->isAllowedUrl($url)) {
                return false;
            }
        }

        foreach ([$this->redirectUri(), $this->postLogoutRedirectUri()] as $exactUri) {
            if (str_contains($exactUri, '*')) {
                return false;
            }
        }

        return $this->clientId() !== '' && $this->clientSecret() !== '';
    }

    public function isAvailable(): bool
    {
        return $this->enabled() && $this->isConfigured();
    }

    /**
     * HTTPS obrigatório (HTTP só com allow_insecure_http, usado por fixtures),
     * sem userinfo e sem fragmento.
     */
    public function isAllowedUrl(string $url): bool
    {
        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            return false;
        }

        if (isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])) {
            return false;
        }

        $scheme = strtolower($parts['scheme']);

        return $scheme === 'https' || ($scheme === 'http' && $this->allowInsecureHttp());
    }

    private function string(string $key): string
    {
        $value = config("quanthum_sso.{$key}");

        return is_string($value) ? trim($value) : '';
    }
}
