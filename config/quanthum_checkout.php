<?php

/*
 * Cliente do servidor Quanthum Checkout (contrato:
 * quanthum-checkout/docs/integracao_client_apps.md). O app NÃO fala com
 * provedor de pagamento: só com o servidor de checkout. Valores em centavos
 * inteiros. Segredos (token e webhook secret) só por variável de ambiente.
 * Sem CHECKOUT_BASE_URL/CHECKOUT_CLIENT_TOKEN o cliente recusa chamar; sem
 * CHECKOUT_WEBHOOK_SECRET a rota de webhook responde 404.
 */
return [
    'base_url' => env('CHECKOUT_BASE_URL'),
    'client_token' => env('CHECKOUT_CLIENT_TOKEN'),

    /*
     * Segredo do webhook de saída. Na rotação do segredo o servidor assina com
     * os dois: configure o novo em CHECKOUT_WEBHOOK_SECRET e o anterior em
     * CHECKOUT_WEBHOOK_SECRET_PREVIOUS até a troca terminar.
     */
    'webhook_secret' => env('CHECKOUT_WEBHOOK_SECRET'),
    'webhook_secret_previous' => env('CHECKOUT_WEBHOOK_SECRET_PREVIOUS'),

    /* Tolerância entre X-Checkout-Timestamp e o relógio local (segundos). */
    'webhook_tolerance_seconds' => 300,

    /*
     * HTTPS obrigatório e sem redirects. Só os testes mudam isto (config() em
     * fixture explícita); não há variável de ambiente.
     */
    'allow_insecure_http' => false,

    'http_timeout_seconds' => 10,
];
