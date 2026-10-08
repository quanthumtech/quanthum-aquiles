<?php

namespace Tests\Feature\QuanthumCheckout;

use App\Events\QuanthumCheckoutEventReceived;
use App\Services\QuanthumCheckout\CheckoutClient;
use App\Services\QuanthumCheckout\Exceptions\CheckoutException;
use App\Services\QuanthumCheckout\WebhookSignature;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    private const SECRET = 'whsec-test-only';

    private const URL = '/api/webhooks/quanthum-checkout';

    /**
     * @return array{0: string, 1: array<string, string>}
     */
    private function signedRequest(string $secret = self::SECRET, ?int $timestamp = null): array
    {
        $body = (string) json_encode([
            'id' => 'evt_1',
            'type' => 'checkout.paid',
            'created_at' => '2026-10-06T12:00:00Z',
            'data' => ['checkout' => ['id' => 'co_1', 'amount_cents' => 4990]],
        ]);
        $ts = (string) ($timestamp ?? time());

        return [$body, [
            'X-Checkout-Timestamp' => $ts,
            'X-Checkout-Signature' => WebhookSignature::sign($body, $ts, $secret),
            'Content-Type' => 'application/json',
        ]];
    }

    public function test_webhook_without_configured_secret_is_404(): void
    {
        [$body, $headers] = $this->signedRequest();

        $this->call('POST', self::URL, [], [], [], $this->server($headers), $body)->assertNotFound();
    }

    public function test_webhook_without_signature_is_rejected(): void
    {
        config(['quanthum_checkout.webhook_secret' => self::SECRET]);
        Event::fake();

        $this->postJson(self::URL, ['id' => 'evt_1', 'type' => 'checkout.paid'])->assertStatus(401);

        Event::assertNotDispatched(QuanthumCheckoutEventReceived::class);
    }

    public function test_webhook_with_wrong_hmac_is_rejected(): void
    {
        config(['quanthum_checkout.webhook_secret' => self::SECRET]);
        Event::fake();
        [$body, $headers] = $this->signedRequest('outro-segredo');

        $this->call('POST', self::URL, [], [], [], $this->server($headers), $body)->assertStatus(401);

        Event::assertNotDispatched(QuanthumCheckoutEventReceived::class);
    }

    public function test_webhook_with_a_stale_timestamp_is_rejected(): void
    {
        config(['quanthum_checkout.webhook_secret' => self::SECRET]);
        [$body, $headers] = $this->signedRequest(timestamp: time() - 3600);

        $this->call('POST', self::URL, [], [], [], $this->server($headers), $body)->assertStatus(401);
    }

    public function test_webhook_with_a_tampered_body_is_rejected(): void
    {
        config(['quanthum_checkout.webhook_secret' => self::SECRET]);
        [$body, $headers] = $this->signedRequest();

        $this->call('POST', self::URL, [], [], [], $this->server($headers), str_replace('4990', '1', $body))->assertStatus(401);
    }

    public function test_webhook_with_a_valid_hmac_is_accepted_and_dispatches_the_event(): void
    {
        config(['quanthum_checkout.webhook_secret' => self::SECRET]);
        Event::fake();
        [$body, $headers] = $this->signedRequest();

        $this->call('POST', self::URL, [], [], [], $this->server($headers), $body)->assertOk();

        Event::assertDispatched(QuanthumCheckoutEventReceived::class, fn ($e) => $e->eventId === 'evt_1' && $e->type === 'checkout.paid');
    }

    public function test_webhook_accepts_the_previous_secret_during_rotation(): void
    {
        config(['quanthum_checkout.webhook_secret' => 'novo', 'quanthum_checkout.webhook_secret_previous' => self::SECRET]);
        [$body, $headers] = $this->signedRequest();

        $this->call('POST', self::URL, [], [], [], $this->server($headers), $body)->assertOk();
    }

    public function test_signature_header_with_two_signatures_matches_any_known_secret(): void
    {
        $ts = (string) time();
        $header = WebhookSignature::sign('{}', $ts, 'velho').','.WebhookSignature::sign('{}', $ts, self::SECRET);

        $this->assertTrue(WebhookSignature::isValid('{}', $ts, $header, [self::SECRET]));
        $this->assertFalse(WebhookSignature::isValid('{}', $ts, $header, ['nenhum-dos-dois']));
        $this->assertFalse(WebhookSignature::isValid('{}', $ts, $header, []));
    }

    public function test_client_creates_a_checkout_with_bearer_and_idempotency_key(): void
    {
        config([
            'quanthum_checkout.base_url' => 'https://checkout.example.test',
            'quanthum_checkout.client_token' => 'token-de-teste',
        ]);
        Http::fake(['checkout.example.test/*' => Http::response(['success' => true, 'data' => ['id' => 'co_1', 'status' => 'pending', 'checkout_url' => 'https://pay.example.test/co_1']], 201)]);

        $data = app(CheckoutClient::class)->create(['provider' => 'stripe', 'amount_cents' => 4990, 'currency' => 'BRL'], 'order-1');

        $this->assertSame('co_1', $data['id']);
        Http::assertSent(fn (HttpRequest $r) => $r->url() === 'https://checkout.example.test/api/v1/client/checkouts'
            && $r->header('Idempotency-Key') === ['order-1']
            && $r->header('Authorization') === ['Bearer token-de-teste']
            && $r['amount_cents'] === 4990);
    }

    public function test_client_refuses_float_amounts_and_missing_idempotency_key(): void
    {
        config(['quanthum_checkout.base_url' => 'https://checkout.example.test', 'quanthum_checkout.client_token' => 'token-de-teste']);
        Http::fake();

        foreach ([[['amount_cents' => 49.9], 'k'], [['amount_cents' => '4990'], 'k'], [['amount_cents' => 4990], '']] as [$payload, $key]) {
            try {
                app(CheckoutClient::class)->create($payload, $key);
                $this->fail('Deveria recusar.');
            } catch (CheckoutException) {
                $this->addToAssertionCount(1);
            }
        }

        Http::assertNothingSent();
    }

    public function test_client_refuses_http_base_url_and_missing_token_without_calling_the_network(): void
    {
        Http::fake();

        config(['quanthum_checkout.base_url' => 'http://checkout.example.test', 'quanthum_checkout.client_token' => 'token']);
        $this->assertThrowsNotConfigured();

        config(['quanthum_checkout.base_url' => 'https://checkout.example.test', 'quanthum_checkout.client_token' => '']);
        $this->assertThrowsNotConfigured();

        Http::assertNothingSent();
    }

    public function test_client_error_never_echoes_the_token(): void
    {
        config(['quanthum_checkout.base_url' => 'https://checkout.example.test', 'quanthum_checkout.client_token' => 'token-secreto-xyz']);
        Http::fake(['checkout.example.test/*' => Http::response(['success' => false, 'error' => ['code' => 'UNAUTHENTICATED', 'message' => 'bad token-secreto-xyz']], 401)]);

        try {
            app(CheckoutClient::class)->find('co_1');
            $this->fail('Deveria falhar.');
        } catch (CheckoutException $e) {
            $this->assertSame('UNAUTHENTICATED', $e->errorCode);
            $this->assertStringNotContainsString('token-secreto-xyz', $e->getMessage());
        }
    }

    private function assertThrowsNotConfigured(): void
    {
        try {
            app(CheckoutClient::class)->find('co_1');
            $this->fail('Deveria recusar sem configuração segura.');
        } catch (CheckoutException $e) {
            $this->assertSame('NOT_CONFIGURED', $e->errorCode);
        }
    }

    /**
     * @param  array<string, string>  $headers
     * @return array<string, string>
     */
    private function server(array $headers): array
    {
        $server = [];

        foreach ($headers as $name => $value) {
            $key = strtoupper(str_replace('-', '_', $name));
            $server[in_array($key, ['CONTENT_TYPE'], true) ? $key : 'HTTP_'.$key] = $value;
        }

        return $server;
    }
}
