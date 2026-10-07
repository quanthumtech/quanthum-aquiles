<?php

namespace App\Services\QuanthumSso;

use App\Models\QuanthumSsoIdentity;
use App\Models\User;
use App\Services\QuanthumSso\Exceptions\QuanthumSsoException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * Decide qual usuário local corresponde a uma identidade do SSO.
 *
 * 1. issuer + sub já vinculado: entra (o e-mail nunca é chave).
 * 2. senão, usuário local com o MESMO e-mail, verificado no provedor
 *    (email_verified===true) E localmente (email_verified_at): vínculo
 *    explícito e auditável, nunca para quem tem papel admin/super_admin.
 * 3. senão, com QUANTHUM_SSO_AUTO_PROVISION: cria usuário com o papel `user`.
 *    Nunca atribui admin/super_admin.
 *
 * Persistência: só issuer + sub (tabela de identidades) e, no cadastro, nome
 * e e-mail do usuário. Nenhum outro claim do id_token é gravado.
 */
class QuanthumSsoUserResolver
{
    /** Papéis que o SSO nunca vincula automaticamente nem concede. */
    private const PRIVILEGED_ROLES = ['super_admin', 'admin'];

    public function __construct(private readonly QuanthumSsoConfig $config) {}

    public function resolve(QuanthumSsoAssertion $assertion): User
    {
        $identity = $this->findIdentity($assertion);

        if ($identity !== null) {
            return $identity->user;
        }

        if ($assertion->email === null) {
            throw new QuanthumSsoException(QuanthumSsoFailure::EmailMissing);
        }

        if (! $assertion->emailVerified) {
            throw new QuanthumSsoException(QuanthumSsoFailure::EmailNotVerified);
        }

        $existing = $this->findLocalUserByEmail($assertion);

        if ($existing !== null) {
            return $this->link($existing, $assertion);
        }

        if (! $this->config->autoProvision()) {
            throw new QuanthumSsoException(QuanthumSsoFailure::AccountNotFound);
        }

        return $this->provision($assertion);
    }

    /**
     * A busca do MySQL é só um filtro de candidatos (colação e PAD SPACE
     * igualam 'sub' e 'sub '); a identidade só vale se issuer e sub forem
     * IDÊNTICOS byte a byte.
     */
    private function findIdentity(QuanthumSsoAssertion $assertion): ?QuanthumSsoIdentity
    {
        return QuanthumSsoIdentity::query()
            ->where('issuer', $assertion->issuer)
            ->where('sub', $assertion->sub)
            ->get()
            ->first(fn (QuanthumSsoIdentity $identity) => $identity->issuer === $assertion->issuer && $identity->sub === $assertion->sub);
    }

    /**
     * users.email é UNIQUE, então a busca (que ignora caixa e acento na colação
     * do MySQL) devolve no máximo UM candidato. Ele é CONFIRMADO em PHP
     * comparando e-mails normalizados; um candidato que só se parece com o
     * e-mail do SSO recusa: nunca vincula.
     */
    private function findLocalUserByEmail(QuanthumSsoAssertion $assertion): ?User
    {
        $candidate = User::query()->where('email', $assertion->email)->first();

        if ($candidate === null) {
            return null;
        }

        if (mb_strtolower(trim($candidate->email)) === $assertion->email) {
            return $candidate;
        }

        Log::warning('Quanthum SSO link refused: look-alike local e-mail', [
            'event' => 'link_refused_lookalike_email',
            'issuer' => $assertion->issuer,
        ]);

        throw new QuanthumSsoException(QuanthumSsoFailure::IdentityConflict);
    }

    /**
     * Verificar e gravar acontecem na MESMA transação, com o usuário travado
     * (lockForUpdate); a UNIQUE(user_id, issuer) é a rede de segurança.
     */
    private function link(User $user, QuanthumSsoAssertion $assertion): User
    {
        $created = false;

        try {
            $linked = DB::transaction(function () use ($user, $assertion, &$created): User {
                $locked = User::query()->whereKey($user->id)->lockForUpdate()->first();

                if ($locked === null) {
                    throw new QuanthumSsoException(QuanthumSsoFailure::AccountNotFound);
                }

                if ($this->hasThisIdentity($locked, $assertion)) {
                    return $locked;
                }

                $this->assertLinkable($locked, $assertion);

                QuanthumSsoIdentity::query()->create([
                    'user_id' => $locked->id,
                    'issuer' => $assertion->issuer,
                    'sub' => $assertion->sub,
                ]);
                $created = true;

                return $locked;
            });
        } catch (UniqueConstraintViolationException) {
            $existing = $this->findIdentity($assertion);

            if ($existing !== null && $existing->user_id === $user->id) {
                return $user;
            }

            Log::warning('Quanthum SSO link refused: concurrent link or identity already taken', [
                'event' => 'link_refused_conflict',
                'user_id' => $user->id,
                'issuer' => $assertion->issuer,
            ]);

            throw new QuanthumSsoException(QuanthumSsoFailure::IdentityConflict);
        }

        if ($created) {
            Log::info('Quanthum SSO identity linked to an existing account', [
                'event' => 'linked',
                'user_id' => $linked->id,
                'issuer' => $assertion->issuer,
            ]);
        }

        return $linked;
    }

    /**
     * Um callback concorrente da MESMA identidade (duas abas) já a gravou
     * enquanto este esperava o lock: é sucesso idempotente, não conflito.
     */
    private function hasThisIdentity(User $user, QuanthumSsoAssertion $assertion): bool
    {
        return QuanthumSsoIdentity::query()
            ->where('user_id', $user->id)
            ->where('issuer', $assertion->issuer)
            ->where('sub', $assertion->sub)
            ->get()
            ->contains(fn (QuanthumSsoIdentity $identity) => $identity->issuer === $assertion->issuer && $identity->sub === $assertion->sub);
    }

    /**
     * Roda DENTRO do lock, com o usuário recarregado: o e-mail e o
     * email_verified_at valem agora, não no momento da busca. Sem exigir o
     * e-mail LOCAL verificado, quem pré-cadastra victim@dominio receberia a
     * identidade da vítima quando ela entrasse por SSO (pré-sequestro).
     */
    private function assertLinkable(User $user, QuanthumSsoAssertion $assertion): void
    {
        if (mb_strtolower(trim($user->email)) !== $assertion->email) {
            $this->refuse('link_refused_email_changed', $user, $assertion, QuanthumSsoFailure::IdentityConflict);
        }

        if ($user->email_verified_at === null) {
            $this->refuse('link_refused_local_email_unverified', $user, $assertion, QuanthumSsoFailure::LocalEmailNotVerified);
        }

        if ($user->hasAnyRole(self::PRIVILEGED_ROLES)) {
            $this->refuse('link_refused_privileged', $user, $assertion, QuanthumSsoFailure::AdminLinkBlocked);
        }

        $alreadyLinkedToIssuer = QuanthumSsoIdentity::query()
            ->where('user_id', $user->id)
            ->where('issuer', $assertion->issuer)
            ->exists();

        if ($alreadyLinkedToIssuer) {
            $this->refuse('link_refused_conflict', $user, $assertion, QuanthumSsoFailure::IdentityConflict);
        }
    }

    private function refuse(string $event, User $user, QuanthumSsoAssertion $assertion, QuanthumSsoFailure $failure): never
    {
        Log::warning('Quanthum SSO link refused', [
            'event' => $event,
            'user_id' => $user->id,
            'issuer' => $assertion->issuer,
        ]);

        throw new QuanthumSsoException($failure);
    }

    private function provision(QuanthumSsoAssertion $assertion): User
    {
        try {
            $user = DB::transaction(function () use ($assertion): User {
                $user = new User;
                $user->forceFill([
                    'name' => $assertion->name !== null && $assertion->name !== ''
                        ? $assertion->name
                        : Str::before((string) $assertion->email, '@'),
                    'email' => $assertion->email,
                    'password' => Str::random(64),
                    'email_verified_at' => now(),
                ])->save();

                $user->assignRole(Role::findOrCreate('user', 'web'));

                QuanthumSsoIdentity::query()->create([
                    'user_id' => $user->id,
                    'issuer' => $assertion->issuer,
                    'sub' => $assertion->sub,
                ]);

                return $user;
            });
        } catch (UniqueConstraintViolationException) {
            $identity = $this->findIdentity($assertion);

            if ($identity === null) {
                throw new QuanthumSsoException(QuanthumSsoFailure::AccountNotFound);
            }

            return $identity->user;
        }

        Log::info('Quanthum SSO created an account', [
            'event' => 'provisioned',
            'user_id' => $user->id,
            'issuer' => $assertion->issuer,
        ]);

        return $user;
    }
}
