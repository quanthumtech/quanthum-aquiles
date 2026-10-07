<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Entrar — {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-svh flex items-center justify-center bg-white px-4 font-sans antialiased">
    <div class="w-full max-w-sm">
        <div class="mb-6 text-center">
            <h1 class="text-2xl font-semibold tracking-tight">{{ config('app.name') }}</h1>
        </div>

        <div class="rounded-xl border border-gray-200 p-6 shadow-sm">
            @if ($status)
                <div class="mb-4 rounded-md bg-green-50 px-3 py-2 text-sm text-green-700">{{ $status }}</div>
            @endif

            @if (! empty($quanthumSsoError ?? null))
                <div role="alert" data-test="quanthum-sso-error" class="mb-4 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">
                    {{ __('quanthum_sso.error.'.\App\Services\QuanthumSso\QuanthumSsoFailure::from($quanthumSsoError)->messageKey()) }}
                </div>
            @endif

            @if ($errors->any())
                <div class="mb-4 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}" class="flex flex-col gap-4">
                @csrf

                <div>
                    <label for="email" class="mb-1 block text-sm font-medium">E-mail</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus
                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-gray-500 focus:outline-none" />
                </div>

                <div>
                    <label for="password" class="mb-1 block text-sm font-medium">Senha</label>
                    <input id="password" name="password" type="password" required
                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-gray-500 focus:outline-none" />
                </div>

                <div class="flex items-center justify-between text-sm">
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="remember" class="rounded border-gray-300" />
                        Lembrar
                    </label>

                    @if (Route::has('password.request'))
                        <a href="{{ route('password.request') }}" class="text-gray-600 hover:underline">Esqueceu a senha?</a>
                    @endif
                </div>

                <button type="submit" class="w-full rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700">
                    Entrar
                </button>
                @if ($quanthumSsoAvailable ?? false)
                    <div class="my-1 flex items-center gap-3 text-xs text-gray-500" aria-hidden="true">
                        <span class="h-px flex-1 bg-gray-200"></span>
                        <span>{{ __('quanthum_sso.or') }}</span>
                        <span class="h-px flex-1 bg-gray-200"></span>
                    </div>

                    <a href="{{ route('quanthum-sso.redirect') }}" data-test="quanthum-sso-button"
                        class="flex w-full items-center justify-center gap-2 rounded-md border border-gray-300 px-4 py-2 text-sm font-medium hover:bg-gray-50">
                        <img src="/images/auth/quanthum-q-light.png" alt="" width="20" height="20" aria-hidden="true" class="size-5 dark:hidden" />
                        <img src="/images/auth/quanthum-q-dark.png" alt="" width="20" height="20" aria-hidden="true" class="hidden size-5 dark:block" />
                        {{ __('quanthum_sso.button') }}
                    </a>
                @endif
            </form>
        </div>
    </div>
</body>
</html>
