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

                @if (! empty($quanthumSsoError ?? null))
                    <div role="alert" data-test="quanthum-sso-error" class="alert alert-error mb-2 text-sm">
                        {{ __('quanthum_sso.error.'.\App\Services\QuanthumSso\QuanthumSsoFailure::from($quanthumSsoError)->messageKey()) }}
                    </div>
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
                    @if ($quanthumSsoAvailable ?? false)
                        <div class="divider my-0 text-xs">{{ __('quanthum_sso.or') }}</div>

                        <a href="{{ route('quanthum-sso.redirect') }}" data-test="quanthum-sso-button"
                            class="btn btn-outline w-full">
                            <img src="/images/auth/quanthum-q-light.png" alt="" width="20" height="20" aria-hidden="true" class="size-5 dark:hidden" />
                            <img src="/images/auth/quanthum-q-dark.png" alt="" width="20" height="20" aria-hidden="true" class="hidden size-5 dark:block" />
                            {{ __('quanthum_sso.button') }}
                        </a>
                    @endif
                </form>
            </div>
        </div>
    </div>
</body>
</html>
