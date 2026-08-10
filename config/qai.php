<?php

return [

    /*
    |--------------------------------------------------------------------------
    | QAI Online — configuração padrão
    |--------------------------------------------------------------------------
    |
    | Aplicações com necessidades mais avançadas (múltiplos provedores por
    | tenant, chaves por usuário, etc.) costumam guardar isso num model
    | próprio (ver AiIntegration no docs-hub/cgov-agreements) — esses valores
    | aqui são só o fallback usado quando nenhum override é passado nas
    | chamadas do QaiOnlineService.
    |
    */

    'url' => env('QAI_ONLINE_URL'),

    'key' => env('QAI_ONLINE_KEY'),

    'model' => env('QAI_ONLINE_MODEL', 'gpt-4o-mini'),

    'timeout' => (int) env('QAI_ONLINE_TIMEOUT', 240),

];
