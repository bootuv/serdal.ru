<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-50 dark:bg-gray-900">
    <div class="flex min-h-screen items-center justify-center px-4 py-12 sm:px-6 lg:px-8">
        <div class="w-full max-w-md space-y-8">
            <div class="text-center">
                <img src="{{ asset('images/Logo.svg') }}" alt="Logo" class="mx-auto h-12 w-auto dark:invert">
                <h2 class="mt-6 text-3xl font-bold tracking-tight text-gray-900 dark:text-white">
                    Вход в систему
                </h2>
            </div>

            <form class="mt-8 space-y-6" action="{{ route('login.post') }}" method="POST">
                @csrf

                @if (session('status'))
                    <div class="rounded-md bg-green-50 p-4">
                        <div class="text-sm text-green-800">
                            {{ session('status') }}
                        </div>
                    </div>
                @endif

                @if ($errors->any())
                    <div class="rounded-md bg-red-50 p-4">
                        <div class="text-sm text-red-800">
                            @foreach ($errors->all() as $error)
                                <p>{{ $error }}</p>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="space-y-4 rounded-md shadow-sm">
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Email
                        </label>
                        <input id="email" name="email" type="email" autocomplete="email" required
                            value="{{ old('email') }}"
                            class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-gray-900 placeholder-gray-400 focus:border-amber-500 focus:outline-none focus:ring-amber-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white sm:text-sm"
                            placeholder="your@email.com">
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Пароль
                        </label>
                        <input id="password" name="password" type="password" autocomplete="current-password" required
                            class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-gray-900 placeholder-gray-400 focus:border-amber-500 focus:outline-none focus:ring-amber-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white sm:text-sm"
                            placeholder="••••••••">
                    </div>
                </div>

                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <input id="remember" name="remember" type="checkbox"
                            class="h-4 w-4 rounded border-gray-300 text-amber-600 focus:ring-amber-500">
                        <label for="remember" class="ml-2 block text-sm text-gray-900 dark:text-gray-300">
                            Запомнить меня
                        </label>
                    </div>

                    <div class="text-sm">
                        <a href="{{ route('password.request') }}"
                            class="font-medium text-amber-600 hover:text-amber-500">
                            Забыли пароль?
                        </a>
                    </div>
                </div>

                <div>
                    <button type="submit"
                        class="group relative flex w-full justify-center rounded-md bg-amber-600 px-3 py-2 text-sm font-semibold text-white hover:bg-amber-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-600">
                        Войти
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>

</html>