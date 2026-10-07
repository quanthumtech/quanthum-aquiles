<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Entrar — {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-base-200 font-sans antialiased flex items-center justify-center px-4">
    <div class="w-full max-w-sm">
        <div class="mb-6 text-center">
            <h1 class="text-2xl font-bold">{{ config('app.name') }}</h1>
        </div>

        <x-card shadow class="bg-base-100">
            @if ($status)
                <x-alert icon="o-check-circle" class="alert-success mb-4">{{ $status }}</x-alert>
            @endif

            @if (! empty($quanthumSsoError ?? null))
                <div role="alert" data-test="quanthum-sso-error" class="alert alert-error mb-4 text-sm">
                    {{ __('quanthum_sso.error.'.\App\Services\QuanthumSso\QuanthumSsoFailure::from($quanthumSsoError)->messageKey()) }}
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}" class="flex flex-col gap-4">
                @csrf

                <x-input
                    label="E-mail"
                    name="email"
                    type="email"
                    value="{{ old('email') }}"
                    icon="o-envelope"
                    required
                    autofocus
                />

                <x-password label="Senha" name="password" icon="o-lock-closed" right required />

                <div class="flex items-center justify-between">
                    <label class="label cursor-pointer gap-2">
                        <input type="checkbox" name="remember" class="checkbox checkbox-sm" />
                        <span class="label-text">Lembrar</span>
                    </label>

                    @if (Route::has('password.request'))
                        <a href="{{ route('password.request') }}" class="text-sm link link-hover">Esqueceu a senha?</a>
                    @endif
                </div>

                <x-button label="Entrar" type="submit" class="btn-primary" icon="o-arrow-right-circle" />
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
        </x-card>
    </div>
</body>
</html>
