<?php

use App\Http\Middleware\EnsureFeatureIsLicensed;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        // As rotas do SSO Quanthum ficam aqui (e não em routes/web.php) de propósito: o
        // `mary:install` reescreve routes/web.php inteiro e levaria o /quanthum-sso junto.
        then: function (): void {
            Route::middleware('web')->group(base_path('routes/quanthum-sso.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Dokploy/Traefik (e qualquer proxy reverso equivalente) termina TLS e repassa em
        // HTTP puro — sem confiar nele, Request::isSecure() fica falso e assets/redirects saem
        // como http:// numa página https://, o navegador bloqueia como mixed content.
        $middleware->trustProxies(at: '*');

        // spatie/laravel-permission não se auto-registra em bootstrap/app.php (Laravel 11+) —
        // sem isso, ->middleware('role:xxx') estoura "Target class [role] does not exist.".
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            // Licença do produto (Quanthum Licenses): `licensed:<modulo>`. Inerte com
            // QUANTHUM_LICENSE_ENFORCE=false (padrão).
            'licensed' => EnsureFeatureIsLicensed::class,
        ]);

        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
