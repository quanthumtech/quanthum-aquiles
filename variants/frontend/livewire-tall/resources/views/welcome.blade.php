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
    <body class="font-sans antialiased">
        <main class="mx-auto flex min-h-svh max-w-3xl flex-col justify-center gap-8 px-6 py-12">
            <div class="space-y-2">
                <p class="text-xs font-medium tracking-wide text-gray-500 uppercase">Quanthum Architecture</p>
                <h1 class="text-3xl font-semibold tracking-tight">{{ config('app.name') }}</h1>
                <p class="text-gray-600">Núcleo Aquiles com o frontend Livewire + Alpine + Tailwind (TALL puro).</p>
            </div>

            <div class="rounded-xl border border-gray-200 p-6 shadow-sm">
                <h2 class="mb-1 font-semibold">Pilares cobertos por este núcleo</h2>
                <p class="mb-4 text-sm text-gray-500">Vêm por padrão, independente do frontend escolhido.</p>
                <ul class="grid grid-cols-2 gap-2 text-sm sm:grid-cols-3">
                    <li class="flex items-center justify-between rounded-md border border-gray-200 px-3 py-2">Enterprise Foundation</li>
                    <li class="flex items-center justify-between rounded-md border border-gray-200 px-3 py-2">Security First</li>
                    <li class="flex items-center justify-between rounded-md border border-gray-200 px-3 py-2">Audit &amp; Governance</li>
                    <li class="flex items-center justify-between rounded-md border border-gray-200 px-3 py-2">Modern Frontend <span class="text-xs text-gray-400">*</span></li>
                    <li class="flex items-center justify-between rounded-md border border-gray-200 px-3 py-2">Database Layer</li>
                    <li class="flex items-center justify-between rounded-md border border-gray-200 px-3 py-2">AI Driven Development</li>
                    <li class="flex items-center justify-between rounded-md border border-gray-200 px-3 py-2">Integration Layer</li>
                    <li class="flex items-center justify-between rounded-md border border-gray-200 px-3 py-2">Cloud Ready</li>
                </ul>
            </div>

            <livewire:counter />
        </main>

        @livewireScripts
    </body>
</html>
