<?php

use App\Http\Controllers\QuanthumSsoController;
use Illuminate\Support\Facades\Route;

Route::middleware(['guest', 'throttle:20,1'])
    ->prefix('quanthum-sso')
    ->name('quanthum-sso.')
    ->group(function () {
        Route::get('redirect', [QuanthumSsoController::class, 'redirect'])->name('redirect');
        Route::get('callback', [QuanthumSsoController::class, 'callback'])->name('callback');
    });
