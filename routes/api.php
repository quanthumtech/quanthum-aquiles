<?php

use App\Http\Controllers\QuanthumCheckoutWebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Webhook de saída do Quanthum Checkout: sem sessão/CSRF (rota de API), só HMAC.
Route::post('/webhooks/quanthum-checkout', QuanthumCheckoutWebhookController::class)
    ->middleware('throttle:120,1')
    ->name('quanthum-checkout.webhook');
