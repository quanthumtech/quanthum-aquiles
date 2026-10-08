<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Vínculo explícito entre um usuário do app e uma identidade do Quanthum SSO
 * (issuer + sub, único). Nunca se liga por e-mail depois de criado.
 */
class QuanthumSsoIdentity extends Model
{
    protected $fillable = [
        'user_id',
        'issuer',
        'sub',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
