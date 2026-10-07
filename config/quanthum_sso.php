<?php

/*
 * Cliente OIDC do Quanthum SSO (perfil quanthum-oidc-profile 1.0): web
 * confidencial, Authorization Code + PKCE S256, state e nonce. Só o issuer é
 * configurado — endpoints e JWKS vêm do discovery. Desligado por padrão: o
 * login local (Fortify e-mail/senha) não muda com ou sem SSO.
 *
 * Segredos (client_secret) só por variável de ambiente: nunca neste arquivo.
 * Não confundir com o LDAP opt-in (config/ldap.php), que é diretório
 * corporativo, não o SSO Quanthum.
 */
return [
    'enabled' => (bool) env('QUANTHUM_SSO_ENABLED', false),

    /*
     * Cria um usuário (papel `user`) quando o `sub` é novo e não há usuário
     * local com o mesmo e-mail verificado. Desligado por padrão: o SSO não
     * abre cadastro por tabela.
     */
    'auto_provision' => (bool) env('QUANTHUM_SSO_AUTO_PROVISION', false),

    'issuer' => env('QUANTHUM_SSO_ISSUER'),
    'client_id' => env('QUANTHUM_SSO_CLIENT_ID'),
    'client_secret' => env('QUANTHUM_SSO_CLIENT_SECRET'),
    'redirect_uri' => env('QUANTHUM_SSO_REDIRECT_URI'),
    'post_logout_redirect_uri' => env('QUANTHUM_SSO_POST_LOGOUT_REDIRECT_URI'),

    'scopes' => 'openid profile email',

    /*
     * O provedor só assina com RS256. Qualquer outro algoritmo (none, HS*, ES*)
     * é rejeitado para evitar algorithm confusion.
     */
    'allowed_algorithms' => ['RS256'],

    /*
     * Discovery, JWKS, token endpoint e redirects exigem HTTPS. Só os testes
     * mudam isto (config() em fixture explícita); não há variável de ambiente.
     */
    'allow_insecure_http' => false,

    'http_timeout_seconds' => 5,
    'discovery_ttl_seconds' => 3600,
    'jwks_ttl_seconds' => 3600,
    'jwks_refresh_min_interval_seconds' => 60,
    'clock_leeway_seconds' => 30,
    'flow_ttl_seconds' => 600,

    /*
     * O id_token do provedor vive poucos minutos e o logout do provedor
     * rejeita id_token_hint expirado. Com menos que esta margem de vida, o
     * logout fica só local.
     */
    'logout_expiry_margin_seconds' => 10,
];
