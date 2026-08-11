# Quanthum Aquiles

Núcleo Laravel da Quanthum Architecture. Todo projeto scaffolded a partir
daqui já nasce com os 8 pilares.

## O que já vem por padrão

| Pilar | Pacote / mecanismo |
|---|---|
| Enterprise Foundation | Laravel 13 + Sail (MySQL + Redis) |
| Security First | Sanctum (API), RBAC via spatie/laravel-permission, login via Fortify (ver abaixo) |
| Audit & Governance | owen-it/laravel-auditing (`audits` table, `User` já é `Auditable`) |
| Modern Frontend | flag `--frontend=react\|livewire-mary\|livewire-daisy\|livewire-tall` (ver `quanthum.json`) |
| AI Driven Development | `App\Services\AI\QaiOnlineService` + `config/qai.php` |
| Integration Layer | Horizon (dashboard em `/horizon`, protegido por RBAC) + filas Redis |
| Cloud Ready | Docker via Sail; ver seção "Deploy em produção (Dokploy)" da documentação da arquitetura |
| Security First / SSO | directorytree/ldaprecord-laravel — **desligado por padrão** (ver abaixo) |

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

## SSO (LDAP) — opt-in

Autenticação por padrão é `eloquent` (email/senha comuns) — funciona sem
nenhum servidor externo. Pra ligar SSO de verdade:

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
