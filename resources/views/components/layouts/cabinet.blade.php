{{-- Раскладка кабинетов (учитель, ученик). Правила — docs/design/BRAND.md.
     active — ключ активного пункта меню. Пункты без нового экрана пока ведут в старый кабинет (Filament). --}}
@props(['title' => null, 'active' => null])
@php
    $user = auth()->user();
    $isStudent = $user?->role === \App\Models\User::ROLE_STUDENT;
    $unread = $user?->unreadNotifications()->count() ?? 0;

    $nav = $isStudent
        ? [
            ['key' => 'home', 'label' => 'Главная', 'icon' => 'home', 'href' => route('cabinet.student.home')],
            ['key' => 'schedule', 'label' => 'Расписание', 'icon' => 'calendar', 'href' => url('/student/schedule-calendar')],
            ['key' => 'tasks', 'label' => 'Задания', 'icon' => 'tasks', 'href' => url('/student/homework')],
            ['key' => 'messages', 'label' => 'Сообщения', 'icon' => 'chat', 'href' => url('/student/messenger')],
            ['key' => 'materials', 'label' => 'Материалы', 'icon' => 'folder', 'href' => url('/student/materials')],
            ['key' => 'recordings', 'label' => 'Записи', 'icon' => 'video', 'href' => url('/student/recordings')],
            ['key' => 'payments', 'label' => 'Оплата', 'icon' => 'wallet', 'href' => url('/student/payment-debts')],
        ]
        : [
            ['key' => 'today', 'label' => 'Сегодня', 'icon' => 'home', 'href' => url('/tutor')],
            ['key' => 'schedule', 'label' => 'Расписание', 'icon' => 'calendar', 'href' => url('/tutor')],
            ['key' => 'messages', 'label' => 'Сообщения', 'icon' => 'chat', 'href' => url('/tutor/messenger')],
            ['key' => 'students', 'label' => 'Ученики', 'icon' => 'users', 'href' => url('/tutor')],
            ['key' => 'tasks', 'label' => 'Задания', 'icon' => 'tasks', 'href' => url('/tutor')],
            ['key' => 'materials', 'label' => 'Материалы', 'icon' => 'folder', 'href' => url('/tutor')],
            ['key' => 'recordings', 'label' => 'Записи', 'icon' => 'video', 'href' => url('/tutor')],
            ['key' => 'reviews', 'label' => 'Отзывы', 'icon' => 'star', 'href' => url('/tutor')],
        ];
    $mobileTabs = array_values(array_filter($nav, fn ($i) => in_array($i['key'], ['home', 'today', 'schedule', 'tasks', 'messages'])));
    $profileHref = $isStudent ? url('/student/profile') : url('/tutor');
    $supportHref = $isStudent ? url('/student/messenger?support=1') : url('/tutor/messenger?support=1');
@endphp
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title . ' — ' : '' }}{{ \App\Support\Seo::SITE_NAME }}</title>
    @include('partials.favicon')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    @vite(['resources/css/cabinet.css', 'resources/js/cabinet.js'])
</head>
<body>
<div class="flex min-h-screen">

    {{-- Сайдбар (компьютер) --}}
    <aside class="sticky top-0 hidden h-screen w-sidebar shrink-0 flex-col gap-8 border-r border-line px-4 pb-4 pt-8 lg:flex">
        <div class="flex items-center justify-between gap-2 pl-3">
            <a href="{{ $nav[0]['href'] }}"><img src="{{ asset('images/Logo.svg') }}" alt="Serdal" class="h-6 w-auto"></a>
            <a href="{{ $isStudent ? url('/student') : url('/tutor') }}" class="relative flex size-9 items-center justify-center rounded text-muted hover:bg-soft-hover hover:text-ink" aria-label="Уведомления{{ $unread ? ', есть новые' : '' }}">
                <x-ui.icon name="bell" />
                @if ($unread)<span class="absolute right-2 top-2 size-2 rounded-full bg-danger shadow-dot-ring"></span>@endif
            </a>
        </div>

        <nav class="flex flex-col gap-1" aria-label="Разделы">
            @foreach ($nav as $item)
                <a href="{{ $item['href'] }}" @class([
                    'flex h-11 items-center gap-3 rounded px-3 text-t1-s font-medium',
                    'bg-mint font-semibold text-ink' => $active === $item['key'],
                    'text-muted hover:bg-soft-hover hover:text-ink' => $active !== $item['key'],
                ]) @if($active === $item['key']) aria-current="page" @endif>
                    <x-ui.icon :name="$item['icon']" />{{ $item['label'] }}
                    @if (! empty($item['count']))<x-ui.count :value="$item['count']" class="ml-auto" />@endif
                </a>
            @endforeach
        </nav>

        <div class="mt-auto flex flex-col gap-2">
            <a href="{{ $supportHref }}" class="flex h-11 items-center gap-3 rounded px-3 text-t1-s font-medium text-muted hover:bg-soft-hover hover:text-ink"><x-ui.icon name="help" />Поддержка</a>
            <a href="{{ $profileHref }}" class="flex items-center gap-3 border-t border-line px-3 pt-4">
                <x-ui.avatar :user="$user" />
                <span class="flex min-w-0 flex-col gap-1">
                    <span class="truncate text-t2 font-medium">{{ $user?->name }}</span>
                    <span class="truncate text-t3 text-muted">{{ $isStudent ? 'Профиль' : 'Профиль и тариф' }}</span>
                </span>
            </a>
        </div>
    </aside>

    <div class="flex min-w-0 flex-1 flex-col">
        {{-- Верхняя панель (телефон) --}}
        <div class="sticky top-0 z-10 flex h-16 items-center justify-between border-b border-line bg-white px-4 lg:hidden">
            <img src="{{ asset('images/Logo.svg') }}" alt="Serdal" class="h-6 w-auto">
            <a href="{{ $isStudent ? url('/student') : url('/tutor') }}" class="relative flex size-11 items-center justify-center rounded shadow-outline" aria-label="Уведомления{{ $unread ? ', есть новые' : '' }}">
                <x-ui.icon name="bell" />
                @if ($unread)<span class="absolute right-3 top-3 size-2 rounded-full bg-danger shadow-dot-ring"></span>@endif
            </a>
        </div>

        <main class="flex flex-1 flex-col gap-6 px-4 pb-tabbar pt-6 lg:gap-8 lg:p-12">
            {{ $slot }}
        </main>

        {{-- Нижняя панель вкладок (телефон) --}}
        <nav class="fixed inset-x-0 bottom-0 z-10 flex border-t border-line bg-white pb-4 pt-2 lg:hidden" aria-label="Разделы">
            @foreach ($mobileTabs as $item)
                <a href="{{ $item['href'] }}" class="flex flex-1 flex-col items-center gap-1 text-tab font-medium {{ $active === $item['key'] ? 'text-ink' : 'text-muted' }}">
                    <span class="flex h-8 w-12 items-center justify-center rounded-full {{ $active === $item['key'] ? 'bg-mint' : '' }}"><x-ui.icon :name="$item['icon']" /></span>
                    {{ $item['label'] }}
                </a>
            @endforeach
            <a href="{{ $profileHref }}" class="flex flex-1 flex-col items-center gap-1 text-tab font-medium text-muted">
                <span class="flex h-8 w-12 items-center justify-center rounded-full"><x-ui.icon name="menu" /></span>Ещё
            </a>
        </nav>
    </div>
</div>
</body>
</html>
