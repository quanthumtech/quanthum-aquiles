<?php

namespace App\Services\QuanthumLicense;

/**
 * Intervalos do validate/heartbeat. `minuto % N` só descreve "a cada N
 * minutos" quando N divide 60 (45 rodaria às 00/45/00, 90 de hora em hora),
 * então intervalos que não dividem 60, zero, negativos ou não numéricos voltam
 * ao padrão do guideline (15 e 30).
 */
final class LicenseSchedule
{
    public const DEFAULT_VALIDATE_MINUTES = 15;

    public const DEFAULT_HEARTBEAT_MINUTES = 30;

    public static function minutes(mixed $configured, int $default): int
    {
        $minutes = is_numeric($configured) ? (int) $configured : 0;

        return $minutes >= 1 && $minutes <= 60 && 60 % $minutes === 0 ? $minutes : $default;
    }

    public static function cron(mixed $configured, int $default): string
    {
        $minutes = self::minutes($configured, $default);

        return $minutes === 60 ? '0 * * * *' : "*/{$minutes} * * * *";
    }
}
