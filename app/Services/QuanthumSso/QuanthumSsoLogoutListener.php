<?php

namespace App\Services\QuanthumSso;

use Illuminate\Auth\Events\Logout;

/**
 * O Fortify invalida a sessão ANTES de montar a resposta de logout, então o
 * id_token (necessário para o id_token_hint) é copiado para o request no
 * evento Logout, que ainda enxerga a sessão.
 */
class QuanthumSsoLogoutListener
{
    public function __construct(private readonly QuanthumSsoClient $client) {}

    public function handle(Logout $event): void
    {
        $request = request();

        if (! $request->hasSession()) {
            return;
        }

        $idToken = $this->client->idToken($request);

        if ($idToken !== null) {
            $request->attributes->set(QuanthumSsoLogoutResponse::ID_TOKEN_ATTRIBUTE, $idToken);
        }
    }
}
