<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name', 'Laravel') }}</title>

        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body class="bg-base-200 font-sans antialiased">
        <main class="mx-auto flex min-h-svh max-w-3xl flex-col justify-center gap-8 px-6 py-12">
            <div class="space-y-2">
                <p class="text-xs font-medium tracking-wide text-base-content/60 uppercase">Quanthum Architecture</p>
                <h1 class="text-3xl font-semibold tracking-tight">{{ config('app.name') }}</h1>
                <p class="text-base-content/70">Núcleo Aquiles com o frontend Livewire + DaisyUI.</p>
            </div>

            <div class="card bg-base-100 shadow-sm">
                <div class="card-body">
                    <h2 class="card-title">Pilares cobertos por este núcleo</h2>
                    <p class="text-sm text-base-content/60">Vêm por padrão, independente do frontend escolhido.</p>
                    <ul class="mt-2 grid grid-cols-2 gap-2 text-sm sm:grid-cols-3">
                        <li class="rounded-box border border-base-300 px-3 py-2">Enterprise Foundation</li>
                        <li class="rounded-box border border-base-300 px-3 py-2">Security First</li>
                        <li class="rounded-box border border-base-300 px-3 py-2">Audit &amp; Governance</li>
                        <li class="rounded-box border border-base-300 px-3 py-2">Modern Frontend <span class="text-xs opacity-50">*</span></li>
                        <li class="rounded-box border border-base-300 px-3 py-2">Database Layer</li>
                        <li class="rounded-box border border-base-300 px-3 py-2">AI Driven Development</li>
                        <li class="rounded-box border border-base-300 px-3 py-2">Integration Layer</li>
                        <li class="rounded-box border border-base-300 px-3 py-2">Cloud Ready</li>
                    </ul>
                </div>
            </div>

            <livewire:counter />
        </main>

        @livewireScripts
    </body>
</html>
