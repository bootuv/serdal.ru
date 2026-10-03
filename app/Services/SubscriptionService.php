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
     * Цена одного дополнительного занятия сверх лимита тарифа по умолчанию (₽)
     * и максимум за одну покупку. Переопределяются в настройках админки.
     */
    const EXTRA_LESSON_PRICE = 100;
    const EXTRA_LESSONS_MAX = 10;

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
     * Ручное назначение тарифа администратором (карточка учителя в админке): заменяет текущую подписку и сообщает учителю о новом тарифе.
     * $days = null — срок тарифа по умолчанию; $unlimited — без даты окончания; $free — без оплаты (цена-снимок 0).
     * Бесплатный тариф всегда бессрочный. Комментарий виден только администраторам.
     */
    public static function assignByAdmin(User $user, Tariff $tariff, User $admin, ?int $days = null, bool $unlimited = false, bool $free = false, ?string $note = null): Subscription
    {
        $note = trim((string) $note);
        $subscription = self::activate(
            $user,
            $tariff,
            days: $unlimited || $tariff->isFree() ? null : ($days ?: $tariff->period_days),
            unlimited: $unlimited && ! $tariff->isFree(),
            comment: 'Назначена администратором: ' . $admin->name . ($note !== '' ? ' · ' . $note : ''),
            price: $free && ! $tariff->isFree() ? 0 : null,
        );

        $user->notify(new \App\Notifications\SubscriptionAssigned($tariff->name, $subscription->ends_at, $subscription->isComplimentary()));

        return $subscription;
    }

    /**
     * Администратор начисляет учителю занятия на баланс (без оплаты). Расходуются как докупленные:
     * после лимита тарифа, а без действующего тарифа — на каждое проведённое занятие.
     * Так учитель может провести занятие, пока не продлил тариф.
     */
    public static function grantLessonsByAdmin(User $user, int $lessons, User $admin, ?string $note = null): \App\Models\LessonGrant
    {
        $note = trim((string) $note);

        $grant = DB::transaction(function () use ($user, $lessons, $admin, $note) {
            $locked = User::whereKey($user->id)->lockForUpdate()->first();
            $locked->update(['extra_lessons_balance' => (int) $locked->extra_lessons_balance + $lessons]);

            return \App\Models\LessonGrant::create([
                'user_id' => $user->id,
                'admin_id' => $admin->id,
                'lessons' => $lessons,
                'note' => $note !== '' ? $note : null,
            ]);
        });

        unset(self::$canStartCache[$user->id]);
        $user->refresh();
        $user->notify(new \App\Notifications\LessonsGranted($lessons, (int) $user->extra_lessons_balance, ! $user->activeSubscription()));

        return $grant;
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
    public static function applyPaidPayment(SubscriptionPayment $payment): ?Subscription
    {
        $payment->update([
            'status' => SubscriptionPayment::STATUS_PAID,
            'paid_at' => $payment->paid_at ?? now(),
        ]);

        $user = $payment->user;

        // Докупка занятий: подписку не трогаем, только пополняем баланс
        if ($payment->isExtraLessons()) {
            return self::applyExtraLessonsPayment($payment);
        }

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
            // Неиспользованный остаток прошлого платного тарифа добавляется к сроку нового
            $periodDays = (int) ($payment->period_days ?: $payment->tariff->period_days);
            $carry = self::carryOver($user, $payment->tariff, (float) $payment->amount, $periodDays);
            if ($carry) {
                $payment->update(['meta' => array_merge($payment->meta ?? [], ['carry_over' => [
                    'from' => $carry['from']->id,
                    'value' => $carry['value'],
                    'days' => $carry['days'],
                ]])]);
            }

            $subscription = self::activate(
                $user,
                $payment->tariff,
                $payment,
                days: $periodDays + ($carry['days'] ?? 0),
                comment: $carry ? 'Остаток тарифа «' . $carry['name'] . '» добавлен к новому: +' . plural_ru($carry['days'], 'день', 'дня', 'дней') : null,
            );
        }

        // При продлении сбрасываем флаг предупреждения, чтобы оно пришло и в новом периоде
        $subscription->update(['expiring_notified_at' => null]);

        self::storeSavedPaymentMethod($payment);

        $user->notify(new \App\Notifications\SubscriptionPaid(
            $subscription->tariff->name,
            $payment->amount,
            $subscription->ends_at,
        ));

        $days = (int) ($payment->period_days ?: $subscription->tariff->period_days);
        self::notifyAdminsAboutPayment($payment, 'тариф «' . $subscription->tariff->name . '» '
            . ($days >= 365 ? 'на год' : 'на ' . plural_ru($days, 'день', 'дня', 'дней')));

        // Партнёрская программа: бонусы за первую оплату приглашённого учителя
        ReferralService::rewardForPayment($payment);

        return $subscription;
    }

    /**
     * Перенос остатка при смене платного тарифа на другой платный (оплата иного тарифа при действующей
     * оплаченной подписке). Неиспользованная часть оплаченного срока пересчитывается по стоимости в дни нового тарифа:
     *
     *   остаток, ₽ = оплачено, ₽ × оставшееся время / весь срок подписки (starts_at → ends_at)
     *   дни        = ⌊ остаток, ₽ / сумма нового платежа × дней в новом платеже ⌋
     *
     * «Оплачено» — оплаченные (не возвращённые) платежи за тариф, привязанные к подписке, плюс остаток,
     * перенесённый в неё с прошлого тарифа; если платежей нет (назначена администратором) — цена-снимок подписки.
     * Null — переносить нечего: нет подписки, тот же тариф (это продление), новый или текущий тариф бесплатный,
     * подписка бессрочная или предоставлена бесплатно, остаток меньше дня нового тарифа.
     * Используется и при оплате (applyPaidPayment), и в окне смены тарифа — строка в окне совпадает с начислением.
     *
     * @return array{from: Subscription, name: string, left_days: int, value: float, days: int}|null
     */
    public static function carryOver(User $user, Tariff $tariff, float $amount, int $periodDays): ?array
    {
        $current = $user->activeSubscription();

        if (! $current || $current->tariff_id === $tariff->id || $tariff->isFree() || $current->tariff->isFree()
            || ! $current->ends_at || ! $current->ends_at->isFuture() || $amount <= 0 || $periodDays <= 0) {
            return null;
        }

        $length = $current->starts_at->diffInSeconds($current->ends_at);
        $left = now()->diffInSeconds($current->ends_at);
        $paid = self::paidValue($current);

        if ($paid <= 0 || $length <= 0) {
            return null;
        }

        $value = round($paid * min(1, $left / $length), 2);
        $days = (int) floor($value / $amount * $periodDays);

        if ($days < 1) {
            return null;
        }

        return [
            'from' => $current,
            'name' => $current->tariff->name,
            // Как «осталось N дней» на карточке тарифа
            'left_days' => (int) now()->diffInDays($current->ends_at),
            'value' => $value,
            'days' => $days,
        ];
    }

    /** Сколько заплачено за подписку, ₽: оплаченные платежи за тариф и перенесённый в неё остаток. */
    private static function paidValue(Subscription $subscription): float
    {
        $payments = $subscription->payments()
            ->where('period_days', '>', 0)
            ->get()
            ->reject(fn (SubscriptionPayment $p) => $p->isExtraLessons() || ! empty($p->meta['card_binding']));

        if ($payments->isEmpty()) {
            return (float) $subscription->price;
        }

        return (float) $payments->where('status', SubscriptionPayment::STATUS_PAID)->sum('amount')
            + (float) $payments->sum(fn (SubscriptionPayment $p) => (float) ($p->meta['carry_over']['value'] ?? 0));
    }

    /**
     * Что сделает возврат платежа за тариф: какую подписку и на сколько дней сократить.
     *
     * - Обычный платёж — подписка платежа минус оплаченный им период.
     * - Платёж, при оплате которого перенесли остаток прошлого тарифа, — минус оплаченный период; перенесённые дни
     *   остаются (их оплатил прошлый платёж), но пересчитываются по месячной цене тарифа, чтобы годовая скидка
     *   не доставалась без оплаты года.
     * - Платёж подписки, остаток которой перенесли в новый тариф, — новая подписка теряет перенесённые дни
     *   в доле возвращённых денег: остаётся ⌊ дни × max(0, остаток − возврат) / остаток ⌋.
     *
     * carry — платёж с отметкой переноса и её значения после возврата. Null — подписку менять не нужно.
     *
     * @return array{subscription: Subscription, days: int, carry: ?array{payment: SubscriptionPayment, value: float, days: int}}|null
     */
    public static function refundPlan(SubscriptionPayment $payment): ?array
    {
        if ($payment->isExtraLessons() || ! empty($payment->meta['card_binding'])) {
            return null;
        }

        // Остаток этой подписки перенесли в новый тариф — возврат забирает перенесённые дни
        if ($payment->subscription_id) {
            $moved = SubscriptionPayment::where('meta->carry_over->from', $payment->subscription_id)->latest('id')->first();

            if ($moved) {
                $target = $moved->subscription;
                $carry = $moved->meta['carry_over'];
                $value = (float) $carry['value'];
                $keepValue = max(0, $value - (float) $payment->amount);
                $keepDays = $value > 0 ? (int) floor((int) $carry['days'] * $keepValue / $value) : 0;

                return $target && $target->ends_at ? [
                    'subscription' => $target,
                    'days' => (int) $carry['days'] - $keepDays,
                    'carry' => ['payment' => $moved, 'value' => round($keepValue, 2), 'days' => $keepDays],
                ] : null;
            }
        }

        $subscription = $payment->subscription
            ?? $payment->user->subscriptions()->active()->where('tariff_id', $payment->tariff_id)->latest('starts_at')->first();

        // Бессрочные (бесплатные/подаренные) подписки не трогаем
        if (! $subscription || ! $subscription->ends_at) {
            return null;
        }

        $days = (int) $payment->period_days;
        $carry = null;

        if (! empty($payment->meta['carry_over'])) {
            $c = $payment->meta['carry_over'];
            $tariff = $payment->tariff;
            $keepDays = $tariff && ! $tariff->isFree() && $tariff->period_days > 0
                ? min((int) $c['days'], (int) floor((float) $c['value'] / (float) $tariff->price * $tariff->period_days))
                : 0;
            $days += (int) $c['days'] - $keepDays;
            $carry = ['payment' => $payment, 'value' => (float) $c['value'], 'days' => $keepDays];
        }

        return ['subscription' => $subscription, 'days' => $days, 'carry' => $carry];
    }

    /**
     * Корректирует подписку после возврата платежа: оплаченный возвращённым
     * платежом период вычитается из срока подписки (перенос остатка при смене
     * тарифа — см. refundPlan). Если срока не остаётся — подписка завершается
     * сразу. Возвращает скорректированную подписку или null.
     */
    public static function applyRefund(SubscriptionPayment $payment): ?Subscription
    {
        // Бонусы партнёрской программы за этот платёж списываются
        ReferralService::revokeForPayment($payment);

        // Возврат за докупленные занятия: списываем их с баланса (не ниже нуля)
        if ($payment->isExtraLessons()) {
            $user = $payment->user;
            $user->update([
                'extra_lessons_balance' => max(0, (int) $user->extra_lessons_balance - (int) $payment->extra_lessons),
            ]);
            unset(self::$canStartCache[$user->id]);

            return null;
        }

        $plan = self::refundPlan($payment);

        if (! $plan) {
            return null;
        }

        $subscription = $plan['subscription'];

        // Запоминаем, сколько перенесённых дней осталось, — чтобы следующий возврат не списал их повторно
        if ($plan['carry']) {
            $moved = $plan['carry']['payment'];
            $moved->update(['meta' => array_merge($moved->meta ?? [], ['carry_over' => array_merge($moved->meta['carry_over'], [
                'value' => $plan['carry']['value'],
                'days' => $plan['carry']['days'],
            ])])]);
        }

        if ($plan['days'] <= 0) {
            return $subscription->fresh();
        }

        $newEnd = $subscription->ends_at->copy()->subDays($plan['days']);

        if ($newEnd->isPast()) {
            $subscription->update(['ends_at' => now(), 'status' => Subscription::STATUS_EXPIRED]);
        } else {
            $subscription->update(['ends_at' => $newEnd]);
        }

        // Запланированное переключение (например, даунгрейд на бесплатный тариф)
        // сдвигаем на новую дату окончания, чтобы не было разрыва
        $scheduled = $payment->user->scheduledSubscription();
        if ($scheduled) {
            $shift = $scheduled->ends_at
                ? $scheduled->starts_at->diffInSeconds($scheduled->ends_at)
                : null;
            $newStart = $newEnd->isPast() ? now() : $newEnd;
            $scheduled->update([
                'starts_at' => $newStart,
                'ends_at' => $shift ? $newStart->copy()->addSeconds($shift) : null,
            ]);
        }

        unset(self::$canStartCache[$payment->user_id]);

        return $subscription->fresh();
    }

    /**
     * Название сохранённого способа оплаты, если ЮKassa не прислала title
     * (для карты title есть всегда, для СБП/SberPay/T-Pay — не гарантирован).
     */
    public static function paymentMethodFallbackTitle(?string $type): string
    {
        return match ($type) {
            'sbp' => 'СБП',
            'sberbank' => 'SberPay',
            'tinkoff_bank' => 'T-Pay',
            'yoo_money' => 'ЮMoney',
            'bank_card' => 'Банковская карта',
            default => 'Сохранённый способ оплаты',
        };
    }

    /**
     * Если учитель попросил сохранить способ оплаты (карту, счёт СБП, SberPay,
     * T-Pay, ЮMoney) и ЮKassa подтвердила сохранение — привязываем его.
     * Автопродление включается только при отдельном согласии
     * (meta.auto_renew_opt_in); уже включённое — не выключаем.
     */
    public static function storeSavedPaymentMethod(SubscriptionPayment $payment): void
    {
        if (empty($payment->meta['save_method'])) {
            return;
        }

        $method = $payment->meta['status_response']['payment_method'] ?? null;

        if (($method['saved'] ?? false) && !empty($method['id'])) {
            $user = $payment->user;
            $user->update([
                'yookassa_payment_method_id' => $method['id'],
                'payment_method_title' => $method['title'] ?? self::paymentMethodFallbackTitle($method['type'] ?? null),
                'auto_renew' => $user->auto_renew || !empty($payment->meta['auto_renew_opt_in']),
            ]);
        }
    }

    /**
     * Автопродление подписки по сохранённому способу оплаты. Вызывается планировщиком
     * незадолго до окончания оплаченного периода.
     * Возвращает итоговый статус платежа ЮKassa или null, если списание не выполнялось.
     */
    public static function attemptAutoRenewal(Subscription $subscription): ?string
    {
        $user = $subscription->user;
        $tariff = $subscription->tariff;

        // Удалённый или снятый с продажи тариф не продлеваем — пользователь
        // выберет новый тариф сам, а подписка истечёт с обычными уведомлениями
        if (!$user || !$user->auto_renew || !$user->yookassa_payment_method_id || $tariff->isFree()
            || $tariff->trashed() || !$tariff->is_active) {
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
            // Тариф закончился, но на балансе есть занятия (например, начислил администратор,
            // пока учитель не продлил тариф) — занятие можно провести за их счёт
            if ((int) $user->extra_lessons_balance > 0) {
                return null;
            }

            $last = $user->subscriptions()->with('tariff')->latest('starts_at')->first();

            return $last
                ? "Срок действия тарифа «{$last->tariff->name}» истёк. Продлите подписку, чтобы проводить занятия."
                : 'Нет активной подписки. Выберите тариф, чтобы проводить занятия.';
        }

        $tariff = $subscription->tariff;

        if ($tariff->lessons_per_month !== null) {
            $used = self::lessonsUsedThisPeriod($user);

            // Лимит тарифа исчерпан, но есть докупленные занятия — можно
            if ($used >= $tariff->lessons_per_month && (int) $user->extra_lessons_balance <= 0) {
                $resetsAt = self::periodResetsAt($user);
                $resetText = $resetsAt ? ' Лимит обновится ' . $resetsAt->format('d.m.Y') . '.' : '';

                // Докупить можно, только если платежи подключены — иначе не предлагаем
                $action = \App\Services\YooKassaService::isConfigured()
                    ? 'Докупите занятия или перейдите на тариф выше'
                    : 'Перейдите на тариф выше';

                return "Лимит занятий по тарифу «{$tariff->name}» исчерпан ({$used} из {$tariff->lessons_per_month}).{$resetText} "
                    . $action . ', чтобы продолжить занятия в этом периоде.';
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
        // Без действующего тарифа (занятие за счёт баланса) — условия последнего тарифа учителя
        $tariff = $user->activeSubscription()?->tariff
            ?? $user->subscriptions()->with('tariff')->latest('starts_at')->first()?->tariff;

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
        $from = self::periodStart($user);

        // Занятие считается проведённым, только если оно завершено, длилось
        // дольше 5 минут и кроме учителя был хотя бы один участник
        // (participant_count включает учителя). Случайные запуски пустой
        // комнаты и перезапуски лимит не расходуют. Занятия, проведённые
        // за счёт докупленных, лимит тарифа не расходуют.
        return MeetingSession::where('user_id', $user->id)
            ->where('started_at', '>=', $from)
            ->where('extra_lesson', false)
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
    /**
     * Лимит занятий тарифа в текущем периоде выбран полностью
     * (докупленные занятия здесь не учитываются).
     */
    public static function lessonLimitReached(User $user): bool
    {
        $tariff = $user->activeSubscription()?->tariff;

        if (!$tariff || $tariff->lessons_per_month === null) {
            return false;
        }

        return self::lessonsUsedThisPeriod($user) >= $tariff->lessons_per_month;
    }

    /**
     * После проведённого занятия: предупредить учителя, что занятия по тарифу заканчиваются (осталось 2 и меньше,
     * с учётом докупленных) или закончились. Каждый порог — один раз за период тарифа.
     */
    public static function notifyLessonsRunningOut(?User $user): void
    {
        if (! $user || $user->role !== User::ROLE_TUTOR) {
            return;
        }

        $summary = self::teacherSummary($user);
        if (($summary['limit'] ?? null) === null) {
            return;
        }

        $left = (int) $summary['left'] + (int) $summary['extra'];
        $threshold = match (true) {
            $left === 0 => 'out',
            $left <= 2 => 'low',
            default => null,
        };
        if ($threshold === null) {
            return;
        }

        $key = 'lessons-running-out:' . $user->id . ':' . self::periodStart($user)->timestamp . ':' . $threshold;
        if (\Illuminate\Support\Facades\Cache::add($key, true, now()->addDays(40))) {
            $user->notify(new \App\Notifications\LessonsRunningOut($left, (string) $summary['name'], $summary['resets'] ?? null));
        }
    }

    /**
     * Сводка тарифа для кабинета учителя: какой тариф, до какого числа, сколько занятий осталось и лимиты.
     * warning — исключение, которое нужно подсветить (занятия на исходе, подписка скоро закончится); null — всё спокойно.
     */
    public static function teacherSummary(User $user): array
    {
        $url = \Illuminate\Support\Facades\Route::has('cabinet.teacher.subscription') ? route('cabinet.teacher.subscription') : url('/tutor/subscription');
        $subscription = $user->activeSubscription();
        if (! $subscription?->tariff) {
            $extra = (int) $user->extra_lessons_balance;

            return ['name' => null, 'url' => $url, 'extra' => $extra, 'warning' => $extra > 0
                ? 'Тарифа нет, но можно провести ещё ' . plural_ru($extra, 'занятие', 'занятия', 'занятий') . '. Продлите тариф, когда будет удобно'
                : 'Выберите тариф, чтобы проводить занятия'];
        }

        $tariff = $subscription->tariff;
        $limit = $tariff->lessons_per_month;
        $used = $limit !== null ? self::lessonsUsedThisPeriod($user) : null;
        $left = $limit !== null ? max(0, $limit - $used) : null;
        $extra = (int) $user->extra_lessons_balance;
        $daysLeft = $subscription->ends_at ? (int) max(0, ceil(now()->diffInHours($subscription->ends_at, false) / 24)) : null;

        $warning = match (true) {
            $left !== null && $left === 0 && $extra <= 0 => 'Занятия по тарифу закончились',
            $left !== null && $left <= max(2, (int) ceil($limit * 0.25)) => 'Осталось ' . plural_ru($left + $extra, 'занятие', 'занятия', 'занятий'),
            $daysLeft !== null && $daysLeft <= 5 && ! $tariff->isFree() => $daysLeft === 0 ? 'Тариф закончится сегодня' : 'Тариф закончится через ' . plural_ru($daysLeft, 'день', 'дня', 'дней'),
            default => null,
        };

        return [
            'name' => $tariff->name,
            'url' => $url,
            'until' => match (true) {
                $tariff->isFree() => 'бесплатный',
                (bool) $subscription->ends_at => 'до ' . \App\Support\HumanDate::date($subscription->ends_at),
                default => 'бессрочно',
            },
            'limit' => $limit,
            'used' => $used,
            'left' => $left,
            'extra' => $extra,
            'resets' => $limit !== null && ($resets = self::periodResetsAt($user)) ? \App\Support\HumanDate::date($resets) : null,
            'limits' => collect([
                $tariff->max_participants ? 'до ' . plural_ru($tariff->max_participants, 'участника', 'участников', 'участников') . ' в занятии' : null,
                $tariff->max_duration_minutes ? 'занятие до ' . plural_ru($tariff->max_duration_minutes, 'минуты', 'минут', 'минут') : null,
                $tariff->recording_retention_days ? 'записи хранятся ' . plural_ru($tariff->recording_retention_days, 'день', 'дня', 'дней') : 'без записей',
            ])->filter()->values()->all(),
            'warning' => $warning,
        ];
    }

    /**
     * Начало текущего периода, за который считается лимит занятий.
     * Для оплаченных подписок (в том числе годовых) — 30-дневные циклы от даты
     * начала подписки; для бесплатного тарифа / без подписки — календарный месяц.
     */
    public static function periodStart(User $user): \Illuminate\Support\Carbon
    {
        $subscription = $user->activeSubscription();

        $from = now()->startOfMonth();

        if ($subscription && $subscription->ends_at) {
            $cycle = intdiv((int) $subscription->starts_at->diffInDays(now()), 30);
            $from = $subscription->starts_at->copy()->addDays($cycle * 30);
        } elseif ($subscription && $subscription->starts_at->gt($from)) {
            $from = $subscription->starts_at;
        }

        return $from;
    }

    /**
     * Дата, когда лимит занятий обновится (начало следующего периода).
     * Null — нет подписки или тариф без лимита.
     */
    public static function periodResetsAt(User $user): ?\Illuminate\Support\Carbon
    {
        $subscription = $user->activeSubscription();

        if (!$subscription || $subscription->tariff->lessons_per_month === null) {
            return null;
        }

        if ($subscription->ends_at) {
            $next = self::periodStart($user)->addDays(30);

            // Подписка закончится раньше следующего цикла — новый период
            // начнётся с продления/новой подписки
            return $next->gt($subscription->ends_at) ? $subscription->ends_at : $next;
        }

        return now()->startOfMonth()->addMonth();
    }

    /**
     * Цена одного дополнительного занятия (₽). Задаётся в админке
     * (Настройки → Эквайринг → Дополнительные занятия).
     */
    public static function extraLessonPrice(): int
    {
        return max(1, (int) (\App\Models\Setting::where('key', 'extra_lesson_price')->value('value') ?: self::EXTRA_LESSON_PRICE));
    }

    /**
     * Максимум дополнительных занятий за одну покупку.
     */
    public static function extraLessonsMax(): int
    {
        return max(1, (int) (\App\Models\Setting::where('key', 'extra_lessons_max')->value('value') ?: self::EXTRA_LESSONS_MAX));
    }

    /**
     * Докупка занятий доступна: настроен эквайринг и у пользователя есть тариф
     * с лимитом занятий (безлимитным тарифам докупать нечего).
     */
    public static function canBuyExtraLessons(User $user): bool
    {
        $tariff = $user->activeSubscription()?->tariff;

        return YooKassaService::isConfigured() && $tariff && $tariff->lessons_per_month !== null;
    }

    /**
     * Создаёт ожидающий платёж за $quantity дополнительных занятий.
     * Подписка (и тариф) нужны только для FK — на них платёж не влияет.
     */
    public static function createExtraLessonsPayment(User $user, int $quantity, ?array $meta = null): SubscriptionPayment
    {
        $quantity = max(1, min($quantity, self::extraLessonsMax()));

        $subscription = $user->activeSubscription();
        $tariff = $subscription?->tariff ?? Tariff::active()->first();

        return SubscriptionPayment::create([
            'user_id' => $user->id,
            'tariff_id' => $tariff->id,
            'subscription_id' => $subscription?->id,
            'amount' => $quantity * self::extraLessonPrice(),
            'period_days' => 0,
            'extra_lessons' => $quantity,
            'status' => SubscriptionPayment::STATUS_PENDING,
            'gateway' => 'yookassa',
            'meta' => $meta,
        ]);
    }

    /**
     * Зачисляет оплаченные дополнительные занятия на баланс пользователя.
     */
    protected static function applyExtraLessonsPayment(SubscriptionPayment $payment): ?Subscription
    {
        $user = $payment->user;

        DB::transaction(function () use ($user, $payment) {
            $locked = User::whereKey($user->id)->lockForUpdate()->first();
            $locked->update(['extra_lessons_balance' => (int) $locked->extra_lessons_balance + (int) $payment->extra_lessons]);
        });

        unset(self::$canStartCache[$user->id]);

        self::storeSavedPaymentMethod($payment);

        $user->refresh();
        $user->notify(new \App\Notifications\ExtraLessonsPurchased(
            (int) $payment->extra_lessons,
            (int) $payment->amount,
            (int) $user->extra_lessons_balance,
        ));

        self::notifyAdminsAboutPayment($payment, 'докупка: ' . plural_ru((int) $payment->extra_lessons, 'занятие', 'занятия', 'занятий'));

        return $user->activeSubscription();
    }

    /**
     * Администраторам — уведомление в кабинете и сообщение в чат техслужбы в Telegram о прошедшей оплате учителя.
     * $what — за что платёж («тариф «Стандарт» на 30 дней», «докупка: 5 занятий»).
     */
    protected static function notifyAdminsAboutPayment(SubscriptionPayment $payment, string $what): void
    {
        $user = $payment->user;
        $meta = $payment->meta ?? [];
        $note = match (true) {
            ! empty($meta['confirmed_manually']) => 'Подтверждено вручную' . (! empty($meta['confirmed_by']) ? ' (' . $meta['confirmed_by'] . ')' : ''),
            ! empty($meta['auto_renew']) => 'Автопродление',
            default => null,
        };

        User::where('role', User::ROLE_ADMIN)->get()
            ->each(fn (User $admin) => $admin->notify(new \App\Notifications\TeacherPaymentReceived($payment, $what)));

        \App\Jobs\SendAdminTelegramMessage::dispatch(implode("\n", array_filter([
            '💳 <b>Оплата от учителя: ' . e(\App\Support\Money::format($payment->amount)) . '</b>',
            e($user->name . ($user->email ? ' (' . $user->email . ')' : '')),
            e(\Illuminate\Support\Str::ucfirst($what)),
            $note ? e($note) : null,
            '',
            '<a href="' . e(route('cabinet.admin.user', ['user' => $user->id])) . '">Карточка учителя</a> · '
                . '<a href="' . e(route('cabinet.admin.payments')) . '">Платежи</a>',
        ], fn ($line) => $line !== null)));
    }

    /**
     * Соответствует ли сессия критериям проведённого занятия (см. lessonsUsedThisPeriod).
     */
    public static function isCountableLesson(MeetingSession $session): bool
    {
        return $session->status === 'completed'
            && $session->ended_at !== null
            && $session->started_at !== null
            && (int) $session->participant_count >= 2
            && $session->started_at->diffInSeconds($session->ended_at) > self::MIN_LESSON_SECONDS;
    }

    /**
     * Вызывается при завершении занятия. Если лимит тарифа в текущем периоде
     * уже выбран, занятие помечается как проведённое за счёт докупленных
     * и списывается с баланса. Сначала расходуется лимит тарифа, потом докупленные.
     * Возвращает true, если занятие списано с докупленных.
     */
    public static function consumeExtraLessonIfNeeded(MeetingSession $session): bool
    {
        if ($session->extra_lesson || !self::isCountableLesson($session)) {
            return false;
        }

        $user = $session->user;
        if (!$user) {
            return false;
        }

        $subscription = $user->activeSubscription();
        $tariff = $subscription?->tariff;

        // Без действующего тарифа каждое проведённое занятие идёт с баланса
        if ($subscription) {
            if (!$tariff || $tariff->lessons_per_month === null) {
                return false;
            }

            // Сессия ещё не помечена, поэтому входит в счётчик лимита
            if (self::lessonsUsedThisPeriod($user) <= $tariff->lessons_per_month) {
                return false;
            }
        }

        $consumed = DB::transaction(function () use ($user, $session) {
            $locked = User::whereKey($user->id)->lockForUpdate()->first();

            if ((int) $locked->extra_lessons_balance <= 0) {
                return false;
            }

            $locked->update(['extra_lessons_balance' => (int) $locked->extra_lessons_balance - 1]);
            $session->updateQuietly(['extra_lesson' => true]);

            return true;
        });

        unset(self::$canStartCache[$user->id]);

        return $consumed;
    }

    /**
     * Склонение слова «занятие» по числу.
     */
    public static function lessonsWord(int $n): string
    {
        $n = abs($n) % 100;
        $n1 = $n % 10;

        if ($n > 10 && $n < 20) {
            return 'занятий';
        }
        if ($n1 > 1 && $n1 < 5) {
            return 'занятия';
        }
        if ($n1 === 1) {
            return 'занятие';
        }

        return 'занятий';
    }
}
