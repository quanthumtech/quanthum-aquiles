<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Services\QuanthumSso\QuanthumSsoConfig;
use App\Services\QuanthumSso\QuanthumSsoFailure;
use App\Services\QuanthumSso\QuanthumSsoLogoutListener;
use App\Services\QuanthumSso\QuanthumSsoLogoutResponse;
use Illuminate\Auth\Events\Logout;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\LogoutResponse;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;

/**
 * Mesma configuração do FortifyServiceProvider do núcleo, mas renderizando
 * via Inertia (resources/js/pages/auth/*.tsx) em vez de View::make — o
 * variant "react" não usa Blade pra nada além do casco em app.blade.php.
 */
class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Logout RP-initiated do Quanthum SSO (só age se a sessão veio do SSO).
        $this->app->singleton(LogoutResponse::class, QuanthumSsoLogoutResponse::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        Event::listen(Logout::class, QuanthumSsoLogoutListener::class);

        Fortify::loginView(fn (Request $request) => Inertia::render('auth/login', [
            'status' => $request->session()->get('status'),
            'locale' => app()->getLocale(),
            'quanthumSsoAvailable' => app(QuanthumSsoConfig::class)->isAvailable(),
            'quanthumSsoError' => QuanthumSsoFailure::tryFrom((string) $request->session()->get('quanthum_sso_error'))?->value,
            'canResetPassword' => Features::enabled(Features::resetPasswords()),
            'canRegister' => Features::enabled(Features::registration()) && Route::has('register'),
        ]));

        if (Features::enabled(Features::registration())) {
            Fortify::registerView(fn () => Inertia::render('auth/register'));
        }

        Fortify::requestPasswordResetLinkView(fn (Request $request) => Inertia::render('auth/forgot-password', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::resetPasswordView(fn (Request $request) => Inertia::render('auth/reset-password', [
            'email' => $request->email,
            'token' => $request->route('token'),
        ]));

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });
    }
}
