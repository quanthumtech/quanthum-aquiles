<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('welcome', [
        'appName' => config('app.name'),
        'pillars' => [
            ['label' => 'Enterprise Foundation', 'status' => 'core'],
            ['label' => 'Security First', 'status' => 'core'],
            ['label' => 'Audit & Governance', 'status' => 'core'],
            ['label' => 'Modern Frontend', 'status' => 'flag'],
            ['label' => 'Database Layer', 'status' => 'core'],
            ['label' => 'AI Driven Development', 'status' => 'core'],
            ['label' => 'Integration Layer', 'status' => 'core'],
            ['label' => 'Cloud Ready', 'status' => 'core'],
        ],
    ]);
});
