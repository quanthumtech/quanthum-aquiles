import { Button } from '@/components/ui/button';

type Messages = {
    button: string;
    or: string;
    errors: Record<string, string>;
};

const PT: Messages = {
    button: 'Entrar com Quanthum SSO',
    or: 'ou',
    errors: {
        generic: 'Não foi possível entrar com o Quanthum SSO. Tente novamente ou use seu e-mail e senha.',
        unavailable: 'O Quanthum SSO está indisponível no momento. Use seu e-mail e senha ou tente mais tarde.',
        denied: 'O acesso foi negado pelo Quanthum SSO.',
        reauth: 'O Quanthum SSO pediu uma nova autenticação. Tente entrar novamente.',
        emailNotVerified: 'O e-mail da sua conta Quanthum ainda não foi verificado. Verifique-o e tente novamente.',
        accountNotFound: 'Não há uma conta neste app para esta identidade Quanthum. Peça acesso ao administrador.',
        notLinkable:
            'Esta conta não pode ser vinculada automaticamente ao Quanthum SSO. Entre com e-mail e senha ou fale com o suporte.',
        localEmailNotVerified:
            'O e-mail da sua conta neste app ainda não foi verificado, então ela não pode ser vinculada ao Quanthum SSO. Entre com e-mail e senha, verifique o e-mail e tente de novo.',
    },
};

const EN: Messages = {
    button: 'Sign in with Quanthum SSO',
    or: 'or',
    errors: {
        generic: 'We could not sign you in with Quanthum SSO. Try again or use your email and password.',
        unavailable: 'Quanthum SSO is unavailable right now. Use your email and password or try again later.',
        denied: 'Access was denied by Quanthum SSO.',
        reauth: 'Quanthum SSO asked you to authenticate again. Try signing in again.',
        emailNotVerified: 'The email on your Quanthum account is not verified yet. Verify it and try again.',
        accountNotFound: 'There is no account in this app for this Quanthum identity. Ask an administrator for access.',
        notLinkable:
            'This account cannot be linked to Quanthum SSO automatically. Sign in with email and password or contact support.',
        localEmailNotVerified:
            'The email of your account in this app is not verified yet, so it cannot be linked to Quanthum SSO. Sign in with email and password, verify the email and try again.',
    },
};

// Código estável do backend (QuanthumSsoFailure) -> chave da mensagem.
const ERROR_KEYS: Record<string, string> = {
    discovery_failed: 'unavailable',
    jwks_unavailable: 'unavailable',
    provider_unavailable: 'unavailable',
    provider_denied: 'denied',
    provider_login_required: 'reauth',
    email_missing: 'emailNotVerified',
    email_not_verified: 'emailNotVerified',
    account_not_found: 'accountNotFound',
    admin_link_blocked: 'notLinkable',
    identity_conflict: 'notLinkable',
    local_email_not_verified: 'localEmailNotVerified',
};

export function ssoMessages(locale?: string): Messages {
    return locale?.toLowerCase().startsWith('pt') ? PT : EN;
}

export function QuanthumSsoError({ code, locale }: { code?: string | null; locale?: string }) {
    if (!code) {
        return null;
    }

    const messages = ssoMessages(locale);

    return (
        <div
            role="alert"
            data-test="quanthum-sso-error"
            className="rounded-md border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm font-medium text-destructive"
        >
            {messages.errors[ERROR_KEYS[code] ?? 'generic']}
        </div>
    );
}

/** Separador "ou" + botão. Só renderiza com o SSO disponível (flag + config completa). */
export function QuanthumSsoButton({ available, locale }: { available?: boolean; locale?: string }) {
    if (!available) {
        return null;
    }

    const messages = ssoMessages(locale);

    return (
        <>
            <div className="flex items-center gap-3 text-xs text-muted-foreground" aria-hidden="true">
                <span className="h-px flex-1 bg-border" />
                <span>{messages.or}</span>
                <span className="h-px flex-1 bg-border" />
            </div>
            <Button variant="outline" className="w-full" asChild>
                <a href="/quanthum-sso/redirect" data-test="quanthum-sso-button">
                    <img
                        src="/images/auth/quanthum-q-light.png"
                        alt=""
                        width={20}
                        height={20}
                        aria-hidden="true"
                        className="size-5 dark:hidden"
                    />
                    <img
                        src="/images/auth/quanthum-q-dark.png"
                        alt=""
                        width={20}
                        height={20}
                        aria-hidden="true"
                        className="hidden size-5 dark:block"
                    />
                    {messages.button}
                </a>
            </Button>
        </>
    );
}
