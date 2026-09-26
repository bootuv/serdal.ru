<?php

namespace App\Services;

use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\Tariff;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Оформление подписки учителем: выбор и продление тарифа, докупка занятий,
 * привязка/отвязка способа оплаты, автопродление.
 * Используется в Cabinet\Teacher\Subscription и Onboarding.
 *
 * Методы действий не показывают уведомлений — возвращают результат ['status' => …, …],
 * а тексты подбирает экран.
 */
class SubscriptionCheckoutService
{
    /** За сколько дней до окончания подписки показывать кнопку «Продлить». */
    const RENEW_WINDOW_DAYS = 14;

    /** Способы оплаты (ключи — типы payment_method_data ЮKassa), СБП — приоритетом. */
    const PAYMENT_METHODS = [
        'sbp' => ['icon' => 'sbp.svg', 'title' => 'СБП', 'subtitle' => 'Приложение вашего банка — рекомендуем'],
        'sberbank' => ['icon' => 'sberpay.svg', 'title' => 'SberPay', 'subtitle' => 'Быстрая оплата для клиентов Сбера'],
        'tinkoff_bank' => ['icon' => 'tpay.svg', 'title' => 'T-Pay', 'subtitle' => 'Приложение Т-Банка'],
        'bank_card' => ['icon' => 'card.svg', 'title' => 'Банковская карта', 'subtitle' => 'Любой банк'],
        'yoo_money' => ['icon' => 'yoomoney.png', 'title' => 'ЮMoney', 'subtitle' => 'Кошелёк или привязанная карта'],
    ];

    /** Способы оплаты; $only — оставить только эти ключи. */
    public static function paymentMethods(?array $only = null): array
    {
        $methods = self::PAYMENT_METHODS;

        return $only === null ? $methods : array_intersect_key($methods, array_flip($only));
    }

    /** Логотип способа оплаты (тип ЮKassa: sbp, bank_card…) — адрес картинки или null. */
    public static function paymentLogo(?string $type): ?string
    {
        $icon = self::PAYMENT_METHODS[$type]['icon'] ?? null;

        return $icon ? asset('images/payment/' . $icon) : null;
    }

    /**
     * Тип сохранённого способа оплаты учителя (у пользователя хранится только id и название):
     * берём из платежа, в ответе на который ЮKassa прислала этот способ.
     */
    public static function savedMethodType(User $user): ?string
    {
        if (! $user->yookassa_payment_method_id) {
            return null;
        }

        $payment = $user->subscriptionPayments()
            ->where('status', \App\Models\SubscriptionPayment::STATUS_PAID)
            ->latest('id')
            ->limit(50)
            ->get()
            ->first(fn ($p) => ($p->meta['status_response']['payment_method']['id'] ?? null) === $user->yookassa_payment_method_id);

        return $payment?->meta['status_response']['payment_method']['type'] ?? null;
    }

    /** Тариф нельзя оформить: удалён (мягко) или снят с продажи. */
    public static function tariffUnavailable(int|string|null $tariffId): bool
    {
        $tariff = Tariff::withTrashed()->find($tariffId);

        return !$tariff || $tariff->trashed() || !$tariff->is_active;
    }

    /**
     * Всё, что нужно странице подписки: текущий/запланированный/истёкший тариф,
     * лимиты периода, докупленные занятия, тарифы и последние платежи.
     */
    public static function overview(User $user): array
    {
        $subscription = $user->activeSubscription();
        $tariffs = Tariff::active()->get();

        // Была подписка, но срок вышел (или крон ещё не пометил её истёкшей)
        $expired = $subscription ? null : $user->subscriptions()
            ->whereIn('status', [Subscription::STATUS_EXPIRED, Subscription::STATUS_ACTIVE])
            ->with('tariff')
            ->latest('starts_at')
            ->first();

        // Самое ожидаемое действие со страницы тарифов:
        // истёк срок — «Продлить»; нет подписки — бесплатный «Старт»;
        // активная подписка — популярный тариф дороже текущего (апгрейд)
        $primaryTariffId = match (true) {
            $expired !== null => $expired->tariff_id,
            $subscription === null => $tariffs->first(fn(Tariff $t) => $t->isFree())?->id,
            default => $tariffs->first(fn(Tariff $t) => $t->is_popular && $t->price > $subscription->tariff->price)?->id,
        };

        return [
            'subscription' => $subscription,
            'scheduled' => $user->scheduledSubscription(),
            'expired' => $expired,
            'primaryTariffId' => $primaryTariffId,
            // «Продлить» показываем незадолго до окончания, а не сразу после оплаты
            'showRenew' => $subscription && !$subscription->tariff->isFree() && $subscription->ends_at
                && now()->diffInDays($subscription->ends_at, false) <= self::RENEW_WINDOW_DAYS,
            'refundProcessingDays' => \App\Support\OfferSettings::offer()['refund_processing_days'],
            'hasYearly' => $tariffs->contains(fn(Tariff $t) => $t->hasYearly()),
            'tariffs' => $tariffs,
            'lessonsUsed' => SubscriptionService::lessonsUsedThisPeriod($user),
            'limitReached' => SubscriptionService::lessonLimitReached($user),
            'periodResetsAt' => SubscriptionService::periodResetsAt($user),
            'extraBalance' => (int) $user->extra_lessons_balance,
            'canBuyExtra' => SubscriptionService::canBuyExtraLessons($user),
            'payments' => self::payments($user, 20),
        ];
    }

    /** Платежи учителя, новые сверху. Неудавшиеся не показываем — они остаются в админке для сверки. */
    public static function payments(User $user, ?int $limit = null): Collection
    {
        return SubscriptionPayment::where('user_id', $user->id)
            ->where('status', '!=', SubscriptionPayment::STATUS_FAILED)
            ->with('tariff')
            ->latest()
            ->when($limit, fn($q) => $q->take($limit))
            ->get();
    }

    /** Ближайший тариф дороже текущего (для подсказки об апгрейде при докупке). */
    public static function nextTariffUp(User $user): ?Tariff
    {
        $currentPrice = $user->activeSubscription()?->tariff->price ?? 0;

        return Tariff::active()
            ->where('price', '>', $currentPrice)
            ->whereNotNull('lessons_per_month')
            ->orderBy('price')
            ->first();
    }

    /** Тариф выше, если сумма докупки сопоставима с доплатой за него; иначе null. */
    public static function upgradeHint(User $user, int $total): ?Tariff
    {
        $upgrade = self::nextTariffUp($user);

        return $upgrade && $total >= $upgrade->price - ($user->activeSubscription()?->tariff->price ?? 0)
            ? $upgrade
            : null;
    }

    /**
     * Смена тарифа на бесплатный при действующем оплаченном периоде откладывается
     * до его окончания. Возвращает дату переключения или null, если переключение сразу.
     */
    public static function deferredUntil(User $user, Tariff $tariff): ?\Illuminate\Support\Carbon
    {
        $current = $user->activeSubscription();

        return $tariff->isFree() && $current && !$current->tariff->isFree() && $current->ends_at?->isFuture()
            ? $current->ends_at
            : null;
    }

    /** Сумма и срок платежа за тариф: [₽, дней] — на месяц (период тарифа) или год. */
    public static function paymentTerms(Tariff $tariff, bool $yearly): array
    {
        $yearly = $yearly && $tariff->hasYearly();

        return [(float) ($yearly ? $tariff->yearly_price : $tariff->price), (int) ($yearly ? 365 : $tariff->period_days)];
    }

    /**
     * Перенос остатка текущего платного тарифа при переходе на $tariff — для строки в окне смены тарифа.
     * Считается так же, как при оплате (SubscriptionService::carryOver). Null — переноса не будет.
     */
    public static function carryOverPreview(User $user, Tariff $tariff, bool $yearly): ?array
    {
        [$amount, $days] = self::paymentTerms($tariff, $yearly);

        return SubscriptionService::carryOver($user, $tariff, $amount, $days);
    }

    /** Ожидающий платёж за тариф на месяц (период тарифа) или год. */
    public static function createTariffPayment(User $user, Tariff $tariff, bool $yearly, ?array $meta = null): SubscriptionPayment
    {
        [$amount, $days] = self::paymentTerms($tariff, $yearly);

        return SubscriptionPayment::create([
            'user_id' => $user->id,
            'tariff_id' => $tariff->id,
            'amount' => $amount,
            'period_days' => $days,
            'status' => SubscriptionPayment::STATUS_PENDING,
            'gateway' => 'yookassa',
            'meta' => $meta,
        ]);
    }

    /**
     * Выбор тарифа: бесплатный активируется сразу (или по окончании оплаченного периода);
     * платный — списание с сохранённого способа в один клик, либо платёж с уводом на платёжную страницу.
     *
     * Статусы: unavailable, already_active, already_scheduled, scheduled (subscription, tariff),
     * activated (tariff), not_configured, paid (tariff), processing, redirect (url), failed (error).
     */
    public static function selectTariff(User $user, int $tariffId, bool $yearly = false, bool $saveMethod = false, bool $autoRenew = false, ?string $method = null): array
    {
        // Сохранение возможно только для способов с включённой привязкой
        // и только когда магазину разрешены автоплатежи — иначе ЮKassa
        // отклонит платёж («This store can't make recurring payments»)
        $saveMethod = $saveMethod
            && YooKassaService::recurringEnabled()
            && in_array($method, YooKassaService::savableMethods(), true);

        if (self::tariffUnavailable($tariffId)) {
            return ['status' => 'unavailable'];
        }

        $tariff = Tariff::active()->findOrFail($tariffId);
        $subscription = $user->activeSubscription();

        if ($tariff->isFree()) {
            if ($subscription && $subscription->tariff_id === $tariff->id) {
                return ['status' => 'already_active', 'tariff' => $tariff];
            }

            // Даунгрейд с оплаченного тарифа: переключаем только после окончания
            // оплаченного периода, чтобы оплаченные лимиты не сгорали
            if ($subscription && !$subscription->tariff->isFree() && $subscription->ends_at?->isFuture()) {
                if ($user->scheduledSubscription()?->tariff_id === $tariff->id) {
                    return ['status' => 'already_scheduled', 'tariff' => $tariff];
                }

                SubscriptionService::scheduleTariffChange($user, $tariff, $subscription->ends_at);

                return ['status' => 'scheduled', 'tariff' => $tariff, 'subscription' => $subscription];
            }

            SubscriptionService::activate($user, $tariff);

            return ['status' => 'activated', 'tariff' => $tariff];
        }

        if (!YooKassaService::isConfigured()) {
            return ['status' => 'not_configured', 'tariff' => $tariff];
        }

        $payment = self::createTariffPayment(
            $user,
            $tariff,
            $yearly,
            $saveMethod ? ['save_method' => true, 'auto_renew_opt_in' => $autoRenew] : null,
        );

        return self::charge($user, $payment, $saveMethod, $method) + ['tariff' => $tariff];
    }

    /**
     * Докупка занятий: платёж на $quantity занятий. Подписка не меняется —
     * после оплаты занятия зачисляются на баланс.
     *
     * Статусы: unavailable (configured), invalid_quantity (max), paid (quantity), processing, redirect (url), failed (error).
     */
    public static function buyExtraLessons(User $user, int $quantity, ?string $method = null): array
    {
        if (!SubscriptionService::canBuyExtraLessons($user)) {
            return ['status' => 'unavailable', 'configured' => YooKassaService::isConfigured()];
        }

        $max = SubscriptionService::extraLessonsMax();
        if ($quantity < 1 || $quantity > $max) {
            return ['status' => 'invalid_quantity', 'max' => $max];
        }

        $payment = SubscriptionService::createExtraLessonsPayment($user, $quantity);

        return self::charge($user, $payment, false, $method) + ['quantity' => $quantity];
    }

    /**
     * Привязка способа оплаты без покупки: проверочный платёж на 1 ₽ с сохранением;
     * рубль возвращается сразу после подтверждения.
     *
     * Статусы: already_bound, disabled, no_tariffs, redirect (url), failed (error).
     */
    public static function bindCard(User $user, ?string $method = null): array
    {
        if ($user->yookassa_payment_method_id) {
            return ['status' => 'already_bound'];
        }

        if (!YooKassaService::recurringEnabled()) {
            return ['status' => 'disabled'];
        }

        // Платёж требует тариф (FK): берём текущий или первый платный — на подписку не влияет
        $tariff = $user->activeSubscription()?->tariff;
        if (!$tariff || $tariff->isFree()) {
            $tariff = Tariff::active()->where('price', '>', 0)->first();
        }

        if (!$tariff) {
            return ['status' => 'no_tariffs'];
        }

        $payment = SubscriptionPayment::create([
            'user_id' => $user->id,
            'tariff_id' => $tariff->id,
            'amount' => 1,
            'period_days' => $tariff->period_days,
            'status' => SubscriptionPayment::STATUS_PENDING,
            'gateway' => 'yookassa',
            'meta' => ['card_binding' => true, 'save_method' => true],
        ]);

        // Только методы с включённой привязкой — иначе ЮKassa вернёт
        // «This store can't make recurring payments»
        $savable = YooKassaService::savableMethods();
        if (!in_array($method, $savable, true)) {
            $method = $savable[0];
        }

        return self::redirectToGateway($payment, true, $method);
    }

    /** Отвязывает сохранённый способ оплаты и выключает автопродление. */
    public static function removePaymentMethod(User $user): array
    {
        $user->update([
            'yookassa_payment_method_id' => null,
            'payment_method_title' => null,
            'auto_renew' => false,
        ]);

        return ['status' => 'removed'];
    }

    /**
     * Включает/выключает автопродление по сохранённому способу оплаты.
     * Статусы: no_method, enabled, disabled.
     */
    public static function toggleAutoRenew(User $user): array
    {
        if (!$user->yookassa_payment_method_id) {
            return ['status' => 'no_method'];
        }

        $user->update(['auto_renew' => !$user->auto_renew]);

        return ['status' => $user->auto_renew ? 'enabled' : 'disabled'];
    }

    /**
     * Оплата платежа: сохранённый способ — списание в один клик; не прошло или способа нет —
     * платёжная страница ЮKassa.
     */
    protected static function charge(User $user, SubscriptionPayment $payment, bool $saveMethod, ?string $method): array
    {
        if ($user->yookassa_payment_method_id) {
            $status = YooKassaService::createRecurringPayment($payment, $user->yookassa_payment_method_id);

            if ($status === 'succeeded') {
                SubscriptionService::applyPaidPayment($payment);

                return ['status' => 'paid', 'method_title' => $user->payment_method_title];
            }

            if ($status === 'pending' || $status === 'waiting_for_capture') {
                return ['status' => 'processing'];
            }

            // Списание не прошло — отправляем на обычную платёжную страницу
        }

        return self::redirectToGateway($payment, $saveMethod, $method);
    }

    protected static function redirectToGateway(SubscriptionPayment $payment, bool $saveMethod, ?string $method): array
    {
        try {
            $url = YooKassaService::createPayment(
                $payment,
                route('subscription.payment.return', $payment),
                savePaymentMethod: $saveMethod,
                methodType: $method,
            );
        } catch (\Throwable $e) {
            $payment->update(['status' => SubscriptionPayment::STATUS_FAILED]);

            return ['status' => 'failed', 'error' => $e->getMessage()];
        }

        return ['status' => 'redirect', 'url' => $url];
    }
}
