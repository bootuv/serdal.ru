{{-- Отписка от рассылки по ссылке из письма (MailingTrackController). Как страницы ошибок: логотип, плитка, h1, строка, одно действие. --}}
@php
    $page = match ($state) {
        'confirm' => ['title' => 'Отписаться от рассылки?', 'text' => 'Писем от ' . \App\Support\Seo::SITE_NAME . ' на ' . $email . ' больше не будет.', 'icon' => 'mail'],
        'done' => ['title' => 'Вы отписались', 'text' => 'Писем от ' . \App\Support\Seo::SITE_NAME . ($email ? ' на ' . $email : '') . ' больше не будет.', 'icon' => 'check'],
        'test' => ['title' => 'Это пробное письмо', 'text' => 'В письмах рассылки по этой ссылке адресат отпишется от писем.', 'icon' => 'mail'],
        default => ['title' => 'Ссылка устарела', 'text' => 'Не нашли такую рассылку. Если письма продолжают приходить — ответьте на любое из них.', 'icon' => 'search'],
    };
@endphp
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $page['title'] }} — {{ \App\Support\Seo::SITE_NAME }}</title>
    @include('partials.favicon')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    @vite(['resources/css/cabinet.css'])
</head>
<body>
<div class="flex min-h-screen flex-col">
    <header class="flex items-center justify-between gap-4 p-4 lg:px-12 lg:py-8">
        <a href="{{ url('/') }}" aria-label="{{ \App\Support\Seo::SITE_NAME }} — на главную"><img src="{{ asset('images/Logo.svg') }}" alt="{{ \App\Support\Seo::SITE_NAME }}" class="h-6 w-auto"></a>
    </header>
    <main class="flex flex-1 items-center justify-center px-4 pb-12">
        <section class="flex max-w-text flex-col items-center gap-6 text-center" aria-labelledby="page-title">
            <span @class(['flex size-16 items-center justify-center rounded-lg', 'bg-ok-bg text-ok-fg' => $state === 'done', 'bg-soft' => $state !== 'done']) aria-hidden="true"><x-ui.icon :name="$page['icon']" /></span>
            <div class="flex flex-col gap-2">
                <h1 id="page-title" class="text-h1-m font-medium lg:text-h1">{{ $page['title'] }}</h1>
                <p class="text-t1 text-muted">{{ $page['text'] }}</p>
            </div>
            @if ($state === 'confirm')
                <form method="POST" action="{{ route('mailing.unsubscribe', ['token' => $token]) }}">
                    <x-ui.btn type="submit" variant="primary" size="l">Отписаться</x-ui.btn>
                </form>
            @else
                <x-ui.btn size="l" :href="url('/')">На сайт {{ \App\Support\Seo::SITE_NAME }}</x-ui.btn>
            @endif
        </section>
    </main>
</div>
</body>
</html>
