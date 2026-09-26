{{-- Страницы ошибок (макеты Sy404, SyError, SySession): логотип и «Написать в поддержку» сверху, по центру — плитка, h1, строка, действие.
     Без обращений к базе, кроме безопасной попытки узнать пользователя: страница 500 должна открываться, даже когда база недоступна. --}}
@php
    try {
        $user = auth()->user();
    } catch (\Throwable $e) {
        $user = null;
    }
    $home = $user ? \App\Http\Middleware\EnsureCabinetRole::homeFor($user) : url('/');
    $homeLabel = $user ? 'Вернуться в кабинет' : 'На главную';
    $supportUrl = $user ? \App\Services\MessengerService::url($user, support: true) : 'mailto:info@serdal.ru';
@endphp
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $title }} — {{ \App\Support\Seo::SITE_NAME }}</title>
    @include('partials.favicon')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    @vite(['resources/css/cabinet.css'])
</head>
<body>
<div class="flex min-h-screen flex-col">
    <header class="flex items-center justify-between gap-4 p-4 lg:px-12 lg:py-8">
        <a href="{{ $home }}" aria-label="Serdal — {{ mb_strtolower($homeLabel) }}"><img src="{{ asset('images/Logo.svg') }}" alt="Serdal" class="h-6 w-auto"></a>
        <a href="{{ $supportUrl }}" class="link text-t2">Написать в поддержку</a>
    </header>
    <main class="flex flex-1 items-center justify-center px-4 pb-12">
        <section class="flex max-w-text flex-col items-center gap-6 text-center" aria-labelledby="error-title">
            <span class="flex size-16 items-center justify-center rounded-lg bg-soft" aria-hidden="true"><x-ui.icon :name="$icon" /></span>
            <div class="flex flex-col gap-2">
                <h1 id="error-title" class="text-h1-m font-medium lg:text-h1">{{ $title }}</h1>
                <p class="text-t1 text-muted">{{ $text }}</p>
            </div>
            @if ($reload ?? false)
                <div class="flex flex-col items-center gap-4">
                    <button type="button" onclick="window.location.reload()" class="inline-flex h-13 items-center justify-center gap-2 rounded bg-brand px-6 text-t1 font-medium text-ink hover:bg-brand-hover"><x-ui.icon name="repeat" />Обновить страницу</button>
                    <a href="{{ $home }}" class="link text-t1-s">{{ $homeLabel }}</a>
                </div>
            @else
                <x-ui.btn variant="primary" size="l" :href="$home">{{ $homeLabel }}</x-ui.btn>
            @endif
        </section>
    </main>
</div>
</body>
</html>
