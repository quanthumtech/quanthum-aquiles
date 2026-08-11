<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar senha — {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-base-200 font-sans antialiased flex items-center justify-center px-4">
    <div class="w-full max-w-sm">
        <div class="mb-6 text-center">
            <h1 class="text-2xl font-bold">{{ config('app.name') }}</h1>
            <p class="text-sm text-base-content/60">Esqueceu sua senha? Sem problema.</p>
        </div>

        <x-card shadow class="bg-base-100">
            @if ($status)
                <x-alert icon="o-check-circle" class="alert-success mb-4">{{ $status }}</x-alert>
            @endif

            <form method="POST" action="{{ route('password.email') }}" class="flex flex-col gap-4">
                @csrf

                <x-input label="E-mail" name="email" type="email" value="{{ old('email') }}" icon="o-envelope" required autofocus />

                <x-button label="Enviar link de recuperação" type="submit" class="btn-primary" />

                <a href="{{ route('login') }}" class="text-sm link link-hover text-center">Voltar pro login</a>
            </form>
        </x-card>
    </div>
</body>
</html>
