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
@endphp

@if(!$subscription)
    <a href="{{ $subscriptionUrl }}" title="Подписка не оформлена — выберите тариф"
        class="hidden sm:flex items-center gap-1.5 px-3 rounded-xl border transition-all"
        style="height: 32px; border-color: #fbbf24; background: rgba(251, 191, 36, .1);">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
            class="w-4 h-4" style="color: #D97706;">
            <path stroke-linecap="round" stroke-linejoin="round"
                d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z" />
        </svg>
        <span class="text-sm font-medium" style="color: #D97706;">Выбрать тариф</span>
    </a>
@else
    <a href="{{ $subscriptionUrl }}"
        title="{{ $subscription->ends_at ? 'Подписка действует до ' . $subscription->ends_at->format('d.m.Y') : 'Ваш текущий тариф' }}"
        class="hidden sm:flex items-center gap-1.5 px-3 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 hover:border-gray-300 dark:hover:border-gray-600 transition-all"
        style="height: 32px;">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
            class="w-4 h-4 {{ $isExpiring ? '' : 'text-gray-400' }}" @if($isExpiring) style="color: #ef4444;" @endif>
            <path stroke-linecap="round" stroke-linejoin="round"
                d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z" />
        </svg>
        <span class="text-sm font-medium text-gray-700 dark:text-gray-200">{{ $subscription->tariff->name }}</span>
        @if($isExpiring)
            <span class="text-xs font-medium" style="color: #ef4444;">
                {{ $daysLeft <= 0 ? 'истекает сегодня' : 'ещё ' . $daysLeft . ' дн.' }}
            </span>
        @elseif($subscription->ends_at)
            <span class="text-xs text-gray-400 hidden lg:inline">до {{ $subscription->ends_at->format('d.m') }}</span>
        @endif
    </a>
@endif
