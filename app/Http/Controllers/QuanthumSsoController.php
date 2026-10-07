<?php

namespace App\Http\Controllers;

use App\Services\QuanthumSso\Exceptions\QuanthumSsoException;
use App\Services\QuanthumSso\QuanthumSsoClient;
use App\Services\QuanthumSso\QuanthumSsoConfig;
use App\Services\QuanthumSso\QuanthumSsoFailure;
use App\Services\QuanthumSso\QuanthumSsoUserResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class QuanthumSsoController extends Controller
{
    public function __construct(private readonly QuanthumSsoConfig $config) {}

    /**
     * Inicia o Authorization Code + PKCE no provedor.
     */
    public function redirect(Request $request, QuanthumSsoClient $client): RedirectResponse
    {
        abort_unless($this->config->isAvailable(), 404);

        try {
            return redirect()->away($client->authorizationUrl($request));
        } catch (QuanthumSsoException $e) {
            return $this->refuse($e->failure);
        }
    }

    /**
     * Recebe o code, valida o id_token e abre a sessão local. Toda falha volta
     * ao login com um código estável: nunca redireciona de volta ao provedor
     * (sem loop) e o destino do sucesso é fixo, nunca vem do request. O
     * Auth::login já troca o id da sessão (session fixation).
     */
    public function callback(Request $request, QuanthumSsoClient $client, QuanthumSsoUserResolver $resolver): RedirectResponse
    {
        abort_unless($this->config->isAvailable(), 404);

        try {
            $assertion = $client->completeAuthorization($request);
            $user = $resolver->resolve($assertion);
        } catch (QuanthumSsoException $e) {
            return $this->refuse($e->failure);
        }

        Auth::login($user);
        $client->rememberIdToken($request, $assertion->idToken);

        Log::info('Quanthum SSO login', [
            'event' => 'login',
            'user_id' => $user->id,
            'issuer' => $assertion->issuer,
        ]);

        return redirect(config('fortify.home'));
    }

    private function refuse(QuanthumSsoFailure $failure): RedirectResponse
    {
        Log::warning('Quanthum SSO login refused', ['reason' => $failure->value]);

        return redirect()->route('login')->with('quanthum_sso_error', $failure->value);
    }
}
