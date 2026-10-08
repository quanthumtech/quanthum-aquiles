<?php

namespace App\Http\Middleware;

use App\Services\QuanthumLicense\LicenseManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Libera uma rota só se o módulo estiver habilitado na licença do PRÓPRIO
 * produto (Quanthum Licenses). Com QUANTHUM_LICENSE_ENFORCE=false (padrão) não
 * faz nada. Lê apenas o estado local (LicenseManager::can confere assinatura,
 * expires_at e grace do cache): nenhuma chamada de rede por request, então uma
 * queda do servidor de licenças com cache válido nunca derruba a rota, e um
 * scheduler parado não mantém uma licença vencida liberando o módulo.
 */
class EnsureFeatureIsLicensed
{
    public function __construct(private readonly LicenseManager $licenseManager) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $moduleKey): Response
    {
        if (! config('quanthum_license.enforce')) {
            return $next($request);
        }

        if (! $this->licenseManager->can($moduleKey)) {
            abort(403, $this->forbiddenMessage($request));
        }

        return $next($request);
    }

    private function forbiddenMessage(Request $request): string
    {
        $messages = (array) config('quanthum_license.forbidden_messages');
        $language = $request->getPreferredLanguage(array_keys($messages)) ?? array_key_first($messages);

        return (string) ($messages[$language] ?? reset($messages));
    }
}
