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
     * $price = 0 — тариф предоставлен без оплаты: учитель увидит пометку
     * «Предоставлен бесплатно» вместо цены и кнопки оплаты.
     */
    public static function activate(
        User $user,
        Tariff $tariff,
        ?SubscriptionPayment $payment = null,
        ?int $days = null,
        bool $unlimited = false,
        ?string $comment = null,
        ?float $price = null,
    ): Subscription {
        return DB::transaction(function () use ($user, $tariff, $payment, $days, $unlimited, $comment, $price) {
            // Закрываем предыдущие активные подписки
            $user->subscriptions()
                ->where('status', Subscription::STATUS_ACTIVE)
                ->update(['status' => Subscription::STATUS_CANCELLED, 'cancelled_at' => now()]);

            $subscription = Subscription::create([
                'user_id' => $user->id,
                'tariff_id' => $tariff->id,
                'status' => Subscription::STATUS_ACTIVE,
                'price' => $price ?? $payment?->amount ?? $tariff->price,
                'starts_at' => now(),
                // Бесплатный тариф и ручная бессрочная выдача — без даты окончания,
                // платный — на период тарифа
                'ends_at' => ($tariff->isFree() || $unlimited) ? null : now()->addDays($days ?? $tariff->period_days),
                'comment' => $comment,
            ]);

            $payment?->update(['subscription_id' => $subscription->id]);

            unset(self::$canStartCache[$user->id]);

            return $subscription;
        });
    }

    /**
     * Планирует переключение на тариф после окончания текущего оплаченного
     * периода. До даты $startsAt действуют условия текущей подписки —
     * оплаченные лимиты не сгорают при даунгрейде.
     */
    public static function scheduleTariffChange(User $user, Tariff $tariff, \Illuminate\Support\Carbon $startsAt): Subscription
    {
        return DB::transaction(function () use ($user, $tariff, $startsAt) {
            // Отменяем ранее запланированные переключения — актуально только последнее
            $user->subscriptions()
                ->scheduled()
                ->update(['status' => Subscription::STATUS_CANCELLED, 'cancelled_at' => now()]);

            $subscription = Subscription::create([
                'user_id' => $user->id,
                'tariff_id' => $tariff->id,
                'status' => Subscription::STATUS_ACTIVE,
                'price' => $tariff->price,
                'starts_at' => $startsAt,
                'ends_at' => $tariff->isFree() ? null : $startsAt->copy()->addDays($tariff->period_days),
                'comment' => 'Переключение по окончании оплаченного периода',
            ]);

            unset(self::$canStartCache[$user->id]);

            return $subscription;
        });
    }

    /**
     * Продлевает активную подписку на тот же тариф (после успешной оплаты).
     * Срок продления берётся из оплаченного периода платежа (месяц или год).
     */
    public static function extend(Subscription $subscription, ?SubscriptionPayment $payment = null): Subscription
    {
        $base = $subscription->ends_at && $subscription->ends_at->isFuture()
            ? $subscription->ends_at
            : now();

        $subscription->update([
            'ends_at' => $base->copy()->addDays($payment?->period_days ?? $subscription->tariff->period_days),
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

        // Оплата отменяет запланированный даунгрейд: пользователь решил продолжить
        // платный тариф (activate() и так отменяет все активные, а extend() — нет)
        $user->subscriptions()
            ->scheduled()
            ->update(['status' => Subscription::STATUS_CANCELLED, 'cancelled_at' => now()]);

        $current = $user->activeSubscription();

        // Оплата того же тарифа = продление, иного тарифа = переключение
        if ($current && $current->tariff_id === $payment->tariff_id) {
            $subscription = self::extend($current, $payment);
        } else {
            $subscription = self::activate($user, $payment->tariff, $payment, days: $payment->period_days);
        }

        // При продлении сбрасываем флаг предупреждения, чтобы оно пришло и в новом периоде
        $subscription->update(['expiring_notified_at' => null]);

        self::storeSavedPaymentMethod($payment);

        $user->notify(new \App\Notifications\SubscriptionPaid(
            $subscription->tariff->name,
            $payment->amount,
            $subscription->ends_at,
        ));

        return $subscription;
    }

    /**
     * Если учитель попросил сохранить карту для автопродления и ЮKassa
     * подтвердила сохранение — привязываем способ оплаты и включаем автопродление.
     */
    protected static function storeSavedPaymentMethod(SubscriptionPayment $payment): void
    {
        if (empty($payment->meta['save_method'])) {
            return;
        }

        $method = $payment->meta['status_response']['payment_method'] ?? null;

        if (($method['saved'] ?? false) && !empty($method['id'])) {
            $payment->user->update([
                'yookassa_payment_method_id' => $method['id'],
                'payment_method_title' => $method['title'] ?? 'Банковская карта',
                'auto_renew' => true,
            ]);
        }
    }

    /**
     * Автопродление подписки по сохранённой карте. Вызывается планировщиком
     * незадолго до окончания оплаченного периода.
     * Возвращает итоговый статус платежа ЮKassa или null, если списание не выполнялось.
     */
    public static function attemptAutoRenewal(Subscription $subscription): ?string
    {
        $user = $subscription->user;
        $tariff = $subscription->tariff;

        if (!$user || !$user->auto_renew || !$user->yookassa_payment_method_id || $tariff->isFree()) {
            return null;
        }

        // Не больше одной попытки на период: если недавно уже было автосписание
        // (в любом статусе) — не повторяем, чтобы не задвоить платёж
        $recentAttempt = SubscriptionPayment::where('user_id', $user->id)
            ->where('meta->auto_renew', true)
            ->where('created_at', '>=', now()->subDays(2))
            ->exists();

        if ($recentAttempt) {
            return null;
        }

        // Период и сумма — как в последнем оплаченном платеже (месяц или год),
        // цена — актуальная цена тарифа на момент списания
        $lastPaid = SubscriptionPayment::where('user_id', $user->id)
            ->where('tariff_id', $tariff->id)
            ->where('status', SubscriptionPayment::STATUS_PAID)
            ->latest('paid_at')
            ->first();

        $yearly = ($lastPaid?->period_days ?? 30) >= 365 && $tariff->hasYearly();

        $payment = SubscriptionPayment::create([
            'user_id' => $user->id,
            'tariff_id' => $tariff->id,
            'subscription_id' => $subscription->id,
            'amount' => $yearly ? $tariff->yearly_price : $tariff->price,
            'period_days' => $yearly ? 365 : $tariff->period_days,
            'status' => SubscriptionPayment::STATUS_PENDING,
            'gateway' => 'yookassa',
            'meta' => ['auto_renew' => true],
        ]);

        $status = YooKassaService::createRecurringPayment($payment, $user->yookassa_payment_method_id);

        if ($status === 'succeeded') {
            self::applyPaidPayment($payment);
        } elseif ($status === 'canceled') {
            $payment->update(['status' => SubscriptionPayment::STATUS_FAILED]);
            $user->notify(new \App\Notifications\SubscriptionAutoRenewFailed($tariff->name));
        }
        // pending / waiting_for_capture — дообработает вебхук ЮKassa

        return $status;
    }

    /**
     * Кэш проверки на время запроса (кнопки в таблицах дёргают её на каждую строку).
     */
    private static array $canStartCache = [];

    /**
     * Можно ли пользователю запустить новое занятие.
     * Возвращает null, если можно, иначе — текст причины блокировки.
     */
    public static function canStartLesson(User $user): ?string
    {
        if (array_key_exists($user->id, self::$canStartCache)) {
            return self::$canStartCache[$user->id];
        }

        return self::$canStartCache[$user->id] = self::resolveCanStartLesson($user);
    }

    public static function flushCanStartCache(): void
    {
        self::$canStartCache = [];
    }

    private static function resolveCanStartLesson(User $user): ?string
    {
        $subscription = $user->activeSubscription();

        if (!$subscription) {
            $last = $user->subscriptions()->with('tariff')->latest('starts_at')->first();

            return $last
                ? "Срок действия тарифа «{$last->tariff->name}» истёк. Продлите подписку, чтобы проводить занятия."
                : 'Нет активной подписки. Выберите тариф, чтобы проводить занятия.';
        }

        $tariff = $subscription->tariff;

        if ($tariff->lessons_per_month !== null) {
            $used = self::lessonsUsedThisPeriod($user);

            if ($used >= $tariff->lessons_per_month) {
                return "Лимит занятий по тарифу «{$tariff->name}» исчерпан ({$used} из {$tariff->lessons_per_month}). "
                    . 'Перейдите на тариф выше, чтобы продолжить занятия в этом периоде.';
            }
        }

        return null;
    }

    /**
     * Лимиты тарифа для создаваемой BBB-встречи. Применяются сервером конференций:
     * max_participants и duration BBB контролирует сам, запись отключается,
     * если тариф не включает хранение записей.
     */
    public static function meetingLimits(User $user): array
    {
        $tariff = $user->activeSubscription()?->tariff;

        return [
            'max_participants' => $tariff?->max_participants,
            'duration_minutes' => $tariff?->max_duration_minutes,
            'record_allowed' => (bool) $tariff?->recording_retention_days,
        ];
    }

    /**
     * Число проведённых занятий в текущем периоде подписки
     * (для бесплатного тарифа / без подписки — с начала календарного месяца).
     */
    public static function lessonsUsedThisPeriod(User $user): int
    {
        $subscription = $user->activeSubscription();

        $from = now()->startOfMonth();

        if ($subscription && $subscription->ends_at) {
            // Лимит занятий — месячный. Для оплаченных подписок (в том числе
            // годовых) окно считаем 30-дневными циклами от даты начала подписки.
            $cycle = intdiv((int) $subscription->starts_at->diffInDays(now()), 30);
            $from = $subscription->starts_at->copy()->addDays($cycle * 30);
        } elseif ($subscription && $subscription->starts_at->gt($from)) {
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
