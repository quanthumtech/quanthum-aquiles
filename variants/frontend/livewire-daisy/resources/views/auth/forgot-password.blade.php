<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar senha — {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-svh flex items-center justify-center bg-base-200 px-4 font-sans antialiased">
    <div class="w-full max-w-sm">
        <div class="mb-6 text-center">
            <h1 class="text-2xl font-semibold tracking-tight">{{ config('app.name') }}</h1>
            <p class="text-base-content/60 text-sm">Esqueceu sua senha? Sem problema.</p>
        </div>

        <div class="card bg-base-100 shadow-sm">
            <div class="card-body">
                @if ($status)
                    <div role="alert" class="alert alert-success mb-2 text-sm">{{ $status }}</div>
                @endif

                @if ($errors->any())
                    <div role="alert" class="alert alert-error mb-2 text-sm">{{ $errors->first() }}</div>
                @endif

                <form method="POST" action="{{ route('password.email') }}" class="flex flex-col gap-4">
                    @csrf

                    <label class="fieldset">
                        <span class="fieldset-legend">E-mail</span>
                        <input name="email" type="email" value="{{ old('email') }}" required autofocus class="input w-full" />
                    </label>

                    <button type="submit" class="btn btn-primary w-full">Enviar link de recuperação</button>

                    <a href="{{ route('login') }}" class="link link-hover text-center text-sm">Voltar pro login</a>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
