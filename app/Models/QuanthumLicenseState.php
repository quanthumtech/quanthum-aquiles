<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;

/**
 * Estado local (singleton, id = 1) da licença do próprio produto junto ao
 * Servidor de Licenças Quanthum. O token fica cifrado (Crypt, feito pelo LicenseManager).
 */
class QuanthumLicenseState extends Model
{
    public const STATUS_NOT_ACTIVATED = 'not_activated';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_RESTRICTED = 'restricted';

    protected $table = 'quanthum_license_state';

    protected $fillable = [
        'installation_id',
        'server_instance_id',
        'public_license_id',
        'license_token',
        'fingerprint_hash',
        'modules',
        'grace_period_hours',
        'expires_at',
        'last_success_at',
        'status',
    ];

    protected $casts = [
        'modules' => 'array',
        'expires_at' => 'datetime',
        'last_success_at' => 'datetime',
    ];

    /**
     * O id não é fillable, então a linha única é criada com forceCreate: um
     * firstOrCreate(['id' => 1]) só funcionaria por coincidência do
     * auto-increment e criaria uma linha nova a cada chamada se a original
     * fosse apagada.
     */
    public static function current(): self
    {
        $state = static::query()->find(1);

        if ($state !== null) {
            return $state;
        }

        try {
            return static::query()->forceCreate([
                'id' => 1,
                'installation_id' => (string) Str::uuid(),
                'status' => self::STATUS_NOT_ACTIVATED,
            ]);
        } catch (UniqueConstraintViolationException) {
            return static::query()->findOrFail(1);
        }
    }
}
