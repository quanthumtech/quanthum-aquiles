<?php

namespace App\Services\QuanthumCheckout;

/**
 * Confere o webhook de saída do Quanthum Checkout: X-Checkout-Signature =
 * `sha256=<hex>` = HMAC-SHA256(secret, "{timestamp}.{corpo cru}"). Na rotação
 * o cabeçalho traz duas assinaturas separadas por vírgula: vale se QUALQUER
 * uma bater com QUALQUER segredo conhecido. Comparação em tempo constante e
 * janela de timestamp (anti-replay).
 */
class WebhookSignature
{
    /**
     * @param  list<string>  $secrets
     */
    public static function isValid(string $rawBody, ?string $timestamp, ?string $signatureHeader, array $secrets, ?int $now = null, int $toleranceSeconds = 300): bool
    {
        $secrets = array_values(array_filter($secrets, fn ($secret) => $secret !== ''));

        if ($secrets === [] || $signatureHeader === null || $signatureHeader === '') {
            return false;
        }

        if ($timestamp === null || ! ctype_digit($timestamp) || strlen($timestamp) > 12) {
            return false;
        }

        if (abs(($now ?? time()) - (int) $timestamp) > $toleranceSeconds) {
            return false;
        }

        $match = false;

        foreach (explode(',', $signatureHeader) as $provided) {
            foreach ($secrets as $secret) {
                $expected = 'sha256='.hash_hmac('sha256', "{$timestamp}.{$rawBody}", $secret);
                $match = hash_equals($expected, trim($provided)) || $match;
            }
        }

        return $match;
    }

    public static function sign(string $rawBody, string $timestamp, string $secret): string
    {
        return 'sha256='.hash_hmac('sha256', "{$timestamp}.{$rawBody}", $secret);
    }
}
