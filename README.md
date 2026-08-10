# Quanthum Aquiles

Núcleo Laravel da Quanthum Architecture. Todo projeto scaffolded a partir
daqui já nasce com os 8 pilares (menos o Frontend Moderno — isso é a M2,
uma flag ainda não implementada; por enquanto este núcleo é backend-only).

## O que já vem por padrão

| Pilar | Pacote / mecanismo |
|---|---|
| Enterprise Foundation | Laravel 13 + Sail (MySQL + Redis) |
| Security First | Sanctum (API), RBAC via spatie/laravel-permission |
| Audit & Governance | owen-it/laravel-auditing (`audits` table, `User` já é `Auditable`) |
| AI Driven Development | `App\Services\AI\QaiOnlineService` + `config/qai.php` |
| Integration Layer | Horizon (dashboard em `/horizon`, protegido por RBAC) + filas Redis |
| Cloud Ready | Docker via Sail; ver seção "Deploy em produção (Dokploy)" da documentação da arquitetura |
| Security First / SSO | directorytree/ldaprecord-laravel — **desligado por padrão** (ver abaixo) |

Frontend (pilar "Modern Frontend") ainda não está incluído — Laravel entrega
o esqueleto padrão (Blade + Vite mínimo). As 4 variações
(`--frontend=react|livewire-mary|livewire-daisy|livewire-tall`) são a M2.

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

## O que este núcleo NÃO faz

Não se auto-atualiza depois de scaffolded — ver seção "Evolução e
extensibilidade" da documentação da Quanthum Architecture. Mudanças aqui
(bump de versão de pacote, novo pilar) só chegam a projetos *novos*, nunca
retroativamente.
