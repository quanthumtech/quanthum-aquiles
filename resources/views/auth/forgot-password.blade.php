<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar senha — {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-svh flex items-center justify-center bg-white px-4 font-sans antialiased">
    <div class="w-full max-w-sm">
        <div class="mb-6 text-center">
            <h1 class="text-2xl font-semibold tracking-tight">{{ config('app.name') }}</h1>
            <p class="text-sm text-gray-500">Esqueceu sua senha? Sem problema.</p>
        </div>

        <div class="rounded-xl border border-gray-200 p-6 shadow-sm">
            @if ($status)
                <div class="mb-4 rounded-md bg-green-50 px-3 py-2 text-sm text-green-700">{{ $status }}</div>
            @endif

            @if ($errors->any())
                <div class="mb-4 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('password.email') }}" class="flex flex-col gap-4">
                @csrf

                <div>
                    <label for="email" class="mb-1 block text-sm font-medium">E-mail</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus
                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-gray-500 focus:outline-none" />
                </div>

                <button type="submit" class="w-full rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700">
                    Enviar link de recuperação
                </button>

                <a href="{{ route('login') }}" class="text-center text-sm text-gray-600 hover:underline">Voltar pro login</a>
            </form>
        </div>
    </div>
</body>
</html>
