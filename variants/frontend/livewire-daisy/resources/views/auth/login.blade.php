<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Entrar — {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-svh flex items-center justify-center bg-base-200 px-4 font-sans antialiased">
    <div class="w-full max-w-sm">
        <div class="mb-6 text-center">
            <h1 class="text-2xl font-semibold tracking-tight">{{ config('app.name') }}</h1>
        </div>

        <div class="card bg-base-100 shadow-sm">
            <div class="card-body">
                @if ($status)
                    <div role="alert" class="alert alert-success mb-2 text-sm">{{ $status }}</div>
                @endif

                @if ($errors->any())
                    <div role="alert" class="alert alert-error mb-2 text-sm">{{ $errors->first() }}</div>
                @endif

                <form method="POST" action="{{ route('login') }}" class="flex flex-col gap-4">
                    @csrf

                    <label class="fieldset">
                        <span class="fieldset-legend">E-mail</span>
                        <input name="email" type="email" value="{{ old('email') }}" required autofocus class="input w-full" />
                    </label>

                    <label class="fieldset">
                        <span class="fieldset-legend">Senha</span>
                        <input name="password" type="password" required class="input w-full" />
                    </label>

                    <div class="flex items-center justify-between text-sm">
                        <label class="label cursor-pointer gap-2">
                            <input type="checkbox" name="remember" class="checkbox checkbox-sm" />
                            Lembrar
                        </label>

                        @if (Route::has('password.request'))
                            <a href="{{ route('password.request') }}" class="link link-hover">Esqueceu a senha?</a>
                        @endif
                    </div>

                    <button type="submit" class="btn btn-primary w-full">Entrar</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
