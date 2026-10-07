<?php

namespace Tests\Feature\QuanthumSso;

use App\Services\QuanthumSso\QuanthumSsoClient;
use App\Services\QuanthumSso\QuanthumSsoConfig;
use App\Services\QuanthumSso\QuanthumSsoFailure;
use Firebase\JWT\JWT;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\QuanthumSsoFixture as Fixture;
use Tests\TestCase;

class OidcClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Fixture::configure();
    }

    public function test_authorization_url_uses_pkce_s256_state_and_nonce(): void
    {
        Fixture::fake();

        $started = Fixture::startFlow();

        $this->assertSame('code', $started['query']['response_type']);
        $this->assertSame('S256', $started['query']['code_challenge_method']);
        $this->assertSame(Fixture::CLIENT_ID, $started['query']['client_id']);
        $this->assertSame(Fixture::REDIRECT_URI, $started['query']['redirect_uri']);
        $this->assertSame($started['flow']['nonce'], $started['query']['nonce']);
        $this->assertSame(
            rtrim(strtr(base64_encode(hash('sha256', $started['flow']['code_verifier'], true)), '+/', '-_'), '='),
            $started['query']['code_challenge'],
        );
        $this->assertGreaterThanOrEqual(43, strlen($started['query']['state']));
    }

    public function test_valid_id_token_completes_the_authorization(): void
    {
        $login = Fixture::prepareLogin();

        $assertion = app(QuanthumSsoClient::class)->completeAuthorization(Fixture::request($login['query']));

        $this->assertSame('sub-123', $assertion->sub);
        $this->assertSame('ana@example.test', $assertion->email);
        $this->assertTrue($assertion->emailVerified);
        $this->assertSame(Fixture::ISSUER, $assertion->issuer);

        // O code_verifier do PKCE e o client_secret (Basic) vão só ao token endpoint.
        $this->assertSame(1, Fixture::sentCount(fn (HttpRequest $r) => str_ends_with($r->url(), '/oauth/token')
            && is_string($r['code_verifier']) && strlen($r['code_verifier']) >= 43
            && $r->hasHeader('Authorization')));
    }

    public function test_wrong_state_never_reaches_the_provider(): void
    {
        $login = Fixture::prepareLogin();
        $request = Fixture::request(['code' => 'auth-code-123', 'state' => 'forged']);

        $failure = Fixture::failureOf(fn () => app(QuanthumSsoClient::class)->completeAuthorization($request));

        $this->assertSame(QuanthumSsoFailure::InvalidState, $failure);
        $this->assertSame(0, Fixture::sentCount(fn (HttpRequest $r) => str_ends_with($r->url(), '/oauth/token')));
        $this->assertNotEmpty($login['nonce']);
    }

    public function test_state_is_single_use(): void
    {
        $login = Fixture::prepareLogin();
        $client = app(QuanthumSsoClient::class);

        $client->completeAuthorization(Fixture::request($login['query']));

        $this->assertSame(
            QuanthumSsoFailure::InvalidState,
            Fixture::failureOf(fn () => $client->completeAuthorization(Fixture::request($login['query']))),
        );
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: array<string, mixed>}>
     */
    public static function invalidIdTokens(): array
    {
        return [
            'aud errado' => [['aud' => 'outro-client'], []],
            'iss errado' => [['iss' => 'https://evil.example.test'], []],
            'nonce errado' => [['nonce' => 'nonce-de-outro-fluxo'], []],
            'expirado' => [['exp' => time() - 3600, 'iat' => time() - 7200], []],
            'sem sub' => [['sub' => null], []],
            'kid desconhecido' => [[], ['kid' => 'kid-desconhecido']],
        ];
    }

    /**
     * @param  array<string, mixed>  $claims
     * @param  array<string, mixed>  $options
     */
    #[DataProvider('invalidIdTokens')]
    public function test_invalid_id_token_is_refused(array $claims, array $options): void
    {
        Fixture::key('kid-desconhecido');
        $login = Fixture::prepareLogin($claims, $options);

        $failure = Fixture::failureOf(fn () => app(QuanthumSsoClient::class)->completeAuthorization(Fixture::request($login['query'])));

        $this->assertSame(QuanthumSsoFailure::InvalidIdToken, $failure);
    }

    public function test_id_token_with_alg_none_is_refused(): void
    {
        $encode = fn (array $part) => JWT::urlsafeB64Encode((string) json_encode($part));
        $forged = function (string $nonce) use ($encode) {
            return $encode(['alg' => 'none', 'typ' => 'JWT', 'kid' => 'kid-1']).'.'
                .$encode(['iss' => Fixture::ISSUER, 'aud' => Fixture::CLIENT_ID, 'sub' => 'attacker', 'iat' => time(), 'exp' => time() + 300, 'nonce' => $nonce]).'.assinatura-qualquer';
        };

        $this->assertForgedTokenRefused($forged);
    }

    public function test_id_token_signed_with_hs256_using_the_public_key_is_refused(): void
    {
        $forged = fn (string $nonce) => JWT::encode([
            'iss' => Fixture::ISSUER, 'aud' => Fixture::CLIENT_ID, 'sub' => 'attacker',
            'iat' => time(), 'exp' => time() + 300, 'nonce' => $nonce,
        ], Fixture::publicPem(), 'HS256', 'kid-1');

        $this->assertForgedTokenRefused($forged);
    }

    public function test_insecure_issuer_is_not_available(): void
    {
        config(['quanthum_sso.issuer' => 'http://sso.example.test', 'quanthum_sso.allow_insecure_http' => false]);

        $this->assertFalse(app(QuanthumSsoConfig::class)->isAvailable());
    }

    /**
     * O token forjado tem iss/aud/exp/nonce CORRETOS: só o algoritmo o denuncia.
     *
     * @param  callable(string): string  $forge
     */
    private function assertForgedTokenRefused(callable $forge): void
    {
        $nonce = '';
        $login = Fixture::prepareLogin([], [], ['token' => function () use (&$nonce, $forge) {
            return Http::response(['id_token' => $forge($nonce)]);
        }]);
        $nonce = $login['nonce'];

        $failure = Fixture::failureOf(fn () => app(QuanthumSsoClient::class)->completeAuthorization(Fixture::request($login['query'])));

        $this->assertSame(QuanthumSsoFailure::InvalidIdToken, $failure);
    }
}
