<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Notifications\SubscriptionExpired;
use App\Notifications\SubscriptionExpiringSoon;
use Illuminate\Console\Command;

class CheckSubscriptions extends Command
{
    protected $signature = 'subscriptions:check';

    protected $description = 'Помечает истёкшие подписки и уведомляет учителей об окончании срока';

    /**
     * За сколько дней предупреждать об окончании подписки.
     */
    const EXPIRING_WARNING_DAYS = 3;

    public function handle(): int
    {
        // 1. Подписки, у которых закончился срок — помечаем и уведомляем
        $expired = Subscription::where('status', Subscription::STATUS_ACTIVE)
            ->whereNotNull('ends_at')
            ->where('ends_at', '<=', now())
            ->with(['user', 'tariff'])
            ->get();

        foreach ($expired as $subscription) {
            $subscription->update(['status' => Subscription::STATUS_EXPIRED]);

            // Если было запланировано переключение (например, на бесплатный тариф),
            // оно уже вступило в силу — «подписка истекла» слать не нужно
            $user = $subscription->user;
            if ($user && !$user->activeSubscription()) {
                $user->notify(new SubscriptionExpired($subscription->tariff->name));
            }
        }

        // 2. Подписки, которые скоро закончатся — предупреждаем один раз
        $expiring = Subscription::where('status', Subscription::STATUS_ACTIVE)
            ->whereNotNull('ends_at')
            ->where('ends_at', '>', now())
            ->where('ends_at', '<=', now()->addDays(self::EXPIRING_WARNING_DAYS))
            ->whereNull('expiring_notified_at')
            ->with(['user', 'tariff'])
            ->get();

        foreach ($expiring as $subscription) {
            $subscription->user?->notify(new SubscriptionExpiringSoon(
                $subscription->tariff->name,
                $subscription->ends_at,
            ));
            $subscription->update(['expiring_notified_at' => now()]);
        }

        $this->info("Истекло: {$expired->count()}, предупреждено: {$expiring->count()}");

        return self::SUCCESS;
    }
}
