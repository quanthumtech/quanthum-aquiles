<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nova senha — {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-svh flex items-center justify-center bg-base-200 px-4 font-sans antialiased">
    <div class="w-full max-w-sm">
        <div class="mb-6 text-center">
            <h1 class="text-2xl font-semibold tracking-tight">{{ config('app.name') }}</h1>
            <p class="text-base-content/60 text-sm">Defina uma nova senha</p>
        </div>

        <div class="card bg-base-100 shadow-sm">
            <div class="card-body">
                @if ($errors->any())
                    <div role="alert" class="alert alert-error mb-2 text-sm">{{ $errors->first() }}</div>
                @endif

                <form method="POST" action="{{ route('password.update') }}" class="flex flex-col gap-4">
                    @csrf

                    <input type="hidden" name="token" value="{{ $token }}">

                    <label class="fieldset">
                        <span class="fieldset-legend">E-mail</span>
                        <input name="email" type="email" value="{{ old('email', $email) }}" required autofocus class="input w-full" />
                    </label>

                    <label class="fieldset">
                        <span class="fieldset-legend">Nova senha</span>
                        <input name="password" type="password" required class="input w-full" />
                    </label>

                    <label class="fieldset">
                        <span class="fieldset-legend">Confirme a nova senha</span>
                        <input name="password_confirmation" type="password" required class="input w-full" />
                    </label>

                    <button type="submit" class="btn btn-primary w-full">Trocar senha</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
