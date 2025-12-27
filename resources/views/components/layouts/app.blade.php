<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-gray-50 dark:bg-gray-900">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Регистрация' }} - {{ config('app.name') }}</title>
    <link href="{{ asset('images/favicon.ico') }}" rel="shortcut icon" type="image/x-icon">

    <style>
        [x-cloak] {
            display: none !important;
        }
    </style>

    <style>
        [x-cloak] {
            display: none !important;
        }
    </style>

    @filamentStyles
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        danger: '#ef4444',
                        success: '#10b981',
                        warning: '#f59e0b',
                        primary: '#6366f1',
                    }
                }
            }
        }
    </script>
</head>

<body class="h-full font-sans antialiased text-gray-900 dark:text-gray-100">
    {{ $slot }}

    @filamentScripts
    @vite('resources/js/app.js')
</body>

</html>