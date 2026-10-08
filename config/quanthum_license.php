<?php

/*
 * Licença do PRÓPRIO produto junto ao Servidor de Licenças Quanthum (contrato:
 * quanthum-licenses/docs/guideline_produtos_licenciados_quanthum.md). Não tem
 * relação com qualquer licença comercial que o produto venda a seus clientes.
 */
return [
    'server_url' => env('QUANTHUM_LICENSE_SERVER_URL'),
    'product_key' => env('QUANTHUM_PRODUCT_KEY'),
    'license_key' => env('QUANTHUM_LICENSE_KEY'),
    'public_key' => env('QUANTHUM_LICENSE_PUBLIC_KEY'),
    'validate_interval_minutes' => (int) env('QUANTHUM_LICENSE_VALIDATE_INTERVAL_MINUTES', 15),
    'heartbeat_interval_minutes' => (int) env('QUANTHUM_LICENSE_HEARTBEAT_INTERVAL_MINUTES', 30),

    /*
     * Enforcement do middleware `licensed:<modulo>`. Desligado por padrão: com
     * false o middleware deixa tudo passar e o comportamento do app é idêntico
     * ao de antes da integração. Ligado, exige licença ativa (não restrita) e
     * o módulo habilitado.
     */
    'enforce' => (bool) env('QUANTHUM_LICENSE_ENFORCE', false),

    /*
     * As chamadas ao servidor de licenças exigem HTTPS (o license_key e o
     * license_token viajam no corpo) e não seguem redirects. Só os testes
     * mudam isto (config() em fixture explícita); não há variável de ambiente.
     */
    'allow_insecure_http' => false,

    /*
     * Mensagem do 403 de EnsureFeatureIsLicensed por idioma. O backend não
     * tem lang/ nem locale por usuário: vale o Accept-Language do request.
     */
    'forbidden_messages' => [
        'pt' => 'Este recurso não está habilitado na sua licença.',
        'en' => 'This feature is not enabled in your license.',
    ],
];
