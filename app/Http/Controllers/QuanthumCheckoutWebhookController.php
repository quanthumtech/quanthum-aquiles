<?php

namespace App\Http\Controllers;

use App\Events\QuanthumCheckoutEventReceived;
use App\Services\QuanthumCheckout\WebhookSignature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class QuanthumCheckoutWebhookController extends Controller
{
    /**
     * Só aceita o evento com HMAC válido (corpo cru + timestamp). Sem segredo
     * configurado a rota não existe (404). Não loga corpo nem cabeçalhos.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $secrets = array_values(array_filter([
            (string) config('quanthum_checkout.webhook_secret'),
            (string) config('quanthum_checkout.webhook_secret_previous'),
        ], fn (string $secret) => $secret !== ''));

        abort_if($secrets === [], 404);

        $valid = WebhookSignature::isValid(
            $request->getContent(),
            $request->header('X-Checkout-Timestamp'),
            $request->header('X-Checkout-Signature'),
            $secrets,
            toleranceSeconds: (int) config('quanthum_checkout.webhook_tolerance_seconds'),
        );

        if (! $valid) {
            Log::warning('Quanthum Checkout webhook refused', ['reason' => 'invalid_signature']);

            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        $payload = json_decode($request->getContent(), true);

        if (! is_array($payload) || ! is_string($payload['id'] ?? null) || ! is_string($payload['type'] ?? null)) {
            return response()->json(['message' => 'Invalid event.'], 422);
        }

        QuanthumCheckoutEventReceived::dispatch($payload['id'], $payload['type'], $payload);

        return response()->json(['received' => true]);
    }
}
