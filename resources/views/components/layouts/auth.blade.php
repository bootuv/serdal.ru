{{-- Экраны без сайдбара: вход, восстановление пароля, регистрация по приглашению, заявка учителя.
     Экран делится пополам: слева логотип, форма и подвал в одной колонке 440 по центру; справа — фото (на телефоне — полоса фото над формой). Правила — docs/design/BRAND.md.
     index — страницу можно индексировать (публичная заявка учителя). --}}
@props(['title' => null, 'index' => false])
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @unless ($index)<meta name="robots" content="noindex">@endunless
    <title>{{ $title ? $title . ' — ' : '' }}{{ \App\Support\Seo::SITE_NAME }}</title>
    @include('partials.favicon')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    @vite(['resources/css/cabinet.css'])
</head>
<body>
<div class="flex min-h-screen">
    {{-- Левая половина: логотип, форма и подвал в одной колонке 440 — края совпадают --}}
    <div class="flex min-w-0 flex-1 flex-col p-4 lg:p-12">
        <div class="mx-auto flex w-full flex-1 flex-col gap-6 lg:max-w-auth-form lg:gap-8">
            <a href="{{ url('/') }}" class="self-start"><img src="{{ asset('images/Logo.svg') }}" alt="{{ \App\Support\Seo::SITE_NAME }}" class="h-6 w-auto"></a>
            <img src="{{ asset('images/bg-p-800.jpg') }}" alt="Учительница ведёт онлайн-занятие на Serdal" class="h-40 w-full rounded object-cover lg:hidden">

            <main class="flex flex-1 flex-col justify-center gap-8">
                {{ $slot }}
            </main>

            <footer class="flex flex-col gap-2 text-t2 text-muted lg:flex-row lg:flex-wrap lg:items-center lg:justify-between lg:gap-4">
                <span>Есть вопросы? <a href="mailto:info@serdal.ru" class="underline underline-offset-4 decoration-line-strong hover:text-ink">info@serdal.ru</a></span>
                <span class="flex gap-4">
                    <a href="{{ route('terms') }}" class="underline underline-offset-4 decoration-line-strong hover:text-ink">Условия</a>
                    <a href="{{ route('privacy') }}" class="underline underline-offset-4 decoration-line-strong hover:text-ink">Конфиденциальность</a>
                </span>
            </footer>
        </div>
    </div>

    {{-- Правая половина: фото --}}
    <div class="sticky top-0 hidden h-screen min-w-0 flex-1 p-4 pl-0 lg:flex">
        <img src="{{ asset('images/bg.jpg') }}" alt="Учительница ведёт онлайн-занятие на Serdal" class="h-full w-full rounded-xl object-cover">
    </div>
</div>
</body>
</html>
