{{-- Отдельная страница без меню кабинета (сбор основателя — у него может быть профиль админа или учителя): логотип сверху, колонка по центру.
     Поисковикам закрыта. Правила — docs/design/BRAND.md. --}}
@props(['title' => null])
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="no-referrer">
    <title>{{ $title ? $title . ' — ' : '' }}{{ \App\Support\Seo::SITE_NAME }}</title>
    @include('partials.favicon')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    @vite(['resources/css/cabinet.css'])
</head>
<body>
<div class="flex min-h-screen flex-col">
    <header class="mx-auto flex w-full max-w-form items-center p-4 lg:px-0 lg:py-8">
        <img src="{{ asset('images/Logo.svg') }}" alt="Serdal" class="h-6 w-auto">
    </header>
    <main class="mx-auto w-full max-w-form flex-1 px-4 pb-12 lg:px-0">
        {{ $slot }}
    </main>
</div>
<x-ui.toast />
</body>
</html>
