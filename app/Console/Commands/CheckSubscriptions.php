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

    /**
     * За сколько дней до окончания выполнять автосписание.
     */
    const AUTO_RENEW_DAYS = 1;

    public function handle(): int
    {
        // 0. Автопродление: подписки с включённым автосписанием, заканчивающиеся
        // в ближайшие сутки — списываем с сохранённой карты
        $renewals = Subscription::where('status', Subscription::STATUS_ACTIVE)
            ->whereNotNull('ends_at')
            ->where('ends_at', '>', now())
            ->where('ends_at', '<=', now()->addDays(self::AUTO_RENEW_DAYS))
            ->whereHas('user', fn($q) => $q->where('auto_renew', true)->whereNotNull('yookassa_payment_method_id'))
            ->with(['user', 'tariff'])
            ->get();

        $renewed = 0;
        foreach ($renewals as $subscription) {
            try {
                if (\App\Services\SubscriptionService::attemptAutoRenewal($subscription) === 'succeeded') {
                    $renewed++;
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error("[Subscriptions] auto-renew failed for subscription {$subscription->id}: " . $e->getMessage());
            }
        }

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
            $user = $subscription->user;

            if (!$user) {
                continue;
            }

            // С включённым автопродлением — предупреждаем о списании,
            // без него — о том, что подписка скоро закончится
            if ($user->auto_renew && $user->yookassa_payment_method_id && !$subscription->tariff->isFree()) {
                $lastPaid = \App\Models\SubscriptionPayment::where('user_id', $user->id)
                    ->where('tariff_id', $subscription->tariff_id)
                    ->where('status', \App\Models\SubscriptionPayment::STATUS_PAID)
                    ->latest('paid_at')
                    ->first();
                $yearly = ($lastPaid?->period_days ?? 30) >= 365 && $subscription->tariff->hasYearly();

                $user->notify(new \App\Notifications\SubscriptionAutoRenewNotice(
                    $subscription->tariff->name,
                    $yearly ? $subscription->tariff->yearly_price : $subscription->tariff->price,
                    $subscription->ends_at,
                ));
            } else {
                $user->notify(new SubscriptionExpiringSoon(
                    $subscription->tariff->name,
                    $subscription->ends_at,
                ));
            }

            $subscription->update(['expiring_notified_at' => now()]);
        }

        // 3. Зависшие платежи «Ожидает оплаты»: запись создаётся при уходе на страницу
        // оплаты, и если оплату не завершили — остаётся навсегда. Сверяем со шлюзом.
        $stale = \App\Models\SubscriptionPayment::where('status', \App\Models\SubscriptionPayment::STATUS_PENDING)
            ->where('created_at', '<=', now()->subHour())
            ->get();

        foreach ($stale as $payment) {
            if (!$payment->gateway_order_id) {
                // Платёж даже не зарегистрирован в шлюзе — оплату не начинали
                $payment->update(['status' => \App\Models\SubscriptionPayment::STATUS_FAILED]);
                continue;
            }

            $status = \App\Services\YooKassaService::fetchStatus($payment);

            if ($status === 'succeeded') {
                // Оплата прошла, но ни возврат, ни вебхук не сработали
                \App\Services\SubscriptionService::applyPaidPayment($payment);
            } elseif ($status === 'canceled') {
                $payment->update(['status' => \App\Models\SubscriptionPayment::STATUS_FAILED]);
            }
            // pending / ошибка запроса — проверим в следующий час
        }

        $this->info("Автопродлено: {$renewed}, истекло: {$expired->count()}, предупреждено: {$expiring->count()}, зависших платежей обработано: {$stale->count()}");

        return self::SUCCESS;
    }
}
