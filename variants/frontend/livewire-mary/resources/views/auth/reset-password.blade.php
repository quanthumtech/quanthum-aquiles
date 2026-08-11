<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nova senha — {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-base-200 font-sans antialiased flex items-center justify-center px-4">
    <div class="w-full max-w-sm">
        <div class="mb-6 text-center">
            <h1 class="text-2xl font-bold">{{ config('app.name') }}</h1>
            <p class="text-sm text-base-content/60">Defina uma nova senha</p>
        </div>

        <x-card shadow class="bg-base-100">
            <form method="POST" action="{{ route('password.update') }}" class="flex flex-col gap-4">
                @csrf

                <input type="hidden" name="token" value="{{ $token }}">

                <x-input label="E-mail" name="email" type="email" value="{{ old('email', $email) }}" icon="o-envelope" required autofocus />
                <x-password label="Nova senha" name="password" icon="o-lock-closed" right required />
                <x-password label="Confirme a nova senha" name="password_confirmation" icon="o-lock-closed" right required />

                <x-button label="Trocar senha" type="submit" class="btn-primary" />
            </form>
        </x-card>
    </div>
</body>
</html>
