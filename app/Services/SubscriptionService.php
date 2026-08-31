<?php

namespace App\Services;

use App\Models\MeetingSession;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\Tariff;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SubscriptionService
{
    /**
     * Минимальная длительность занятия, чтобы оно расходовало лимит тарифа.
     */
    const MIN_LESSON_SECONDS = 5 * 60;

    /**
     * Активирует тариф пользователю: закрывает текущую активную подписку
     * и создаёт новую. Для платных тарифов вызывается после подтверждения оплаты.
     * $unlimited = true — бессрочная подписка (ручная выдача администратором).
     */
    public static function activate(
        User $user,
        Tariff $tariff,
        ?SubscriptionPayment $payment = null,
        ?int $days = null,
        bool $unlimited = false,
        ?string $comment = null,
    ): Subscription {
        return DB::transaction(function () use ($user, $tariff, $payment, $days, $unlimited, $comment) {
            // Закрываем предыдущие активные подписки
            $user->subscriptions()
                ->where('status', Subscription::STATUS_ACTIVE)
                ->update(['status' => Subscription::STATUS_CANCELLED, 'cancelled_at' => now()]);

            $subscription = Subscription::create([
                'user_id' => $user->id,
                'tariff_id' => $tariff->id,
                'status' => Subscription::STATUS_ACTIVE,
                'price' => $payment?->amount ?? $tariff->price,
                'starts_at' => now(),
                // Бесплатный тариф и ручная бессрочная выдача — без даты окончания,
                // платный — на период тарифа
                'ends_at' => ($tariff->isFree() || $unlimited) ? null : now()->addDays($days ?? $tariff->period_days),
                'comment' => $comment,
            ]);

            $payment?->update(['subscription_id' => $subscription->id]);

            return $subscription;
        });
    }

    /**
     * Продлевает активную подписку на тот же тариф (после успешной оплаты).
     */
    public static function extend(Subscription $subscription, ?SubscriptionPayment $payment = null): Subscription
    {
        $base = $subscription->ends_at && $subscription->ends_at->isFuture()
            ? $subscription->ends_at
            : now();

        $subscription->update([
            'ends_at' => $base->copy()->addDays($subscription->tariff->period_days),
            'status' => Subscription::STATUS_ACTIVE,
        ]);

        $payment?->update(['subscription_id' => $subscription->id]);

        return $subscription;
    }

    /**
     * Обрабатывает подтверждённый платёж: активирует или продлевает подписку.
     */
    public static function applyPaidPayment(SubscriptionPayment $payment): Subscription
    {
        $payment->update([
            'status' => SubscriptionPayment::STATUS_PAID,
            'paid_at' => $payment->paid_at ?? now(),
        ]);

        $user = $payment->user;
        $current = $user->activeSubscription();

        // Оплата того же тарифа = продление, иного тарифа = переключение
        if ($current && $current->tariff_id === $payment->tariff_id) {
            $subscription = self::extend($current, $payment);
        } else {
            $subscription = self::activate($user, $payment->tariff, $payment);
        }

        // При продлении сбрасываем флаг предупреждения, чтобы оно пришло и в новом периоде
        $subscription->update(['expiring_notified_at' => null]);

        $user->notify(new \App\Notifications\SubscriptionPaid(
            $subscription->tariff->name,
            $payment->amount,
            $subscription->ends_at,
        ));

        return $subscription;
    }

    /**
     * Число проведённых занятий в текущем периоде подписки
     * (для бесплатного тарифа / без подписки — с начала календарного месяца).
     */
    public static function lessonsUsedThisPeriod(User $user): int
    {
        $subscription = $user->activeSubscription();

        $from = $subscription && $subscription->ends_at
            // начало текущего оплаченного периода
            ? $subscription->ends_at->copy()->subDays($subscription->tariff->period_days)
            : now()->startOfMonth();

        if ($subscription && $subscription->starts_at->gt($from)) {
            $from = $subscription->starts_at;
        }

        // Занятие считается проведённым, только если оно завершено, длилось
        // дольше 5 минут и кроме учителя был хотя бы один участник
        // (participant_count включает учителя). Случайные запуски пустой
        // комнаты и перезапуски лимит не расходуют.
        return MeetingSession::where('user_id', $user->id)
            ->where('started_at', '>=', $from)
            ->where('status', 'completed')
            ->where('participant_count', '>=', 2)
            ->whereNotNull('ended_at')
            ->when(
                DB::getDriverName() === 'sqlite',
                fn($query) => $query->whereRaw(
                    '(julianday(ended_at) - julianday(started_at)) * 86400 > ?',
                    [self::MIN_LESSON_SECONDS]
                ),
                fn($query) => $query->whereRaw(
                    'TIMESTAMPDIFF(SECOND, started_at, ended_at) > ?',
                    [self::MIN_LESSON_SECONDS]
                )
            )
            ->count();
    }
}
