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
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\LogoutResponse;
use Laravel\Fortify\Fortify;

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

        // quanthumSsoAvailable/quanthumSsoError: o botão "Entrar com Quanthum SSO" só
        // aparece com o SSO ligado E configurado (QuanthumSsoConfig::isAvailable()).
        Fortify::loginView(fn (Request $request) => View::make('auth.login', [
            'status' => $request->session()->get('status'),
            'quanthumSsoAvailable' => app(QuanthumSsoConfig::class)->isAvailable(),
            'quanthumSsoError' => QuanthumSsoFailure::tryFrom((string) $request->session()->get('quanthum_sso_error'))?->value,
        ]));

        Fortify::requestPasswordResetLinkView(fn (Request $request) => View::make('auth.forgot-password', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::resetPasswordView(fn (Request $request) => View::make('auth.reset-password', [
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
