<?php

namespace App\Services\QuanthumLicense;

use App\Models\QuanthumLicenseState;
use App\Services\QuanthumLicense\Exceptions\LicenseActivationException;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use SodiumException;
use Throwable;

/**
 * Consome o Servidor de Licenças Quanthum para o PRÓPRIO produto, seguindo o
 * guideline (guideline_produtos_licenciados_quanthum.md) com estas correções:
 * assinatura Ed25519 conferida também na ativação e com a chave validada; só
 * um 4xx definitivo com código definitivo restringe (5xx, 429, rede, corpo
 * fora do envelope usam o cache); heartbeat não derruba o scheduler; o cache
 * respeita expires_at e grace também NO REQUEST (sem rede); um "restricted"
 * dado pelo servidor só sai com um validate bem-sucedido; HTTPS obrigatório e
 * nenhum redirect é seguido.
 */
class LicenseManager
{
    /**
     * Erros do servidor que significam "esta licença não vale": só esses, e só
     * em resposta 4xx (menos 429), restringem.
     *
     * @var list<string>
     */
    private const RESTRICTING_ERROR_CODES = [
        'LICENSE_NOT_FOUND',
        'LICENSE_INVALID',
        'LICENSE_EXPIRED',
        'LICENSE_SUSPENDED',
        'LICENSE_REVOKED',
        'FINGERPRINT_MISMATCH',
    ];

    /**
     * Um last_success_at no FUTURO além desta tolerância (relógio adiantado uma
     * vez, VM restaurada) não conta como contato recente: senão a diferença
     * negativa manteria o cache "válido" para sempre.
     */
    private const CLOCK_SKEW_MINUTES = 5;

    /**
     * @return array<string, mixed> dados da ativação, sem o license_token
     */
    public function activate(string $licenseKey): array
    {
        $this->assertConfigured();

        $state = QuanthumLicenseState::current();
        $fingerprint = $this->fingerprint($state);

        try {
            $response = $this->request(10)->post($this->url('activate'), [
                'license_key' => $licenseKey,
                'product_key' => config('quanthum_license.product_key'),
                'instance_name' => config('app.name').' - '.app()->environment(),
                'environment' => app()->environment('production') ? 'production' : 'staging',
                'fingerprint' => $fingerprint,
            ]);
        } catch (ConnectionException $e) {
            throw new LicenseActivationException('Servidor de licenças indisponível.', 'SERVER_UNREACHABLE', $e);
        }

        if (! $response->successful()) {
            throw new LicenseActivationException(
                $this->safeServerMessage($response->json('error.message'), $licenseKey) ?? 'Falha ao ativar a licença.',
                $this->safeErrorCode($response->json('error.code')),
            );
        }

        $data = $this->licenseData($response);

        if ($data === null || ! is_string($data['license_token'] ?? null) || ! is_string($data['instance_id'] ?? null)) {
            throw new LicenseActivationException('Resposta de ativação inválida.', 'INVALID_RESPONSE');
        }

        if (! $this->verifySignature($data['license_token'])) {
            throw new LicenseActivationException('A assinatura do token de licença não confere com a chave pública configurada.', 'SIGNATURE_INVALID');
        }

        $state->update([
            'server_instance_id' => $data['instance_id'],
            'public_license_id' => $data['public_license_id'],
            'license_token' => Crypt::encryptString($data['license_token']),
            'fingerprint_hash' => hash('sha256', $fingerprint['installation_id'].'|'.$fingerprint['database_hash']),
            'modules' => $data['modules'],
            'grace_period_hours' => $data['grace_period_hours'],
            'expires_at' => $data['expires_at'],
            'last_success_at' => now(),
            'status' => QuanthumLicenseState::STATUS_ACTIVE,
        ]);

        unset($data['license_token'], $data['signature']);

        return $data;
    }

    public function validate(): string
    {
        $state = QuanthumLicenseState::current();
        $token = $this->tokenOf($state);

        if ($token === null) {
            $state->update(['status' => QuanthumLicenseState::STATUS_NOT_ACTIVATED]);

            return QuanthumLicenseState::STATUS_NOT_ACTIVATED;
        }

        if (! $this->serverUrlIsSecure()) {
            Log::warning('Servidor de licenças Quanthum com URL ausente ou insegura; usando o cache.');

            return $this->validateFromCache($state);
        }

        try {
            $response = $this->request(5)->post($this->url('validate'), [
                'instance_id' => $state->server_instance_id,
                'license_token' => $token,
                'fingerprint_hash' => $state->fingerprint_hash,
            ]);
        } catch (Throwable) {
            return $this->validateFromCache($state);
        }

        if ($response->successful()) {
            $data = $this->licenseData($response, $state->server_instance_id);

            if ($data === null) {
                Log::warning('Resposta do servidor de licenças Quanthum fora do envelope esperado; usando o cache.', [
                    'http_status' => $response->status(),
                ]);

                return $this->validateFromCache($state);
            }

            $state->update([
                'public_license_id' => $data['public_license_id'],
                'modules' => $data['modules'],
                'grace_period_hours' => $data['grace_period_hours'],
                'expires_at' => $data['expires_at'],
                'last_success_at' => now(),
                'status' => QuanthumLicenseState::STATUS_ACTIVE,
            ]);

            return QuanthumLicenseState::STATUS_ACTIVE;
        }

        $code = $this->safeErrorCode($response->json('error.code'));

        if (! $response->clientError() || $response->status() === 429 || ! in_array($code, self::RESTRICTING_ERROR_CODES, true)) {
            Log::warning('Servidor de licenças Quanthum indisponível ou resposta inesperada; usando o cache.', [
                'http_status' => $response->status(),
                'code' => $code,
            ]);

            return $this->validateFromCache($state);
        }

        if ($code === 'LICENSE_INVALID' && $this->reactivateAfterKeyRotation()) {
            return QuanthumLicenseState::STATUS_ACTIVE;
        }

        Log::warning('Licença Quanthum negada pelo servidor.', ['code' => $code]);

        return $this->restrict($state);
    }

    /**
     * @param  array<string, int|float>  $usage
     */
    public function heartbeat(array $usage = []): void
    {
        $state = QuanthumLicenseState::current();
        $token = $this->tokenOf($state);

        if ($token === null) {
            return;
        }

        if (! $this->serverUrlIsSecure()) {
            Log::warning('Heartbeat da licença Quanthum não enviado: URL do servidor ausente ou insegura.');

            return;
        }

        try {
            $this->request(10)->post($this->url('heartbeat'), [
                'instance_id' => $state->server_instance_id,
                'license_token' => $token,
                'health' => [
                    'app_version' => config('app.version', 'unknown'),
                    'php_version' => PHP_VERSION,
                    'laravel_version' => app()->version(),
                ],
                'usage' => $usage,
            ]);
        } catch (Throwable) {
            Log::warning('Heartbeat da licença Quanthum não pôde ser enviado.');
        }
    }

    /**
     * O módulo só vale com a licença EFETIVAMENTE ativa: a validade do cache
     * (assinatura, expires_at, grace) é conferida aqui, no request, sem rede.
     */
    public function can(string $moduleKey): bool
    {
        $state = QuanthumLicenseState::current();

        if ($this->effectiveStatusOf($state) !== QuanthumLicenseState::STATUS_ACTIVE) {
            return false;
        }

        foreach ($state->modules ?? [] as $module) {
            if (($module['key'] ?? null) === $moduleKey) {
                return (bool) ($module['enabled'] ?? false);
            }
        }

        return false;
    }

    public function limit(string $moduleKey, string $limitKey): ?int
    {
        $state = QuanthumLicenseState::current();

        if ($this->effectiveStatusOf($state) !== QuanthumLicenseState::STATUS_ACTIVE) {
            return null;
        }

        foreach ($state->modules ?? [] as $module) {
            if (($module['key'] ?? null) === $moduleKey) {
                return $module['limits'][$limitKey] ?? null;
            }
        }

        return null;
    }

    /**
     * Status efetivo, calculado só com o estado local: um scheduler parado não
     * mantém uma licença vencida (ou sem contato além do grace) como ativa.
     */
    public function status(): string
    {
        return $this->effectiveStatusOf(QuanthumLicenseState::current());
    }

    /**
     * Referência legível para a UI (ex.: "Licença: LIC-8U2IIULFWV"). Nunca
     * exiba server_instance_id nem license_token.
     */
    public function publicLicenseId(): ?string
    {
        return QuanthumLicenseState::current()->public_license_id;
    }

    private function effectiveStatusOf(QuanthumLicenseState $state): string
    {
        if ($this->tokenOf($state) === null || $state->status === QuanthumLicenseState::STATUS_NOT_ACTIVATED) {
            return QuanthumLicenseState::STATUS_NOT_ACTIVATED;
        }

        if ($state->status === QuanthumLicenseState::STATUS_RESTRICTED) {
            return QuanthumLicenseState::STATUS_RESTRICTED;
        }

        return $this->cacheIsValid($state)
            ? QuanthumLicenseState::STATUS_ACTIVE
            : QuanthumLicenseState::STATUS_RESTRICTED;
    }

    /**
     * A ÚNICA regra de validade do cache, usada no request e no fallback de
     * validate() (rede/5xx): assinatura, expires_at e grace contra o último
     * contato com sucesso, mais uma folga de dois ciclos de validate. O
     * scheduler só renova last_success_at a cada intervalo, então sem a folga
     * (grace 0/null) a licença oscilaria entre uma execução e outra, e um
     * único 503 persistiria restricted com o request ainda dizendo active.
     */
    private function cacheIsValid(QuanthumLicenseState $state): bool
    {
        $token = $this->tokenOf($state);

        if ($token === null || $state->last_success_at === null || ! $this->verifySignature($token)) {
            return false;
        }

        if ($state->expires_at !== null && $state->expires_at->isPast()) {
            return false;
        }

        $allowanceMinutes = 2 * LicenseSchedule::minutes(config('quanthum_license.validate_interval_minutes'), LicenseSchedule::DEFAULT_VALIDATE_MINUTES);
        $graceMinutes = ((int) ($state->grace_period_hours ?? 0)) * 60 + $allowanceMinutes;
        $minutesSinceSuccess = $state->last_success_at->diffInMinutes(now());

        if ($minutesSinceSuccess < -self::CLOCK_SKEW_MINUTES) {
            return false;
        }

        return $minutesSinceSuccess <= $graceMinutes;
    }

    private function validateFromCache(QuanthumLicenseState $state): string
    {
        if ($state->status === QuanthumLicenseState::STATUS_RESTRICTED) {
            return QuanthumLicenseState::STATUS_RESTRICTED;
        }

        return $this->cacheIsValid($state)
            ? QuanthumLicenseState::STATUS_ACTIVE
            : $this->restrict($state);
    }

    private function restrict(QuanthumLicenseState $state): string
    {
        $state->update(['status' => QuanthumLicenseState::STATUS_RESTRICTED]);

        return QuanthumLicenseState::STATUS_RESTRICTED;
    }

    private function assertConfigured(): void
    {
        foreach (['product_key', 'public_key'] as $key) {
            if (! is_string(config("quanthum_license.{$key}")) || trim((string) config("quanthum_license.{$key}")) === '') {
                throw new LicenseActivationException("Configuração ausente: quanthum_license.{$key}.", 'NOT_CONFIGURED');
            }
        }

        if (! $this->serverUrlIsSecure()) {
            throw new LicenseActivationException('Configuração ausente ou insegura: quanthum_license.server_url (HTTPS obrigatório).', 'NOT_CONFIGURED');
        }
    }

    /**
     * license_key e license_token viajam no corpo: só HTTPS (http apenas sob
     * allow_insecure_http, usado por fixtures), sem userinfo nem fragmento.
     */
    private function serverUrlIsSecure(): bool
    {
        $url = config('quanthum_license.server_url');
        $parts = is_string($url) ? parse_url(trim($url)) : false;

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            return false;
        }

        if (isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])) {
            return false;
        }

        $scheme = strtolower($parts['scheme']);

        return $scheme === 'https' || ($scheme === 'http' && (bool) config('quanthum_license.allow_insecure_http'));
    }

    private function request(int $timeout): PendingRequest
    {
        return Http::timeout($timeout)->connectTimeout($timeout)->withoutRedirecting()->acceptJson();
    }

    /**
     * Um 2xx só vale como resposta do servidor se seguir o envelope do
     * contrato: {success: true, data: {status: active, public_license_id,
     * grace_period_hours, expires_at (chave presente, pode ser null),
     * modules[]}}. Na validação o instance_id é obrigatório e tem de ser o da instância
     * ativada (o servidor sempre o envia). HTML de manutenção, corpo vazio ou outro formato NÃO é
     * validação positiva.
     *
     * @return array<string, mixed>|null
     */
    private function licenseData(Response $response, ?string $expectedInstanceId = null): ?array
    {
        $json = $response->json();

        if (! is_array($json) || ($json['success'] ?? null) !== true || ! is_array($json['data'] ?? null)) {
            return null;
        }

        $data = $json['data'];

        if (($data['status'] ?? null) !== 'active'
            || ! is_string($data['public_license_id'] ?? null) || $data['public_license_id'] === ''
            || ! array_key_exists('grace_period_hours', $data)
            || ($data['grace_period_hours'] !== null && (! is_int($data['grace_period_hours']) || $data['grace_period_hours'] < 0))
            || ! array_key_exists('expires_at', $data)
            || ! is_array($data['modules'] ?? null)) {
            return null;
        }

        if ($data['expires_at'] !== null && (! is_string($data['expires_at']) || strtotime($data['expires_at']) === false)) {
            return null;
        }

        if ($expectedInstanceId !== null && ($data['instance_id'] ?? null) !== $expectedInstanceId) {
            return null;
        }

        foreach ($data['modules'] as $module) {
            if (! is_array($module) || ! is_string($module['key'] ?? null) || $module['key'] === ''
                || ! is_bool($module['enabled'] ?? null)
                || (isset($module['limits']) && ! is_array($module['limits']))) {
                return null;
            }
        }

        return $data;
    }

    private function reactivateAfterKeyRotation(): bool
    {
        $licenseKey = config('quanthum_license.license_key');

        if (! is_string($licenseKey) || $licenseKey === '') {
            return false;
        }

        try {
            $this->activate($licenseKey);

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function tokenOf(QuanthumLicenseState $state): ?string
    {
        try {
            $token = $this->decryptedToken($state);
        } catch (DecryptException) {
            return null;
        }

        return is_string($token) && $token !== '' ? $token : null;
    }

    /**
     * O token fica cifrado no banco (Crypt): uma APP_KEY trocada faz isto lançar.
     *
     * @throws DecryptException
     */
    private function decryptedToken(QuanthumLicenseState $state): ?string
    {
        $stored = $state->license_token;

        return is_string($stored) && $stored !== '' ? Crypt::decryptString($stored) : null;
    }

    /**
     * Código de erro do servidor só é confiado (e gravado/logado) se tiver o
     * formato dos códigos do contrato: MAIÚSCULAS, dígitos e sublinhado.
     */
    private function safeErrorCode(mixed $code): ?string
    {
        return is_string($code) && preg_match('/^[A-Z][A-Z0-9_]{1,63}$/D', $code) === 1 ? $code : null;
    }

    /**
     * Mensagem do servidor vai para o terminal: sem controles/ANSI, só letras,
     * números, pontuação e espaço, no máximo 200 caracteres, e sem eco de
     * segredo da requisição.
     */
    private function safeServerMessage(mixed $message, string ...$secrets): ?string
    {
        if (! is_string($message)) {
            return null;
        }

        foreach ($secrets as $secret) {
            if ($secret !== '') {
                $message = str_replace($secret, '[redacted]', $message);
            }
        }

        $clean = preg_replace('/[^\p{L}\p{N}\p{P}\p{Zs}]+/u', '', $message) ?? '';
        $clean = trim(preg_replace('/\s+/u', ' ', $clean) ?? '');

        return $clean === '' ? null : mb_substr($clean, 0, 200);
    }

    /**
     * @return array{app_url: mixed, machine_id: string|null, database_hash: string, installation_id: string}
     */
    private function fingerprint(QuanthumLicenseState $state): array
    {
        return [
            'app_url' => config('app.url'),
            'machine_id' => gethostname() ?: null,
            'database_hash' => hash('sha256', (string) config('database.connections.'.config('database.default').'.database')),
            'installation_id' => $state->installation_id,
        ];
    }

    private function verifySignature(?string $token): bool
    {
        if ($token === null || $token === '') {
            return false;
        }

        $parts = explode('.', $token, 2);

        if (count($parts) !== 2) {
            return false;
        }

        $json = base64_decode($parts[0], true);
        $signature = base64_decode($parts[1], true);
        $publicKey = base64_decode((string) config('quanthum_license.public_key'), true);

        if ($json === false || $signature === false || $publicKey === false) {
            return false;
        }

        if (strlen($publicKey) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES || strlen($signature) !== SODIUM_CRYPTO_SIGN_BYTES) {
            return false;
        }

        try {
            return sodium_crypto_sign_verify_detached($signature, $json, $publicKey);
        } catch (SodiumException) {
            return false;
        }
    }

    private function url(string $path): string
    {
        return rtrim(trim((string) config('quanthum_license.server_url')), '/')."/api/v1/client/licenses/{$path}";
    }
}
