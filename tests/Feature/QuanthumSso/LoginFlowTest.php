<?php

namespace Tests\Feature\QuanthumSso;

use App\Models\QuanthumSsoIdentity;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\Support\QuanthumSsoFixture as Fixture;
use Tests\TestCase;

class LoginFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->withoutVite();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_login_page_has_no_sso_button_by_default(): void
    {
        $login = $this->loginPage();

        $this->assertFalse($login['available']);
        $this->assertStringNotContainsString('quanthum-sso-button', $login['html']);
        $this->assertStringNotContainsString('/quanthum-sso/redirect', $login['html']);
    }

    public function test_login_page_has_no_sso_button_when_enabled_but_unconfigured(): void
    {
        config(['quanthum_sso.enabled' => true]);

        $this->assertFalse($this->loginPage()['available']);
    }

    public function test_login_page_shows_the_button_with_both_logos_when_available(): void
    {
        Fixture::configure();

        $login = $this->loginPage();

        $this->assertTrue($login['available']);

        // Na variante react (Inertia) o botão é desenhado no navegador a partir do
        // flag acima; o markup abaixo é do Blade (núcleo, mary, daisy, tall).
        if (! $login['inertia']) {
            $this->assertStringContainsString('data-test="quanthum-sso-button"', $login['html']);
            $this->assertStringContainsString('/quanthum-sso/redirect', $login['html']);
            $this->assertStringContainsString('/images/auth/quanthum-q-light.png', $login['html']);
            $this->assertStringContainsString('/images/auth/quanthum-q-dark.png', $login['html']);
            $this->assertStringContainsString('Sign in with Quanthum SSO', $login['html']);
        }
    }

    public function test_button_text_follows_the_portuguese_locale(): void
    {
        Fixture::configure();
        app()->setLocale('pt_BR');

        $login = $this->loginPage();

        if ($login['inertia']) {
            $this->assertSame('pt_BR', $login['props']['locale']);
        } else {
            $this->assertStringContainsString('Entrar com Quanthum SSO', $login['html']);
        }
    }

    public function test_local_login_keeps_working_with_sso_on(): void
    {
        Fixture::configure();
        $user = User::factory()->create(['password' => 'secret-pass-123']);

        $this->post('/login', ['email' => $user->email, 'password' => 'secret-pass-123'])->assertRedirect();

        $this->assertAuthenticatedAs($user);
    }

    public function test_redirect_is_404_when_disabled(): void
    {
        $this->get('/quanthum-sso/redirect')->assertNotFound();
        $this->get('/quanthum-sso/callback')->assertNotFound();
    }

    public function test_redirect_goes_to_the_provider_when_available(): void
    {
        Fixture::configure();
        Fixture::fake();

        $response = $this->get('/quanthum-sso/redirect');

        $response->assertRedirectContains(Fixture::ISSUER.'/oauth/authorize?');
        $this->assertStringContainsString('code_challenge_method=S256', (string) $response->headers->get('Location'));
    }

    public function test_callback_with_invalid_state_returns_to_login_without_calling_the_provider(): void
    {
        Fixture::configure();
        Fixture::fake();

        $this->get('/quanthum-sso/redirect');
        $response = $this->get('/quanthum-sso/callback?code=abc&state=forjado');

        $response->assertRedirect(route('login'))->assertSessionHas('quanthum_sso_error', 'invalid_state');
        $this->assertGuest();
        $this->assertSame(0, Fixture::sentCount(fn ($r) => str_ends_with($r->url(), '/oauth/token')));

        $login = $this->loginPage();
        $this->assertSame('invalid_state', $login['error']);
    }

    public function test_callback_without_a_started_flow_is_refused(): void
    {
        Fixture::configure();

        $this->get('/quanthum-sso/callback?code=abc&state=x')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_verified_email_links_an_existing_user_and_logs_in(): void
    {
        $user = User::factory()->create(['email' => 'ana@example.test']);

        $this->completeLogin()->assertRedirect(config('fortify.home'));

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('quanthum_sso_identities', ['user_id' => $user->id]);
    }

    public function test_known_sub_logs_in_even_if_the_provider_email_changed(): void
    {
        $user = User::factory()->create(['email' => 'ana@example.test']);
        QuanthumSsoIdentity::query()->create(['user_id' => $user->id, 'issuer' => Fixture::ISSUER, 'sub' => 'sub-123']);

        $this->completeLogin(['email' => 'outro@example.test'])->assertRedirect(config('fortify.home'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_new_sub_without_auto_provision_is_refused(): void
    {
        $this->completeLogin()->assertRedirect(route('login'))->assertSessionHas('quanthum_sso_error', 'account_not_found');

        $this->assertGuest();
        $this->assertSame(0, User::query()->count());
    }

    public function test_unverified_provider_email_is_refused(): void
    {
        User::factory()->create(['email' => 'ana@example.test']);

        $this->completeLogin(['email_verified' => false])->assertSessionHas('quanthum_sso_error', 'email_not_verified');

        $this->assertGuest();
        $this->assertSame(0, QuanthumSsoIdentity::query()->count());
    }

    public function test_unverified_local_email_is_refused(): void
    {
        User::factory()->unverified()->create(['email' => 'ana@example.test']);

        $this->completeLogin()->assertSessionHas('quanthum_sso_error', 'local_email_not_verified');

        $this->assertGuest();
    }

    public function test_privileged_accounts_are_never_linked_by_email(): void
    {
        $admin = User::factory()->create(['email' => 'ana@example.test']);
        $admin->assignRole('super_admin');

        $this->completeLogin()->assertSessionHas('quanthum_sso_error', 'admin_link_blocked');

        $this->assertGuest();
        $this->assertSame(0, QuanthumSsoIdentity::query()->count());
    }

    public function test_auto_provision_creates_a_plain_user_and_never_a_privileged_one(): void
    {
        $this->completeLogin([], ['auto_provision' => true])->assertRedirect(config('fortify.home'));

        $user = User::query()->where('email', 'ana@example.test')->firstOrFail();
        $this->assertTrue($user->hasRole('user'));
        $this->assertFalse($user->hasAnyRole(['admin', 'super_admin']));
        $this->assertDatabaseHas('quanthum_sso_identities', ['user_id' => $user->id]);
    }

    public function test_only_sub_and_issuer_are_persisted_for_the_identity(): void
    {
        $this->completeLogin([], ['auto_provision' => true]);

        $this->assertSame(
            ['id', 'user_id', 'issuer', 'sub', 'created_at', 'updated_at'],
            array_keys(QuanthumSsoIdentity::query()->firstOrFail()->getAttributes()),
        );
    }

    /**
     * Lê o /login nas duas formas do Aquiles: Blade (núcleo e variantes livewire)
     * ou página Inertia (variante react, props em data-page).
     *
     * @return array{html: string, inertia: bool, available: bool, error: ?string, props: array<string, mixed>}
     */
    private function loginPage(): array
    {
        $html = (string) $this->get('/login')->assertOk()->getContent();
        $inertia = str_contains($html, 'data-page="app"');
        $props = [];

        if ($inertia) {
            preg_match('/<script data-page="app" type="application\/json">(.*?)<\/script>/s', $html, $m);
            $props = (array) (json_decode($m[1] ?? '{}', true)['props'] ?? []);
        }

        return [
            'html' => $html,
            'inertia' => $inertia,
            'props' => $props,
            'available' => $inertia ? ($props['quanthumSsoAvailable'] ?? false) === true : str_contains($html, 'data-test="quanthum-sso-button"'),
            'error' => $inertia ? ($props['quanthumSsoError'] ?? null) : (str_contains($html, 'data-test="quanthum-sso-error"') ? 'invalid_state' : null),
        ];
    }

    /**
     * @param  array<string, mixed>  $claims
     * @param  array<string, mixed>  $configOverrides
     */
    private function completeLogin(array $claims = [], array $configOverrides = []): TestResponse
    {
        Fixture::configure($configOverrides);

        $nonce = null;

        Http::fake([
            Fixture::ISSUER.'/.well-known/openid-configuration' => Http::response(Fixture::discovery()),
            Fixture::ISSUER.'/oauth/jwks' => Http::response(Fixture::jwks()),
            Fixture::ISSUER.'/oauth/token' => function () use (&$nonce, $claims) {
                return Fixture::tokenResponse(['nonce' => $nonce, ...$claims]);
            },
        ]);

        // O fluxo começa pela rota real, para a sessão do HTTP test ter state/nonce/verifier.
        $redirect = $this->get('/quanthum-sso/redirect');
        parse_str((string) parse_url((string) $redirect->headers->get('Location'), PHP_URL_QUERY), $query);
        $nonce = $query['nonce'];

        return $this->get('/quanthum-sso/callback?'.http_build_query(['code' => 'auth-code-123', 'state' => $query['state']]));
    }
}
