<?php

namespace App\Filament\App\Widgets;

use App\Models\Subscription;
use App\Services\SubscriptionService;
use Filament\Widgets\Widget;

/**
 * Блок «Мой тариф» на инфопанели учителя: сколько занятий осталось в периоде,
 * когда обновится лимит, до какого числа действует тариф и его ограничения
 * (участники, длительность, хранение записей).
 */
class TariffLimitsWidget extends Widget
{
    protected static string $view = 'filament.app.widgets.tariff-limits';

    // Первый блок инфопанели, перед «Ожидают оплаты» (1) и «Ближайшими занятиями» (2)
    protected static ?int $sort = 0;

    protected int|string|array $columnSpan = 'full';

    /**
     * За сколько дней до окончания подписки подсвечивать срок.
     */
    const EXPIRING_DAYS = 5;

    /**
     * При каком остатке занятий подсвечивать счётчик (в долях от лимита).
     */
    const LOW_LESSONS_RATIO = 0.25;

    protected function getViewData(): array
    {
        $user = auth()->user();
        $subscription = $user->activeSubscription();

        // Была подписка, но срок вышел (или крон ещё не пометил её истёкшей)
        $expired = $subscription ? null : $user->subscriptions()
            ->whereIn('status', [Subscription::STATUS_EXPIRED, Subscription::STATUS_ACTIVE])
            ->with('tariff')
            ->latest('starts_at')
            ->first();

        $tariff = $subscription?->tariff;
        $limit = $tariff?->lessons_per_month;
        $lessonsUsed = $subscription ? SubscriptionService::lessonsUsedThisPeriod($user) : 0;
        $remaining = $limit !== null ? max(0, $limit - $lessonsUsed) : null;
        $percent = $limit ? min(100, (int) round($lessonsUsed / $limit * 100)) : 0;
        $limitReached = $limit !== null && $lessonsUsed >= $limit;
        $lessonsLow = $limit !== null && !$limitReached && $remaining <= max(1, (int) ceil($limit * self::LOW_LESSONS_RATIO));

        $daysLeft = $subscription?->ends_at ? max(0, (int) now()->diffInDays($subscription->ends_at, false)) : null;

        return [
            'subscription' => $subscription,
            'scheduled' => $subscription ? $user->scheduledSubscription() : null,
            'expired' => $expired,
            'tariff' => $tariff,
            'limit' => $limit,
            'lessonsUsed' => $lessonsUsed,
            'remaining' => $remaining,
            'percent' => $percent,
            'limitReached' => $limitReached,
            'lessonsLow' => $lessonsLow,
            'periodStart' => $subscription ? SubscriptionService::periodStart($user) : null,
            'periodResetsAt' => $subscription ? SubscriptionService::periodResetsAt($user) : null,
            'extraBalance' => (int) $user->extra_lessons_balance,
            'canBuyExtra' => SubscriptionService::canBuyExtraLessons($user),
            'daysLeft' => $daysLeft,
            'isExpiring' => $daysLeft !== null && $daysLeft <= self::EXPIRING_DAYS,
            'subscriptionUrl' => route('filament.app.pages.subscription'),
        ];
    }
}
