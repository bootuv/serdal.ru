{{-- Раскладка кабинетов (учитель, ученик, админ). Правила — docs/design/BRAND.md.
     active — ключ активного пункта меню.
     bare — экран сам управляет отступами и высотой (Сообщения). --}}
@props(['title' => null, 'active' => null, 'bare' => false])
@php
    $user = auth()->user();
    $isStudent = $user?->role === \App\Models\User::ROLE_STUDENT;
    // Админка — та же раскладка со своим меню (экраны cabinet.admin.* и общий экран «Почта и пароль»)
    $isAdmin = $user?->role === \App\Models\User::ROLE_ADMIN && request()->routeIs('cabinet.admin.*', 'cabinet.account');
    $unread = $user?->unreadNotifications()->count() ?? 0;
    $unreadMessages = $user ? app(\App\Services\MessengerService::class)->unreadCount($user) : 0;
    $unreadNews = app(\App\Services\AnnouncementService::class)->unreadCount($user);

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
            ['key' => 'news', 'label' => 'Новости', 'icon' => 'news', 'href' => $to('cabinet.student.news', '/cabinet/student'), 'count' => $unreadNews],
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
            ['key' => 'blog', 'label' => 'Мои статьи', 'icon' => 'pencil', 'href' => $to('cabinet.teacher.blog', '/cabinet/teacher')],
            ['key' => 'news', 'label' => 'Новости', 'icon' => 'news', 'href' => $to('cabinet.teacher.news', '/cabinet/teacher'), 'count' => $unreadNews],
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
            ['key' => 'founders', 'label' => 'Основатели', 'icon' => 'lock', 'href' => $a('founders')],
            ['sep' => true],
            ['key' => 'news', 'label' => 'Новости', 'icon' => 'news', 'href' => $a('news')],
            ['key' => 'blog', 'label' => 'Блог', 'icon' => 'pencil', 'href' => $a('blog'), 'count' => $inbox['blog']],
            ['key' => 'mailings', 'label' => 'Рассылки', 'icon' => 'mail', 'href' => $a('mailings')],
            ['key' => 'help', 'label' => 'База знаний', 'icon' => 'help', 'href' => $a('help')],
            ['sep' => true],
            ['key' => 'settings', 'label' => 'Настройки', 'icon' => 'settings', 'href' => $a('settings')],
        ];
    }
    $mobileTabs = array_values(array_filter($nav, fn ($i) => in_array($i['key'] ?? null, $isAdmin ? ['today', 'support', 'lessons', 'users'] : ['home', 'today', 'schedule', 'tasks', 'messages'])));
    $profileHref = match (true) {
        $isAdmin => route('cabinet.admin.user', ['user' => $user->id]),
        $isStudent => $to('cabinet.student.profile', '/student/profile'),
        default => $to('cabinet.teacher.profile', '/tutor/edit-profile'),
    };
    $supportHref = $user ? \App\Services\MessengerService::url($user, support: true) : '#';
    // «Тур по кабинету» — вернуться к туру в любой момент (CabinetTourService)
    $tourHref = ! $isAdmin && \App\Services\CabinetTourService::available($user) ? \App\Services\CabinetTourService::startUrl($user) : null;
    $helpCenterHref = route('help.section', $isStudent ? 'students' : 'tutors');

    // Тариф и лимиты учитель видит карточкой на «Сегодня» (x-ui.tariff), в сайдбаре — только ссылка
    $profileSub = $isAdmin ? 'Администратор' : ($isStudent ? 'Профиль' : 'Профиль и тариф');

    // Меню под именем в сайдбаре: вкладки профиля, тариф и выход (выход — отдельной формой внизу меню)
    $profileMenu = match (true) {
        $isAdmin, $isStudent => [['label' => 'Профиль', 'icon' => 'user', 'href' => $profileHref]],
        default => [
            ['label' => 'Профиль', 'icon' => 'user', 'href' => $profileHref],
            ['label' => 'Цены на занятия', 'icon' => 'wallet', 'href' => $profileHref . '?tab=prices'],
            ['label' => 'Уведомления', 'icon' => 'bell', 'href' => $profileHref . '?tab=notify'],
            ['label' => 'Тариф и платежи', 'icon' => 'tasks', 'href' => $to('cabinet.teacher.subscription', '/tutor/subscription')],
        ],
    };
    // У учителя почта и пароль — вкладка профиля, у остальных — отдельная страница
    $accountHref = $user?->role === \App\Models\User::ROLE_TUTOR ? $profileHref . '?tab=account' : route('cabinet.account');
    $profileMenu[] = ['label' => 'Почта и пароль', 'icon' => 'lock', 'href' => $accountHref];
    // Публичная страница учителя в каталоге — открывается в новой вкладке (пока учитель не активен, её нет)
    $publicHref = ! $isAdmin && ! $isStudent && $user?->is_active && $user->username ? route('tutors.show', ['username' => $user->username]) : null;
    if ($publicHref) {
        array_splice($profileMenu, 1, 0, [['label' => 'Моя страница', 'icon' => 'eye', 'href' => $publicHref, 'external' => true]]);
    }

    // «Ещё» на телефоне: разделы, которых нет на нижней панели, + поддержка, партнёрка, профиль
    $moreItems = array_values(array_filter($nav, fn ($i) => empty($i['sep']) && ! in_array($i, $mobileTabs, true)));
    if (! $isStudent && ! $isAdmin && \App\Services\ReferralService::enabled() && \Illuminate\Support\Facades\Route::has('cabinet.teacher.referrals')) {
        $moreItems[] = ['key' => 'referrals', 'label' => 'Пригласить коллег', 'icon' => 'share', 'href' => route('cabinet.teacher.referrals')];
    }
    if ($isAdmin) {
        $moreItems[] = ['key' => 'profile', 'label' => 'Профиль', 'icon' => 'user', 'href' => $profileHref];
    } else {
        if ($tourHref) {
            $moreItems[] = ['key' => 'tour', 'label' => 'Тур по кабинету', 'icon' => 'play', 'href' => $tourHref];
        }
        $moreItems[] = ['key' => 'support', 'label' => 'Поддержка', 'icon' => 'help', 'href' => $supportHref];
        $moreItems[] = ['key' => 'profile', 'label' => $isStudent ? 'Профиль' : 'Профиль и тариф', 'icon' => 'user', 'href' => $profileHref];
        if ($publicHref) {
            $moreItems[] = ['key' => 'public', 'label' => 'Моя страница', 'icon' => 'eye', 'href' => $publicHref, 'external' => true];
        }
    }
    $moreItems[] = ['key' => 'account', 'label' => 'Почта и пароль', 'icon' => 'lock', 'href' => $accountHref];
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
    @if ($tourHref)
        {{-- Идёт тур по кабинету (resources/js/tour.js) — затемняем экран сразу, до загрузки скриптов, чтобы переход между шагами не мигал --}}
        <script>try { if (sessionStorage.getItem('cabinet-tour') || /[?&]tour=/.test(location.search)) { const h = document.documentElement; h.dataset.tour = 'loading'; setTimeout(() => { if (h.dataset.tour === 'loading') delete h.dataset.tour; }, 5000); } } catch (e) {}</script>
    @endif
    @vite(['resources/css/cabinet.css', 'resources/js/cabinet.js'])
</head>
<body>
<div class="flex min-h-screen">

    {{-- Сайдбар (компьютер) --}}
    <aside class="sticky top-0 hidden h-screen w-sidebar shrink-0 flex-col border-r border-line px-4 pb-4 pt-8 lg:flex">
        <div class="mb-8 flex shrink-0 items-center justify-between gap-2 pl-3">
            <a href="{{ $nav[0]['href'] }}"><img src="{{ asset('images/Logo.svg') }}" alt="Serdal" class="h-6 w-auto"></a>
            <button type="button" x-data="{ n: {{ $unread }} }" x-on:notifications-count.window="n = $event.detail.count" x-on:click="$dispatch('notifications-open')" data-tour="bell"
                class="relative flex size-9 items-center justify-center rounded text-muted hover:bg-soft-hover hover:text-ink" x-bind:aria-label="n ? 'Уведомления, есть новые' : 'Уведомления'" aria-label="Уведомления">
                <x-ui.icon name="bell" />
                <span x-show="n > 0" class="absolute right-2 top-2 size-2 rounded-full bg-danger shadow-dot-ring" {!! $unread ? '' : 'x-cloak' !!}></span>
            </button>
        </div>

        {{-- Сайдбар по высоте экрана: разделы прокручиваются внутри, логотип и низ (настройки, профиль) всегда на месте --}}
        <nav class="scroll-thin -mx-1 flex min-h-0 flex-1 flex-col gap-1 overflow-y-auto px-1" aria-label="Разделы">
            @foreach ($nav as $item)
                @if (! empty($item['sep']))
                    <span class="mx-3 my-2 h-px shrink-0 bg-line" aria-hidden="true"></span>
                    @continue
                @endif
                <a href="{{ $item['href'] }}" data-tour="nav-{{ $item['key'] }}" @class([
                    'flex h-11 shrink-0 items-center gap-3 rounded px-3 text-t1-s font-medium',
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

        <div class="mt-2 flex shrink-0 flex-col gap-2">
            @unless ($isStudent || $isAdmin)
                <livewire:cabinet.referral-promo />
                {{-- Плашку скрыли — партнёрка остаётся доступной обычной ссылкой --}}
                @if (\App\Services\ReferralService::enabled() && ! \App\Services\ReferralService::shouldShowBanner($user) && \Illuminate\Support\Facades\Route::has('cabinet.teacher.referrals'))
                    <a href="{{ route('cabinet.teacher.referrals') }}" data-tour="referrals" class="flex h-11 items-center gap-3 rounded px-3 text-t1-s font-medium text-muted hover:bg-soft-hover hover:text-ink"><x-ui.icon name="share" />Пригласить коллег</a>
                @endif
            @endunless
            @unless ($isAdmin)
                {{-- Помощь: поддержка, тур и база знаний одной строкой, список открывается вверх. Тур открывает его сам (событие tour-reveal). --}}
                <div class="relative" data-tour="help" x-data="{ open: false }" x-on:click.outside="open = false" x-on:keydown.escape="open = false"
                     x-on:tour-reveal.window="open = $event.detail.name === 'help'">
                    <div x-show="open" x-cloak role="menu" aria-label="Помощь" data-tour="help-menu" class="absolute inset-x-0 bottom-full z-10 mb-1 flex flex-col rounded border border-line bg-white p-1 shadow-card">
                        <a href="{{ $supportHref }}" role="menuitem" class="flex h-11 items-center gap-3 rounded-sm px-3 text-t1-s font-medium text-ink hover:bg-soft-hover"><x-ui.icon name="chat" />Поддержка</a>
                        @if ($tourHref)
                            <a href="{{ $tourHref }}" role="menuitem" class="flex h-11 items-center gap-3 rounded-sm px-3 text-t1-s font-medium text-ink hover:bg-soft-hover"><x-ui.icon name="play" />Тур по кабинету</a>
                        @endif
                        <a href="{{ $helpCenterHref }}" target="_blank" rel="noopener" role="menuitem" class="flex h-11 items-center gap-3 rounded-sm px-3 text-t1-s font-medium text-ink hover:bg-soft-hover"><x-ui.icon name="list" />База знаний<x-ui.icon name="external" size="s" class="ml-auto text-faint" /></a>
                    </div>
                    <button type="button" x-on:click="open = ! open" x-bind:aria-expanded="open" aria-haspopup="menu"
                            class="flex h-11 w-full items-center gap-3 rounded px-3 text-t1-s font-medium hover:bg-soft-hover hover:text-ink" x-bind:class="open ? 'bg-soft-hover text-ink' : 'text-muted'">
                        <x-ui.icon name="help" />Помощь<x-ui.icon name="chevron-down" size="s" class="ml-auto transition-transform" x-bind:class="open && 'rotate-180'" />
                    </button>
                </div>
            @endunless
            {{-- Профиль: по клику — меню вверх с разделами профиля и выходом. Тур подсвечивает его целиком (nav-profile). --}}
            <div class="relative border-t border-line pt-3" data-tour="nav-profile" x-data="{ open: false }" x-on:click.outside="open = false" x-on:keydown.escape="open = false">
                <div x-show="open" x-cloak role="menu" aria-label="Профиль" class="absolute inset-x-0 bottom-full z-10 mb-1 flex flex-col rounded border border-line bg-white p-1 shadow-card">
                    @foreach ($profileMenu as $item)
                        <a href="{{ $item['href'] }}" @if (! empty($item['external'])) target="_blank" rel="noopener" @endif role="menuitem" class="flex h-11 items-center gap-3 rounded-sm px-3 text-t1-s font-medium text-ink hover:bg-soft-hover"><x-ui.icon :name="$item['icon']" />{{ $item['label'] }}@if (! empty($item['external']))<x-ui.icon name="external" size="s" class="ml-auto text-faint" />@endif</a>
                    @endforeach
                    <span class="mx-3 my-1 h-px bg-line" aria-hidden="true"></span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" role="menuitem" class="flex h-11 w-full items-center gap-3 rounded-sm px-3 text-t1-s font-medium text-ink hover:bg-soft-hover"><x-ui.icon name="logout" />Выйти из кабинета</button>
                    </form>
                </div>
                <button type="button" x-on:click="open = ! open" x-bind:aria-expanded="open" aria-haspopup="menu"
                        class="flex w-full items-center gap-3 rounded py-2 pl-2 pr-3 text-left hover:bg-soft-hover" x-bind:class="open && 'bg-soft-hover'">
                    <x-ui.avatar :user="$user" />
                    <span class="flex min-w-0 flex-1 flex-col gap-1">
                        <span class="truncate text-t2 font-medium">{{ $user?->name }}</span>
                        <span class="truncate text-t3 text-muted">{{ $profileSub }}</span>
                    </span>
                    <x-ui.icon name="chevron-down" size="s" class="shrink-0 text-muted transition-transform" x-bind:class="open && 'rotate-180'" />
                </button>
            </div>
        </div>
    </aside>

    {{-- overflow-x-clip: что бы ни пришло в данных, страница не шире экрана и не ездит вбок на телефоне (clip, а не hidden — верхняя панель остаётся липкой) --}}
    <div @class(['flex min-w-0 flex-1 flex-col overflow-x-clip', 'lg:h-screen' => $bare])>
        {{-- Верхняя панель (телефон) --}}
        <div class="sticky top-0 z-10 flex h-16 items-center justify-between border-b border-line bg-white px-4 lg:hidden">
            <a href="{{ $nav[0]['href'] }}" aria-label="Serdal — на главный экран кабинета"><img src="{{ asset('images/Logo.svg') }}" alt="Serdal" class="h-6 w-auto"></a>
            <button type="button" x-data="{ n: {{ $unread }} }" x-on:notifications-count.window="n = $event.detail.count" x-on:click="$dispatch('notifications-open')" data-tour="bell"
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
                <a href="{{ $item['href'] }}" data-tour="tab-{{ $item['key'] }}" class="flex flex-1 flex-col items-center gap-1 text-tab font-medium {{ $active === $item['key'] ? 'text-ink' : 'text-muted' }}">
                    <span class="relative flex h-8 w-12 items-center justify-center rounded-full {{ $active === $item['key'] ? 'bg-mint' : '' }}"><x-ui.icon :name="$item['icon']" />@if (! empty($item['count']))<span class="absolute right-3 top-1 size-2 rounded-full bg-danger shadow-dot-ring"></span>@endif</span>
                    {{ $item['label'] }}
                </a>
            @endforeach
            <button type="button" x-data x-on:click="$dispatch('more-open')" aria-haspopup="dialog" data-tour="more" class="flex flex-1 flex-col items-center gap-1 text-tab font-medium {{ $moreActive ? 'text-ink' : 'text-muted' }}">
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
                    <a href="{{ $item['href'] }}" @if (! empty($item['external'])) target="_blank" rel="noopener" @endif @class([
                        'flex h-11 items-center gap-3 rounded px-3 text-t1-s font-medium',
                        'bg-mint font-semibold text-ink' => $active === $item['key'],
                        'text-muted hover:bg-soft-hover hover:text-ink' => $active !== $item['key'],
                    ])>
                        <x-ui.icon :name="$item['icon']" />{{ $item['label'] }}
                        @if (! empty($item['count']))<x-ui.count :value="$item['count']" class="ml-auto" />@endif
                        @if (! empty($item['external']))<x-ui.icon name="external" size="s" class="ml-auto text-faint" />@endif
                    </a>
                @endforeach
                <form method="POST" action="{{ route('logout') }}" class="mt-1 border-t border-line pt-2">
                    @csrf
                    <button type="submit" class="flex h-11 w-full items-center gap-3 rounded px-3 text-t1-s font-medium text-muted hover:bg-soft-hover hover:text-ink"><x-ui.icon name="logout" />Выйти из кабинета</button>
                </form>
            </nav>
        </div>
    </div>
</div>
<livewire:cabinet.notifications />
<x-ui.lightbox />
<livewire:cabinet.push-prompt />
@unless ($isAdmin)
    <livewire:cabinet.tour />
@endunless
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
