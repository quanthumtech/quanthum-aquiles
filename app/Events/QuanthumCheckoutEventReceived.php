<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Evento de saída do Quanthum Checkout já autenticado (HMAC válido). O app
 * decide o que fazer: ouça este evento, deduplique por `eventId` (entrega
 * at-least-once, sem ordem garantida) e processe em fila.
 */
final class QuanthumCheckoutEventReceived
{
    use Dispatchable;

    /**
     * @param  array<string, mixed>  $payload  corpo completo do evento (id, type, created_at, data)
     */
    public function __construct(
        public readonly string $eventId,
        public readonly string $type,
        public readonly array $payload,
    ) {}
}
