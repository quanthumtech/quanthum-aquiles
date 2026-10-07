<?php

namespace Tests\Feature\QuanthumLicense;

use App\Models\QuanthumLicenseState;
use App\Services\QuanthumLicense\Exceptions\LicenseActivationException;
use App\Services\QuanthumLicense\LicenseManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Tests\Support\QuanthumLicenseFixture as Fixture;
use Tests\TestCase;

class LicenseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('licensed:reports')->get('/__licensed-test', fn () => 'ok');
    }

    public function test_licensed_middleware_is_inert_with_enforce_off(): void
    {
        $this->assertFalse((bool) config('quanthum_license.enforce'));

        $this->get('/__licensed-test')->assertOk();
        Http::assertNothingSent();
    }

    public function test_enforce_on_without_license_returns_403_in_english_and_portuguese(): void
    {
        Fixture::configure();

        $this->get('/__licensed-test', ['Accept-Language' => 'en'])
            ->assertForbidden()
            ->assertSee('This feature is not enabled in your license.');

        $this->get('/__licensed-test', ['Accept-Language' => 'pt-BR'])
            ->assertForbidden()
            ->assertSee('Este recurso não está habilitado na sua licença.');

        Http::assertNothingSent();
    }

    public function test_enforce_on_with_an_active_licensed_module_passes(): void
    {
        Fixture::configure();
        Fixture::fakeActivation(Fixture::token());

        app(LicenseManager::class)->activate('LICENSE-KEY-TEST');

        $this->get('/__licensed-test')->assertOk();
    }

    public function test_enforce_on_with_a_module_missing_from_the_license_is_403(): void
    {
        Fixture::configure();
        Fixture::fakeActivation(Fixture::token(), [['key' => 'other', 'enabled' => true]]);

        app(LicenseManager::class)->activate('LICENSE-KEY-TEST');

        $this->get('/__licensed-test')->assertForbidden();
    }

    public function test_token_signed_by_another_key_is_refused_and_nothing_is_stored(): void
    {
        Fixture::configure();
        Fixture::fakeActivation(Fixture::token(Fixture::foreignSecretKey()));

        try {
            app(LicenseManager::class)->activate('LICENSE-KEY-TEST');
            $this->fail('A assinatura de outra chave deveria ser recusada.');
        } catch (LicenseActivationException $e) {
            $this->assertSame('SIGNATURE_INVALID', $e->errorCode);
        }

        $this->assertNull(QuanthumLicenseState::current()->license_token);
        $this->get('/__licensed-test')->assertForbidden();
    }

    public function test_insecure_server_url_is_refused_without_calling_the_server(): void
    {
        Fixture::configure();
        config(['quanthum_license.server_url' => 'http://licenses.example.test']);
        Http::fake();

        try {
            app(LicenseManager::class)->activate('LICENSE-KEY-TEST');
            $this->fail('HTTP deveria ser recusado.');
        } catch (LicenseActivationException $e) {
            $this->assertSame('NOT_CONFIGURED', $e->errorCode);
        }

        Http::assertNothingSent();
    }

    public function test_license_token_is_stored_encrypted(): void
    {
        Fixture::configure();
        $token = Fixture::token();
        Fixture::fakeActivation($token);

        app(LicenseManager::class)->activate('LICENSE-KEY-TEST');

        $raw = (string) DB::table('quanthum_license_state')->value('license_token');
        $this->assertNotSame($token, $raw);
        $this->assertStringNotContainsString($token, $raw);
    }
}
