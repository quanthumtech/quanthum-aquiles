<?php

use App\Models\User;
use App\Services\QuanthumLicense\LicenseManager;
use App\Services\QuanthumLicense\LicenseSchedule;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Licença do produto: só agenda quando há servidor de licenças configurado
// (sem QUANTHUM_LICENSE_SERVER_URL o scaffold não faz nada, nem chamada de rede).
if (is_string(config('quanthum_license.server_url')) && config('quanthum_license.server_url') !== '') {
    Schedule::call(fn () => app(LicenseManager::class)->validate())
        ->name('quanthum-license-validate')
        ->cron(LicenseSchedule::cron(config('quanthum_license.validate_interval_minutes'), LicenseSchedule::DEFAULT_VALIDATE_MINUTES))
        ->withoutOverlapping();

    Schedule::call(fn () => app(LicenseManager::class)->heartbeat(['users' => User::query()->count()]))
        ->name('quanthum-license-heartbeat')
        ->cron(LicenseSchedule::cron(config('quanthum_license.heartbeat_interval_minutes'), LicenseSchedule::DEFAULT_HEARTBEAT_MINUTES))
        ->withoutOverlapping();
}
