# Quanthum Aquiles

Núcleo Laravel da Quanthum Architecture. Todo projeto scaffolded a partir
daqui já nasce com os 8 pilares.

## O que já vem por padrão

| Pilar | Pacote / mecanismo |
|---|---|
| Enterprise Foundation | Laravel 13 + Sail (MySQL + Redis) |
| Security First | Sanctum (API), RBAC via spatie/laravel-permission, login via Fortify + botão "Entrar com Quanthum SSO" (OIDC, opt-in; ver abaixo) |
| Audit & Governance | owen-it/laravel-auditing (`audits` table, `User` já é `Auditable`) |
| Modern Frontend | flag `--frontend=react\|livewire-mary\|livewire-daisy\|livewire-tall` (ver `quanthum.json`) |
| AI Driven Development | `App\Services\AI\QaiOnlineService` + `config/qai.php` |
| Integration Layer | Horizon (dashboard em `/horizon`, protegido por RBAC) + filas Redis |
| Cloud Ready | Docker via Sail; ver seção "Deploy em produção (Dokploy)" da documentação da arquitetura |
| Security First / Diretório corporativo | directorytree/ldaprecord-laravel (LDAP) — **desligado por padrão** (ver "Login por LDAP") |
| Integration Layer / Plataforma Quanthum | cliente do SSO Quanthum (OIDC), do servidor de Licenças e do Quanthum Checkout — **desligados por padrão** (ver "SSO Quanthum / Licenças / Checkout") |

## Login

`laravel/fortify` já vem no núcleo — todo projeto nasce com `/login`,
`/forgot-password` e `/reset-password/{token}` funcionando, igual qualquer
starter kit padrão do Laravel (não é preciso instalar nada a mais). As
views ficam em `resources/views/auth/*.blade.php` (Tailwind puro, sem kit de
componentes — funcionam de graça pro variant `livewire-tall`). Os variants
`livewire-mary`/`livewire-daisy` sobrescrevem essas views com os componentes
do respectivo kit; o `react` sobrescreve `FortifyServiceProvider` inteiro
pra renderizar via Inertia (`resources/js/pages/auth/*.tsx`) em vez de Blade.

Registro de conta (`Features::registration()`) fica **desligado** por padrão
— a maioria dos projetos Aquiles são ferramentas internas/admin, com o
primeiro usuário criado via `DatabaseSeeder`. O variant `react` (o único
pensado como SPA client-facing) liga registro no seu próprio
`config/fortify.php`.

## Por que este núcleo existe

Consolida três implementações que existiam espalhadas e incompletas em
docs-hub, kosmos-one e cgov-agreements: auditoria só existia numa delas,
RBAC+SSO só em outra, e o cliente HTTP da QAI Online foi escrito do zero
três vezes com pequenas diferenças de retry/timeout. Aqui é uma coisa só,
usada por padrão.

## Primeiros passos depois do scaffold

O `setup` do `quanthum.json` já roda `composer install`, `.env`,
`key:generate` e `npm install`. O que fica pra você (depende de Docker, não
dá pra automatizar no scaffold):

```bash
./vendor/bin/sail up -d
./vendor/bin/sail artisan migrate --seed
```

Isso sobe MySQL + Redis + a aplicação e roda as migrations de todos os
pacotes (users, sessions, audits, personal_access_tokens, permissions, LDAP)
mais o seeder de roles (`super_admin`, `admin`, `user`).

## RBAC

Três papéis por padrão (convenção validada no cgov-agreements):
`super_admin` (tudo), `admin` (`manage-users`, `view-users`), `user` (base).
O usuário de teste do `DatabaseSeeder` já nasce `super_admin`. Adicione as
permissions do seu domínio em `database/seeders/RolePermissionSeeder.php`
sem apagar as três roles.

## SSO Quanthum / Licenças / Checkout

Tudo aqui é **aditivo e desligado por padrão**: sem as variáveis no `.env` o
scaffold sobe igual, o login local (Fortify e-mail/senha) segue funcionando, e
nenhuma chamada de rede acontece no boot nem nos testes. Linguagem de
referência: a Quanthum adota ISO/IEC 27001 e 27002 como referência, em
implantação (ver a Central de Confiança); este template não é certificado nem
garante conformidade por si só.

### SSO Quanthum (OIDC)

Cliente do `quanthum-sso` (perfil `quanthum-oidc-profile` 1.0): Authorization
Code + PKCE S256, `state`, `nonce`, id_token só RS256 (rejeita `none`/HS*/ES*),
validação de `iss`, `aud`, `exp`, `nonce`, discovery pelo issuer, HTTPS
obrigatório. Fica no núcleo (`app/Services/QuanthumSso`,
`config/quanthum_sso.php`, `routes/quanthum-sso.php`); cada frontend só ganha o
botão "Entrar com Quanthum SSO" (mesmo logo e `data-test="quanthum-sso-button"`)
na tela de login, exibido apenas com o SSO ligado **e** configurado.

1. Peça o cadastro do client OIDC à Quanthum Services (guia de integração OIDC).
   Redirect URI: `<APP_URL>/quanthum-sso/callback`.
2. Preencha `QUANTHUM_SSO_*` no `.env` e `QUANTHUM_SSO_ENABLED=true`. Issuer, client id, client secret, redirect URI e **`QUANTHUM_SSO_POST_LOGOUT_REDIRECT_URI`** são todos obrigatórios (URLs `https` válidas, sem curinga): com qualquer um vazio o SSO fica indisponível sem aviso e o botão não aparece. Referência de implantação, não garantia de conformidade.
3. Vínculo: por `issuer`+`sub`; na primeira entrada, por e-mail verificado (no
   provedor e localmente), nunca para `admin`/`super_admin`. Usuário novo só é
   criado com `QUANTHUM_SSO_AUTO_PROVISION=true` (papel `user`).

Só `issuer`+`sub` são gravados (`quanthum_sso_identities`); nome e e-mail vão
para o `users`. `client_secret`, `code`, `state` e id_token nunca entram em log.

### Licenças

Cliente do `quanthum-licenses` (`App\Services\QuanthumLicense\LicenseManager`,
`config/quanthum_license.php`): ativação com `php artisan quanthum:license:activate`,
validate/heartbeat agendados (só quando `QUANTHUM_LICENSE_SERVER_URL` existe),
assinatura Ed25519 conferida com `QUANTHUM_LICENSE_PUBLIC_KEY`. O middleware
`licensed:<modulo>` é inerte com `QUANTHUM_LICENSE_ENFORCE=false`; com `true`,
sem licença ativa e módulo habilitado responde 403 (pt/en).

### Checkout

Cliente fino do `quanthum-checkout` (`App\Services\QuanthumCheckout\CheckoutClient`:
criar com `Idempotency-Key`, consultar, nota) e webhook de saída em
`POST /api/webhooks/quanthum-checkout`, aceito só com HMAC-SHA256 válido
(`X-Checkout-Signature`/`X-Checkout-Timestamp`, comparação em tempo constante,
janela de 5 min). O evento autenticado vira `QuanthumCheckoutEventReceived`:
deduplique por `id` e processe em fila. Valores em centavos inteiros. **O app
não fala com Stripe nem Inter**: só com o servidor de checkout.

### Quem é responsável pelo quê

| Parte | Responsável |
|---|---|
| Validar id_token, PKCE/state/nonce, vínculo de usuário, sessão local, segredos no `.env`, HTTPS | o app (este template) |
| Autenticar a pessoa, emitir o id_token, cadastrar o client OIDC, chaves de assinatura | provedor (`quanthum-sso`) |
| Emitir/assinar a licença, módulos, expiração | provedor (`quanthum-licenses`); o app só valida e aplica |
| Cobrança, provedor de pagamento, nota fiscal, assinar o webhook | provedor (`quanthum-checkout`); o app só chama a API e confere o HMAC |
| Retenção de dados do produto, deduplicação de eventos, o que fazer em cada evento | o time do produto que usa o template |

O template não implementa 2FA, retenção nem tratamento de dados além do acima.

## Login por LDAP (opt-in)

LDAP é o diretório corporativo, **não** o SSO Quanthum (que é OIDC, acima).
Autenticação por padrão é `eloquent` (email/senha comuns) — funciona sem
nenhum servidor externo. Pra ligar o login por LDAP:

1. Preencha `LDAP_*` no `.env` (host, base DN, credenciais de bind).
2. Ajuste `config/auth.php` → `'model'` do provider LDAP pro model do seu
   diretório real (`LdapRecord\Models\ActiveDirectory\User` é o padrão;
   troque para `OpenLDAP\User`/`FreeIPA\User` se não for Active Directory).
3. `AUTH_PROVIDER=ldap` no `.env`.

**Não copie o padrão do cgov-agreements de colocar a senha de bind direto
no `config/ldap.php`** — use sempre `env('LDAP_PASSWORD')`, como já está
aqui.

## QAI Online

`App\Services\AI\QaiOnlineService` — stateless, aceita `apiUrl`/`apiKey`
por parâmetro ou cai no fallback de `config('qai.*')` (`QAI_ONLINE_*` no
`.env`). Se seu projeto precisa de múltiplos provedores por usuário/tenant,
crie seu próprio model (`AiIntegration`, como em docs-hub/cgov-agreements)
por cima deste client — ele não pressupõe nenhum model específico.

## Horizon

Dashboard em `/horizon`, protegido em `app/Providers/HorizonServiceProvider.php`
— só usuários com role `super_admin` acessam (não é allowlist de e-mail).

## Qualidade de código

```bash
sail composer fix      # aplica o Pint (formatação automática)
sail composer stan     # PHPStan/Larastan, nível 5, só em app/
sail composer verify   # pint --test + stan + testes em paralelo — roda tudo, não corrige nada
```

`verify` é o que roda antes de um PR/deploy: falha se o Pint encontrar
código fora do padrão (sem reescrever nada — pra isso é o `fix`), se o
PHPStan achar um problema de tipo, ou se algum teste quebrar.

## O que este núcleo NÃO faz

Não se auto-atualiza depois de scaffolded — ver seção "Evolução e
extensibilidade" da documentação da Quanthum Architecture. Mudanças aqui
(bump de versão de pacote, novo pilar) só chegam a projetos *novos*, nunca
retroativamente.
