{{-- Раскладка кабинетов (учитель, ученик, админ). Правила — docs/design/BRAND.md.
     active — ключ активного пункта меню.
     bare — экран сам управляет отступами и высотой (Сообщения). --}}
@props(['title' => null, 'active' => null, 'bare' => false])
@php
    $user = auth()->user();
    $isStudent = $user?->role === \App\Models\User::ROLE_STUDENT;
    // Админка — та же раскладка со своим меню (экраны cabinet.admin.*)
    $isAdmin = $user?->role === \App\Models\User::ROLE_ADMIN && request()->routeIs('cabinet.admin.*');
    $unread = $user?->unreadNotifications()->count() ?? 0;
    $unreadMessages = $user ? app(\App\Services\MessengerService::class)->unreadCount($user) : 0;

    // Новый экран, если маршрут уже есть, иначе — страница старого кабинета
    $to = fn (string $route, string $legacy) => \Illuminate\Support\Facades\Route::has($route) ? route($route) : url($legacy);

    $nav = $isStudent
        ? [
            ['key' => 'home', 'label' => 'Главная', 'icon' => 'home', 'href' => route('cabinet.student.home')],
            ['key' => 'schedule', 'label' => 'Расписание', 'icon' => 'calendar', 'href' => $to('cabinet.student.schedule', '/student/schedule-calendar')],
            ['key' => 'tasks', 'label' => 'Задания', 'icon' => 'tasks', 'href' => $to('cabinet.student.tasks', '/student/homework')],
            ['key' => 'messages', 'label' => 'Сообщения', 'icon' => 'chat', 'href' => $to('cabinet.student.messages', '/student/messenger'), 'count' => $unreadMessages],
            ['key' => 'materials', 'label' => 'Материалы', 'icon' => 'folder', 'href' => $to('cabinet.student.materials', '/student/materials')],
            ['key' => 'recordings', 'label' => 'Записи', 'icon' => 'video', 'href' => $to('cabinet.student.recordings', '/student/recordings')],
            ['key' => 'payments', 'label' => 'Оплата', 'icon' => 'wallet', 'href' => $to('cabinet.student.payments', '/student/payment-debts')],
        ]
        : [
            ['key' => 'today', 'label' => 'Сегодня', 'icon' => 'home', 'href' => $to('cabinet.teacher.today', '/tutor')],
            ['key' => 'schedule', 'label' => 'Расписание', 'icon' => 'calendar', 'href' => $to('cabinet.teacher.schedule', '/tutor/schedule-calendar')],
            ['key' => 'messages', 'label' => 'Сообщения', 'icon' => 'chat', 'href' => $to('cabinet.teacher.messages', '/tutor/messenger'), 'count' => $unreadMessages],
            ['key' => 'students', 'label' => 'Ученики', 'icon' => 'users', 'href' => $to('cabinet.teacher.students', '/tutor/students')],
            ['key' => 'tasks', 'label' => 'Задания', 'icon' => 'tasks', 'href' => $to('cabinet.teacher.tasks', '/tutor/homework'), 'count' => $user ? \App\Services\HomeworkSubmissionService::toReview($user->id)->reorder()->count() : 0],
            ['key' => 'materials', 'label' => 'Материалы', 'icon' => 'folder', 'href' => $to('cabinet.teacher.materials', '/tutor/materials')],
            ['key' => 'recordings', 'label' => 'Записи', 'icon' => 'video', 'href' => $to('cabinet.teacher.recordings', '/tutor/recordings')],
            ['key' => 'reviews', 'label' => 'Отзывы', 'icon' => 'star', 'href' => $to('cabinet.teacher.reviews', '/tutor/reviews'), 'count' => app(\App\Services\TeacherReviewsService::class)->unreadCount($user)],
        ];
    if ($isAdmin) {
        $inbox = app(\App\Services\AdminInboxService::class)->counts();
        $a = fn (string $name, array $params = []) => \Illuminate\Support\Facades\Route::has('cabinet.admin.' . $name) ? route('cabinet.admin.' . $name, $params) : '#';
        // Разделители (sep) — вместо подписей групп: входящие · занятия и люди · деньги · сайт
        $nav = [
            ['key' => 'today', 'label' => 'Сегодня', 'icon' => 'home', 'href' => $a('today')],
            ['sep' => true],
            ['key' => 'support', 'label' => 'Поддержка', 'icon' => 'chat', 'href' => $a('support'), 'count' => $inbox['support']],
            ['key' => 'applications', 'label' => 'Заявки', 'icon' => 'user', 'href' => $a('applications'), 'count' => $inbox['applications']],
            ['key' => 'reviews', 'label' => 'Отзывы', 'icon' => 'star', 'href' => $a('reviews'), 'count' => $inbox['reviews']],
            ['sep' => true],
            ['key' => 'lessons', 'label' => 'Занятия', 'icon' => 'calendar', 'href' => $a('lessons'), 'count' => $inbox['lessons']],
            ['key' => 'users', 'label' => 'Пользователи', 'icon' => 'users', 'href' => $a('users')],
            ['sep' => true],
            ['key' => 'payments', 'label' => 'Платежи', 'icon' => 'wallet', 'href' => $a('payments')],
            ['key' => 'tariffs', 'label' => 'Тарифы', 'icon' => 'tasks', 'href' => $a('tariffs')],
            ['key' => 'referrals', 'label' => 'Приглашения', 'icon' => 'share', 'href' => $a('referrals')],
            ['sep' => true],
            ['key' => 'help', 'label' => 'База знаний', 'icon' => 'help', 'href' => $a('help')],
        ];
    }
    $mobileTabs = array_values(array_filter($nav, fn ($i) => in_array($i['key'] ?? null, $isAdmin ? ['today', 'support', 'lessons', 'users'] : ['home', 'today', 'schedule', 'tasks', 'messages'])));
    $profileHref = match (true) {
        $isAdmin => route('cabinet.admin.user', ['user' => $user->id]),
        $isStudent => $to('cabinet.student.profile', '/student/profile'),
        default => $to('cabinet.teacher.profile', '/tutor/edit-profile'),
    };
    $supportHref = $user ? \App\Services\MessengerService::url($user, support: true) : '#';

    // Тариф и лимиты учитель видит карточкой на «Сегодня» (x-ui.tariff), в сайдбаре — только ссылка
    $profileSub = $isAdmin ? 'Администратор' : ($isStudent ? 'Профиль' : 'Профиль и тариф');

    // «Ещё» на телефоне: разделы, которых нет на нижней панели, + поддержка, партнёрка, профиль
    $moreItems = array_values(array_filter($nav, fn ($i) => empty($i['sep']) && ! in_array($i, $mobileTabs, true)));
    if (! $isStudent && ! $isAdmin && \App\Services\ReferralService::enabled() && \Illuminate\Support\Facades\Route::has('cabinet.teacher.referrals')) {
        $moreItems[] = ['key' => 'referrals', 'label' => 'Пригласить коллег', 'icon' => 'share', 'href' => route('cabinet.teacher.referrals')];
    }
    if ($isAdmin) {
        $moreItems[] = ['key' => 'settings', 'label' => 'Настройки', 'icon' => 'settings', 'href' => route('cabinet.admin.settings')];
        $moreItems[] = ['key' => 'profile', 'label' => 'Профиль', 'icon' => 'user', 'href' => $profileHref];
    } else {
        $moreItems[] = ['key' => 'support', 'label' => 'Поддержка', 'icon' => 'help', 'href' => $supportHref];
        $moreItems[] = ['key' => 'profile', 'label' => $isStudent ? 'Профиль' : 'Профиль и тариф', 'icon' => 'user', 'href' => $profileHref];
    }
    $moreActive = $active === null || in_array($active, array_column($moreItems, 'key'), true);
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
            <button type="button" x-data="{ n: {{ $unread }} }" x-on:notifications-count.window="n = $event.detail.count" x-on:click="$dispatch('notifications-open')"
                class="relative flex size-9 items-center justify-center rounded text-muted hover:bg-soft-hover hover:text-ink" x-bind:aria-label="n ? 'Уведомления, есть новые' : 'Уведомления'" aria-label="Уведомления">
                <x-ui.icon name="bell" />
                <span x-show="n > 0" class="absolute right-2 top-2 size-2 rounded-full bg-danger shadow-dot-ring" {!! $unread ? '' : 'x-cloak' !!}></span>
            </button>
        </div>

        <nav class="flex flex-col gap-1" aria-label="Разделы">
            @foreach ($nav as $item)
                @if (! empty($item['sep']))
                    <span class="mx-3 my-2 h-px bg-line" aria-hidden="true"></span>
                    @continue
                @endif
                <a href="{{ $item['href'] }}" @class([
                    'flex h-11 items-center gap-3 rounded px-3 text-t1-s font-medium',
                    'bg-mint font-semibold text-ink' => $active === $item['key'],
                    'text-muted hover:bg-soft-hover hover:text-ink' => $active !== $item['key'],
                ]) {!! $active === $item['key'] ? 'aria-current="page"' : '' !!}>
                    <x-ui.icon :name="$item['icon']" />{{ $item['label'] }}
                    @if (array_key_exists('count', $item))
                        {{-- Живой счётчик: обновляется событием cabinet-counts (панель уведомлений) --}}
                        <span x-data="{ c: {{ (int) $item['count'] }} }" x-on:cabinet-counts.window="c = $event.detail.counts[@js($item['key'])] ?? c" x-show="c > 0" {!! $item['count'] ? '' : 'x-cloak' !!}
                              class="ml-auto inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-danger px-1 text-count font-semibold text-white" x-text="c > 99 ? '99+' : c">{{ $item['count'] > 99 ? '99+' : $item['count'] }}</span>
                    @endif
                </a>
            @endforeach
        </nav>

        <div class="mt-auto flex flex-col gap-2">
            @unless ($isStudent || $isAdmin)
                <livewire:cabinet.referral-promo />
                {{-- Плашку скрыли — партнёрка остаётся доступной обычной ссылкой --}}
                @if (\App\Services\ReferralService::enabled() && ! \App\Services\ReferralService::shouldShowBanner($user) && \Illuminate\Support\Facades\Route::has('cabinet.teacher.referrals'))
                    <a href="{{ route('cabinet.teacher.referrals') }}" class="flex h-11 items-center gap-3 rounded px-3 text-t1-s font-medium text-muted hover:bg-soft-hover hover:text-ink"><x-ui.icon name="share" />Пригласить коллег</a>
                @endif
            @endunless
            @if ($isAdmin)
                <a href="{{ route('cabinet.admin.settings') }}" @class(['flex h-11 items-center gap-3 rounded px-3 text-t1-s font-medium', 'bg-mint font-semibold text-ink' => $active === 'settings', 'text-muted hover:bg-soft-hover hover:text-ink' => $active !== 'settings'])><x-ui.icon name="settings" />Настройки</a>
            @else
                <a href="{{ $supportHref }}" class="flex h-11 items-center gap-3 rounded px-3 text-t1-s font-medium text-muted hover:bg-soft-hover hover:text-ink"><x-ui.icon name="help" />Поддержка</a>
            @endif
            <a href="{{ $profileHref }}" class="flex items-center gap-3 border-t border-line px-3 pt-4">
                <x-ui.avatar :user="$user" />
                <span class="flex min-w-0 flex-col gap-1">
                    <span class="truncate text-t2 font-medium">{{ $user?->name }}</span>
                    <span class="truncate text-t3 text-muted">{{ $profileSub }}</span>
                </span>
            </a>
        </div>
    </aside>

    <div @class(['flex min-w-0 flex-1 flex-col', 'lg:h-screen' => $bare])>
        {{-- Верхняя панель (телефон) --}}
        <div class="sticky top-0 z-10 flex h-16 items-center justify-between border-b border-line bg-white px-4 lg:hidden">
            <img src="{{ asset('images/Logo.svg') }}" alt="Serdal" class="h-6 w-auto">
            <button type="button" x-data="{ n: {{ $unread }} }" x-on:notifications-count.window="n = $event.detail.count" x-on:click="$dispatch('notifications-open')"
                class="relative flex size-11 items-center justify-center rounded shadow-outline" x-bind:aria-label="n ? 'Уведомления, есть новые' : 'Уведомления'" aria-label="Уведомления">
                <x-ui.icon name="bell" />
                <span x-show="n > 0" class="absolute right-3 top-3 size-2 rounded-full bg-danger shadow-dot-ring" {!! $unread ? '' : 'x-cloak' !!}></span>
            </button>
        </div>

        <main @class(['flex flex-1 flex-col', 'gap-6 px-4 pb-tabbar pt-6 lg:gap-8 lg:p-12' => ! $bare, 'min-h-0' => $bare])>
            {{ $slot }}
        </main>

        {{-- Нижняя панель вкладок (телефон) --}}
        <nav class="fixed inset-x-0 bottom-0 z-10 flex border-t border-line bg-white pb-4 pt-2 lg:hidden" aria-label="Разделы">
            @foreach ($mobileTabs as $item)
                <a href="{{ $item['href'] }}" class="flex flex-1 flex-col items-center gap-1 text-tab font-medium {{ $active === $item['key'] ? 'text-ink' : 'text-muted' }}">
                    <span class="relative flex h-8 w-12 items-center justify-center rounded-full {{ $active === $item['key'] ? 'bg-mint' : '' }}"><x-ui.icon :name="$item['icon']" />@if (! empty($item['count']))<span class="absolute right-3 top-1 size-2 rounded-full bg-danger shadow-dot-ring"></span>@endif</span>
                    {{ $item['label'] }}
                </a>
            @endforeach
            <button type="button" x-data x-on:click="$dispatch('more-open')" aria-haspopup="dialog" class="flex flex-1 flex-col items-center gap-1 text-tab font-medium {{ $moreActive ? 'text-ink' : 'text-muted' }}">
                <span class="relative flex h-8 w-12 items-center justify-center rounded-full {{ $moreActive ? 'bg-mint' : '' }}"><x-ui.icon name="menu" />@if (collect($moreItems)->contains(fn ($i) => ! empty($i['count'])))<span class="absolute right-3 top-1 size-2 rounded-full bg-danger shadow-dot-ring"></span>@endif</span>Ещё
            </button>
        </nav>

        {{-- «Ещё» (телефон): остальные разделы списком --}}
        <div x-data="{ open: false }" x-on:more-open.window="open = true" x-on:keydown.escape.window="open = false" x-show="open" x-cloak
             class="fixed inset-0 z-30 flex items-end bg-scrim lg:hidden" x-on:click.self="open = false">
            <nav role="dialog" aria-modal="true" aria-label="Все разделы" class="flex w-full flex-col gap-1 rounded-t-xl bg-white px-4 pb-8 pt-4">
                <div class="flex items-center justify-between pb-2 pl-3">
                    <span class="text-h2 font-medium">Ещё</span>
                    <x-ui.btn square icon="x" x-on:click="open = false" aria-label="Закрыть" />
                </div>

                @foreach ($moreItems as $item)
                    <a href="{{ $item['href'] }}" @class([
                        'flex h-11 items-center gap-3 rounded px-3 text-t1-s font-medium',
                        'bg-mint font-semibold text-ink' => $active === $item['key'],
                        'text-muted hover:bg-soft-hover hover:text-ink' => $active !== $item['key'],
                    ])>
                        <x-ui.icon :name="$item['icon']" />{{ $item['label'] }}
                        @if (! empty($item['count']))<x-ui.count :value="$item['count']" class="ml-auto" />@endif
                    </a>
                @endforeach
            </nav>
        </div>
    </div>
</div>
<livewire:cabinet.notifications />
<x-ui.lightbox />
<livewire:cabinet.push-prompt />
<x-ui.toast />
{{-- Звук важных уведомлений (флаг sound у broadcast-уведомления) — тот же скрипт, что в старом кабинете --}}
@include('partials.notification-sound')

{{-- Сбой запроса Livewire (resources/js/cabinet.js): 419 — «Страница устарела», 500 — «Что-то пошло не так» (макеты SySession, SyError) --}}
<div x-data="{ kind: null }" x-on:cabinet-request-failed.window="kind = $event.detail.kind" x-show="kind" x-cloak
     x-on:keydown.escape.window="kind = null" x-on:click.self="kind = null"
     class="fixed inset-0 z-40 flex items-center justify-center bg-scrim p-4">
    <div role="alertdialog" aria-modal="true" aria-labelledby="fail-title" class="flex w-full max-w-modal-s flex-col items-center gap-6 rounded-xl bg-white p-8 text-center shadow-modal">
        <span class="flex size-16 items-center justify-center rounded-lg bg-soft" aria-hidden="true"><x-ui.icon name="clock" x-show="kind === 'expired'" /><x-ui.icon name="help" x-show="kind !== 'expired'" /></span>
        <div class="flex flex-col gap-2">
            <h2 id="fail-title" class="text-h2 font-medium" x-text="kind === 'expired' ? 'Страница устарела' : 'Что-то пошло не так'"></h2>
            <p class="text-t1 text-muted" x-text="kind === 'expired' ? 'Вкладка долго была открыта без действий. Обновите её и повторите последнее действие — введённый текст мог не сохраниться.' : 'Сбой на нашей стороне. Обновите страницу через минуту — обычно этого достаточно.'"></p>
        </div>
        <div class="flex items-center gap-2">
            <x-ui.btn x-on:click="kind = null">Закрыть</x-ui.btn>
            <x-ui.btn variant="primary" icon="repeat" x-on:click="window.location.reload()">Обновить страницу</x-ui.btn>
        </div>
    </div>
</div>
</body>
</html>
