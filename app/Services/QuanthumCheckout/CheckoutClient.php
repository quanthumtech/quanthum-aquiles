<?php

namespace App\Services\QuanthumCheckout;

use App\Services\QuanthumCheckout\Exceptions\CheckoutException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Cliente fino do servidor Quanthum Checkout (Client API). Sem tela e sem
 * provedor de pagamento: quem cobra é o servidor. Só voltar ao success_url NÃO
 * prova pagamento: confie no evento `checkout.paid` (webhook assinado) ou em
 * find().
 */
class CheckoutClient
{
    /**
     * Cria a sessão de checkout. `$idempotencyKey` (até 255 caracteres) é
     * obrigatório: a mesma chave com o mesmo corpo devolve a mesma sessão.
     *
     * @param  array<string, mixed>  $payload  provider, amount_cents (inteiro, centavos), currency, mode, ...
     * @return array<string, mixed> `data` da resposta (id, status, checkout_url, ...)
     */
    public function create(array $payload, string $idempotencyKey): array
    {
        if ($idempotencyKey === '' || strlen($idempotencyKey) > 255) {
            throw new CheckoutException('Idempotency-Key obrigatória (1 a 255 caracteres).', 'IDEMPOTENCY_KEY_REQUIRED');
        }

        if (! isset($payload['amount_cents']) || ! is_int($payload['amount_cents']) || $payload['amount_cents'] < 1) {
            throw new CheckoutException('amount_cents deve ser um inteiro em centavos (>= 1).', 'VALIDATION_FAILED');
        }

        return $this->data($this->send(fn (PendingRequest $http) => $http
            ->withHeaders(['Idempotency-Key' => $idempotencyKey])
            ->post($this->url('checkouts'), $payload)));
    }

    /**
     * @return array<string, mixed>
     */
    public function find(string $checkoutId): array
    {
        return $this->data($this->send(fn (PendingRequest $http) => $http->get($this->url('checkouts/'.rawurlencode($checkoutId)))));
    }

    /**
     * @return array<string, mixed>
     */
    public function invoice(string $checkoutId): array
    {
        return $this->data($this->send(fn (PendingRequest $http) => $http->get($this->url('checkouts/'.rawurlencode($checkoutId).'/invoice'))));
    }

    /**
     * @param  callable(PendingRequest): Response  $call
     */
    private function send(callable $call): Response
    {
        $this->assertConfigured();

        $timeout = (int) config('quanthum_checkout.http_timeout_seconds');

        $http = Http::timeout($timeout)
            ->connectTimeout($timeout)
            ->withoutRedirecting()
            ->acceptJson()
            ->asJson()
            ->withToken((string) config('quanthum_checkout.client_token'));

        try {
            return $call($http);
        } catch (ConnectionException $e) {
            throw new CheckoutException('Servidor de checkout indisponível.', 'SERVER_UNREACHABLE', null, $e);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function data(Response $response): array
    {
        if (! $response->successful()) {
            throw new CheckoutException(
                $this->safeMessage($response->json('error.message')) ?? 'Falha na chamada ao servidor de checkout.',
                $this->safeErrorCode($response->json('error.code')),
                $response->status(),
            );
        }

        $data = $response->json('data');

        if ($response->json('success') !== true || ! is_array($data)) {
            throw new CheckoutException('Resposta fora do envelope esperado.', 'INVALID_RESPONSE', $response->status());
        }

        return $data;
    }

    private function assertConfigured(): void
    {
        $token = config('quanthum_checkout.client_token');

        if (! is_string($token) || trim($token) === '' || ! $this->baseUrlIsSecure()) {
            throw new CheckoutException('Configuração ausente ou insegura: CHECKOUT_BASE_URL (HTTPS) e CHECKOUT_CLIENT_TOKEN.', 'NOT_CONFIGURED');
        }
    }

    private function baseUrlIsSecure(): bool
    {
        $url = config('quanthum_checkout.base_url');
        $parts = is_string($url) ? parse_url(trim($url)) : false;

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            return false;
        }

        if (isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])) {
            return false;
        }

        $scheme = strtolower($parts['scheme']);

        return $scheme === 'https' || ($scheme === 'http' && (bool) config('quanthum_checkout.allow_insecure_http'));
    }

    private function url(string $path): string
    {
        return rtrim(trim((string) config('quanthum_checkout.base_url')), '/')."/api/v1/client/{$path}";
    }

    private function safeErrorCode(mixed $code): ?string
    {
        return is_string($code) && preg_match('/^[A-Z][A-Z0-9_]{1,63}$/D', $code) === 1 ? $code : null;
    }

    /**
     * Mensagem do servidor vai para log/tela: sem controles/ANSI, no máximo
     * 200 caracteres, e sem eco do token.
     */
    private function safeMessage(mixed $message): ?string
    {
        if (! is_string($message)) {
            return null;
        }

        $token = (string) config('quanthum_checkout.client_token');

        if ($token !== '') {
            $message = str_replace($token, '[redacted]', $message);
        }

        $clean = preg_replace('/[^\p{L}\p{N}\p{P}\p{Zs}]+/u', '', $message) ?? '';
        $clean = trim(preg_replace('/\s+/u', ' ', $clean) ?? '');

        return $clean === '' ? null : mb_substr($clean, 0, 200);
    }
}
