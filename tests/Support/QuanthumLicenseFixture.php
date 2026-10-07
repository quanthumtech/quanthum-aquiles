<?php

namespace Tests\Support;

use Illuminate\Support\Facades\Http;

/**
 * Servidor de licenças simulado: par Ed25519 real (sodium) e token assinado no
 * formato `base64(json).base64(assinatura)`. Nada aqui fala com rede.
 */
final class QuanthumLicenseFixture
{
    public const SERVER = 'https://licenses.example.test';

    private static ?string $secretKey = null;

    private static ?string $publicKey = null;

    public static function configure(bool $enforce = true): void
    {
        self::keys();

        config([
            'quanthum_license.server_url' => self::SERVER,
            'quanthum_license.product_key' => 'product-test',
            'quanthum_license.public_key' => base64_encode((string) self::$publicKey),
            'quanthum_license.enforce' => $enforce,
            'quanthum_license.allow_insecure_http' => false,
        ]);
    }

    public static function token(?string $withSecretKey = null): string
    {
        self::keys();

        $json = json_encode(['license' => 'LIC-TEST', 'iat' => time()], JSON_THROW_ON_ERROR);
        $signature = sodium_crypto_sign_detached($json, $withSecretKey ?? (string) self::$secretKey);

        return base64_encode($json).'.'.base64_encode($signature);
    }

    /**
     * Chave secreta de OUTRO par, para provar que assinatura alheia é recusada.
     */
    public static function foreignSecretKey(): string
    {
        return sodium_crypto_sign_secretkey(sodium_crypto_sign_keypair());
    }

    /**
     * @param  list<array<string, mixed>>  $modules
     */
    public static function fakeActivation(string $token, array $modules = [['key' => 'reports', 'enabled' => true]]): void
    {
        Http::fake([
            self::SERVER.'/api/v1/client/licenses/activate' => Http::response([
                'success' => true,
                'data' => [
                    'status' => 'active',
                    'instance_id' => 'instance-1',
                    'public_license_id' => 'LIC-TEST',
                    'license_token' => $token,
                    'grace_period_hours' => 24,
                    'expires_at' => null,
                    'modules' => $modules,
                ],
            ]),
        ]);
    }

    private static function keys(): void
    {
        if (self::$secretKey === null) {
            $pair = sodium_crypto_sign_keypair();
            self::$secretKey = sodium_crypto_sign_secretkey($pair);
            self::$publicKey = sodium_crypto_sign_publickey($pair);
        }
    }
}
