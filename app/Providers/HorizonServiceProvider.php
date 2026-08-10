<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        // Horizon::routeSmsNotificationsTo('15556667777');
        // Horizon::routeMailNotificationsTo('example@example.com');
        // Horizon::routeSlackNotificationsTo('slack-webhook-url', '#channel');
    }

    /**
     * Register the Horizon gate.
     *
     * This gate determines who can access Horizon in non-local environments.
     * Usa RBAC (spatie/laravel-permission) em vez de allowlist de e-mail — o
     * pilar de Segurança/RBAC do núcleo já resolve isso.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', function ($user = null) {
            return (bool) $user?->hasRole('super_admin');
        });
    }
}
