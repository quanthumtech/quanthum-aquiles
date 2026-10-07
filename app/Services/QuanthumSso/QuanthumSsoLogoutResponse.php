<?php

namespace App\Services\QuanthumSso;

use App\Services\QuanthumSso\Exceptions\QuanthumSsoException;
use Illuminate\Support\Facades\Log;
use Laravel\Fortify\Contracts\LogoutResponse;
use Laravel\Fortify\Http\Responses\LogoutResponse as FortifyLogoutResponse;

/**
 * Logout local sempre acontece. Só quando a sessão era do SSO (id_token
 * presente) e o provedor anuncia o end_session_endpoint, o navegador segue
 * para lá com id_token_hint e a post-logout URI EXATA da configuração. Sem
 * id_token, ou se o discovery falhar, fica só o logout local: nenhuma URL é
 * montada a partir do request.
 *
 * Em requests Inertia (variante react) o redirecionamento externo vai pelo
 * protocolo do Inertia (409 + X-Inertia-Location), sem depender do pacote.
 */
class QuanthumSsoLogoutResponse implements LogoutResponse
{
    public const ID_TOKEN_ATTRIBUTE = 'quanthum_sso.id_token';

    public function toResponse($request)
    {
        $default = (new FortifyLogoutResponse)->toResponse($request);
        $idToken = $request->attributes->get(self::ID_TOKEN_ATTRIBUTE);

        if (! is_string($idToken) || $idToken === '') {
            return $default;
        }

        $isInertia = $request->header('X-Inertia') !== null;

        if ($request->wantsJson() && ! $isInertia) {
            return $default;
        }

        $client = app(QuanthumSsoClient::class);

        if (! $client->idTokenIsUsableForLogout($idToken)) {
            Log::info('Quanthum SSO logout kept local: the stored id_token is expired', [
                'event' => 'logout_local_expired_id_token',
            ]);

            return $default;
        }

        try {
            $url = $client->endSessionUrl($idToken);
        } catch (QuanthumSsoException $e) {
            Log::warning('Quanthum SSO logout kept local only', ['reason' => $e->failure->value]);

            return $default;
        }

        if ($url === null) {
            return $default;
        }

        return $isInertia
            ? response('', 409)->header('X-Inertia-Location', $url)
            : redirect()->away($url);
    }
}
