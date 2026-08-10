<?php

declare(strict_types=1);

namespace App\Services\AI;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Cliente único pro gateway QAI Online (formato compatível OpenAI:
 * POST /api/v1/chat/completions, GET /api/v1/models, GET /api/v1/usage).
 *
 * Consolidado a partir de três implementações quase idênticas que existiam
 * em docs-hub, kosmos-one e cgov-agreements — cada uma com uma combinação
 * diferente de retry, timeout e formato de retorno. Esta versão junta o que
 * cada uma tinha de melhor:
 * - retry em 502/503/504 (docs-hub tinha o conjunto mais completo de status;
 *   cgov-agreements não tinha retry nenhum e kosmos-one só cobria 502);
 * - assinatura stateless por parâmetro, não amarrada a um Eloquent model
 *   (kosmos-one) — quem precisa de múltiplos provedores por tenant continua
 *   livre pra ter seu próprio model (AiIntegration) por cima deste cliente;
 * - retorno normalizado {text, promptTokens, completionTokens, raw} e o
 *   endpoint de usage() (kosmos-one).
 */
class QaiOnlineService
{
    private const RETRY_STATUSES = [502, 503, 504];

    private const MAX_ATTEMPTS = 3;

    private const RETRY_DELAY_SECONDS = 3;

    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     * @return array{text: string, promptTokens: int, completionTokens: int, raw: array<string, mixed>}
     */
    public function chatCompletions(
        array $messages,
        ?string $apiUrl = null,
        ?string $apiKey = null,
        ?string $model = null,
        float $temperature = 0.7,
        int $maxTokens = 4096,
    ): array {
        $client = $this->client($apiUrl, $apiKey);

        $payload = [
            'model' => $model ?? config('qai.model'),
            'messages' => $messages,
            'temperature' => $temperature,
            'max_tokens' => $maxTokens,
        ];

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            $response = $client->post('/api/v1/chat/completions', $payload);

            if ($response->successful()) {
                $data = $response->json() ?? [];

                return [
                    'text' => (string) ($data['choices'][0]['message']['content'] ?? ''),
                    'promptTokens' => (int) ($data['usage']['prompt_tokens'] ?? 0),
                    'completionTokens' => (int) ($data['usage']['completion_tokens'] ?? 0),
                    'raw' => $data,
                ];
            }

            $isLastAttempt = $attempt === self::MAX_ATTEMPTS;
            $isRetryable = in_array($response->status(), self::RETRY_STATUSES, true);

            if (! $isRetryable || $isLastAttempt) {
                throw new RuntimeException('QAI Online Chat Error: '.$response->status().' - '.$response->body());
            }

            sleep(self::RETRY_DELAY_SECONDS);
        }

        throw new RuntimeException('QAI Online Chat Error: unreachable');
    }

    /** @return array<string, string> id => label */
    public function listModels(?string $apiUrl = null, ?string $apiKey = null): array
    {
        $response = $this->client($apiUrl, $apiKey)->get('/api/v1/models');

        if (! $response->successful()) {
            throw new RuntimeException('QAI Online Models Error: '.$response->status().' - '.$response->body());
        }

        $data = $response->json() ?? [];
        $items = $data['data'] ?? $data;

        if (! is_array($items)) {
            return [];
        }

        $models = [];
        foreach ($items as $model) {
            if (! is_array($model)) {
                continue;
            }

            $id = $model['id'] ?? $model['name'] ?? null;

            if (is_string($id)) {
                $models[$id] = $model['name'] ?? $id;
            }
        }

        return $models;
    }

    /** @return array<string, mixed> */
    public function usage(?string $apiUrl = null, ?string $apiKey = null): array
    {
        $response = $this->client($apiUrl, $apiKey)->get('/api/v1/usage');

        return $response->successful() ? ($response->json() ?? []) : [];
    }

    private function client(?string $apiUrl, ?string $apiKey): PendingRequest
    {
        $resolvedUrl = $apiUrl ?? config('qai.url');
        $resolvedKey = $apiKey ?? config('qai.key');

        if (blank($resolvedUrl)) {
            throw new RuntimeException(
                'QAI Online: URL não configurada. Defina QAI_ONLINE_URL no .env ou passe $apiUrl explicitamente.',
            );
        }

        return Http::withHeaders([
            'Authorization' => 'Bearer '.$resolvedKey,
            'Content-Type' => 'application/json',
        ])->baseUrl(rtrim($resolvedUrl, '/'))->timeout((int) config('qai.timeout'));
    }
}
