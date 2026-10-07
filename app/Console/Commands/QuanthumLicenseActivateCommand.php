<?php

namespace App\Console\Commands;

use App\Services\QuanthumLicense\Exceptions\LicenseActivationException;
use App\Services\QuanthumLicense\LicenseManager;
use Illuminate\Console\Command;

class QuanthumLicenseActivateCommand extends Command
{
    protected $signature = 'quanthum:license:activate {key? : license_key (default: QUANTHUM_LICENSE_KEY do .env)}';

    protected $description = 'Ativa a licença deste produto junto ao servidor de licenças Quanthum.';

    public function handle(LicenseManager $licenseManager): int
    {
        $key = $this->argument('key') ?? config('quanthum_license.license_key');

        if (! is_string($key) || $key === '') {
            $this->error('Informe a license_key (argumento ou QUANTHUM_LICENSE_KEY no .env).');

            return self::FAILURE;
        }

        try {
            $data = $licenseManager->activate($key);
        } catch (LicenseActivationException $e) {
            $this->error($e->getMessage().($e->errorCode ? " [{$e->errorCode}]" : ''));

            return self::FAILURE;
        }

        $this->info('Licença ativada com sucesso.');
        $this->line('Licença: '.($data['public_license_id'] ?? '-'));
        $this->line('Expira em: '.($data['expires_at'] ?? '-'));

        return self::SUCCESS;
    }
}
