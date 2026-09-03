@php
    $user = auth()->user();

    // Бейдж показываем только преподавателям
    if (!$user || !in_array($user->role, [\App\Models\User::ROLE_TUTOR, \App\Models\User::ROLE_MENTOR])) {
        return;
    }

    $subscription = $user->activeSubscription();
    $subscriptionUrl = route('filament.app.pages.subscription');

    $daysLeft = $subscription?->ends_at ? (int) now()->diffInDays($subscription->ends_at, false) : null;
    $isExpiring = $daysLeft !== null && $daysLeft <= 5;

    // Была подписка, но срок вышел (или крон ещё не пометил её истёкшей)
    $expired = $subscription ? null : $user->subscriptions()
        ->whereIn('status', [\App\Models\Subscription::STATUS_EXPIRED, \App\Models\Subscription::STATUS_ACTIVE])
        ->with('tariff')
        ->latest('starts_at')
        ->first();

    // Цветовая схема тарифа: бесплатный — нейтральная, платные — по возрастанию уровня
    $freePalette = ['fg' => '#475569', 'border' => '#cbd5e1', 'bg' => 'rgba(148, 163, 184, .16)', 'icon' => '#64748b'];
    $paidPalettes = [
        ['fg' => '#1d4ed8', 'border' => '#93c5fd', 'bg' => 'rgba(59, 130, 246, .14)', 'icon' => '#2563eb'],   // 1-й платный: синий
        ['fg' => '#b45309', 'border' => '#fcd34d', 'bg' => 'rgba(251, 191, 36, .22)', 'icon' => '#d97706'],   // 2-й: янтарный
        ['fg' => '#6d28d9', 'border' => '#c4b5fd', 'bg' => 'rgba(139, 92, 246, .16)', 'icon' => '#7c3aed'],   // 3-й: фиолетовый
        ['fg' => '#0f766e', 'border' => '#5eead4', 'bg' => 'rgba(20, 184, 166, .16)', 'icon' => '#0d9488'],   // 4-й: бирюзовый
    ];
    $palette = null;
    if ($subscription) {
        $tariff = $subscription->tariff;
        if ($tariff->isFree()) {
            $palette = $freePalette;
        } else {
            $paidIds = \App\Models\Tariff::where('is_active', true)
                ->where('price', '>', 0)
                ->orderBy('sort')
                ->pluck('id')
                ->values();
            $rank = $paidIds->search($tariff->id);
            $palette = $paidPalettes[($rank === false ? 0 : $rank) % count($paidPalettes)];
        }
    }
    $endsLabel = $subscription?->ends_at?->format('d.m.Y');
@endphp

<style>
    @keyframes sub-badge-pulse {
        0%, 100% { opacity: 1; transform: scale(1); }
        50% { opacity: .45; transform: scale(.75); }
    }
    .sub-badge { height: 34px; font-weight: 600; letter-spacing: -.01em; white-space: nowrap; }
    .sub-badge:hover { filter: brightness(.96); transform: translateY(-1px); }
    .sub-badge__dot { width: 8px; height: 8px; border-radius: 999px; background: #ef4444; animation: sub-badge-pulse 1.4s ease-in-out infinite; }
    .sub-badge__pill { font-size: 11px; font-weight: 700; line-height: 1; padding: 4px 7px; border-radius: 999px; }
</style>

@if(!$subscription && $expired)
    {{-- Срок истёк: сплошная красная плашка --}}
    <a href="{{ $subscriptionUrl }}"
        title="Срок действия тарифа «{{ $expired->tariff->name }}» истёк{{ $expired->ends_at ? ' ' . $expired->ends_at->format('d.m.Y') : '' }} — продлите подписку"
        class="sub-badge hidden sm:flex items-center gap-2 px-3.5 rounded-xl transition-all"
        style="background: #dc2626; color: #fff; box-shadow: 0 2px 10px rgba(220, 38, 38, .35);">
        <span class="sub-badge__dot" style="background: #fff;"></span>
        <span class="text-sm">Срок истёк</span>
        <span class="sub-badge__pill" style="background: rgba(255, 255, 255, .22); color: #fff;">Продлить</span>
    </a>
@elseif(!$subscription)
    {{-- Подписки нет: яркая жёлтая плашка с призывом --}}
    <a href="{{ $subscriptionUrl }}" title="Подписка не оформлена — выберите тариф"
        class="sub-badge hidden sm:flex items-center gap-2 px-3.5 rounded-xl transition-all"
        style="background: #fbbf24; color: #1f2937; box-shadow: 0 2px 10px rgba(251, 191, 36, .45);">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"
            class="w-4 h-4">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
        </svg>
        <span class="text-sm">Выбрать тариф</span>
    </a>
@else
    {{-- Активный тариф: цвет зависит от уровня тарифа, при скором окончании — красная рамка и счётчик --}}
    <a href="{{ $subscriptionUrl }}"
        title="Тариф «{{ $subscription->tariff->name }}»{{ $endsLabel ? ' действует до ' . $endsLabel : '' }}{{ $isExpiring ? ' — продлите подписку' : '' }}"
        class="sub-badge hidden sm:flex items-center gap-2 px-3.5 rounded-xl border-2 transition-all"
        style="background: {{ $palette['bg'] }}; border-color: {{ $isExpiring ? '#ef4444' : $palette['border'] }}; color: {{ $palette['fg'] }};">
        @if($isExpiring)
            <span class="sub-badge__dot"></span>
        @else
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"
                class="w-4 h-4" style="color: {{ $palette['icon'] }};">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z" />
            </svg>
        @endif
        <span class="text-sm">{{ $subscription->tariff->name }}</span>
        @if($isExpiring)
            <span class="sub-badge__pill" style="background: #ef4444; color: #fff;">
                {{ $daysLeft <= 0 ? 'истекает сегодня' : 'ещё ' . plural_ru($daysLeft, 'день', 'дня', 'дней') }}
            </span>
        @elseif($subscription->ends_at)
            <span class="text-xs font-medium hidden lg:inline" style="opacity: .8;">до {{ $subscription->ends_at->format('d.m') }}</span>
        @endif
    </a>
@endif
