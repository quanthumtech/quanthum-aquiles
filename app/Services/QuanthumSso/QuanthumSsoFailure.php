<?php

namespace App\Services\QuanthumSso;

/**
 * Motivos de falha do fluxo SSO. O valor é um código estável (sem PII, sem
 * token) usado em log e devolvido à tela de login, que o traduz no front.
 */
enum QuanthumSsoFailure: string
{
    case NotConfigured = 'not_configured';
    case DiscoveryFailed = 'discovery_failed';
    case JwksUnavailable = 'jwks_unavailable';
    case InvalidState = 'invalid_state';
    case ExpiredFlow = 'expired_flow';
    case MissingCode = 'missing_code';
    case ProviderDenied = 'provider_denied';
    case ProviderLoginRequired = 'provider_login_required';
    case ProviderUnavailable = 'provider_unavailable';
    case ProviderError = 'provider_error';
    case InvalidGrant = 'invalid_grant';
    case TokenExchangeFailed = 'token_exchange_failed';
    case InvalidIdToken = 'invalid_id_token';
    case EmailMissing = 'email_missing';
    case EmailNotVerified = 'email_not_verified';
    case AccountNotFound = 'account_not_found';
    case AdminLinkBlocked = 'admin_link_blocked';
    case IdentityConflict = 'identity_conflict';
    case LocalEmailNotVerified = 'local_email_not_verified';

    /**
     * Chave (lang/<locale>/quanthum_sso.php, `error.<chave>`) da mensagem que a
     * tela de login mostra. Mesmo agrupamento da QAI; o motivo exato só vai ao log.
     */
    public function messageKey(): string
    {
        return match ($this) {
            self::DiscoveryFailed, self::JwksUnavailable, self::ProviderUnavailable => 'unavailable',
            self::ProviderDenied => 'denied',
            self::ProviderLoginRequired => 'reauth',
            self::EmailMissing, self::EmailNotVerified => 'emailNotVerified',
            self::AccountNotFound => 'accountNotFound',
            self::AdminLinkBlocked, self::IdentityConflict => 'notLinkable',
            self::LocalEmailNotVerified => 'localEmailNotVerified',
            default => 'generic',
        };
    }
}
