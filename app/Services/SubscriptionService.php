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
     * Корректирует подписку после возврата платежа: оплаченный возвращённым
     * платежом период вычитается из срока подписки. Если срока не остаётся —
     * подписка завершается сразу. Возвращает скорректированную подписку или null.
     */
    public static function applyRefund(SubscriptionPayment $payment): ?Subscription
    {
        // Возврат за докупленные занятия: списываем их с баланса (не ниже нуля)
        if ($payment->isExtraLessons()) {
            $user = $payment->user;
            $user->update([
                'extra_lessons_balance' => max(0, (int) $user->extra_lessons_balance - (int) $payment->extra_lessons),
            ]);
            unset(self::$canStartCache[$user->id]);

            return null;
        }

        $subscription = $payment->subscription
            ?? $payment->user->subscriptions()->active()->where('tariff_id', $payment->tariff_id)->latest('starts_at')->first();

        // Бессрочные (бесплатные/подаренные) подписки не трогаем
        if (!$subscription || !$subscription->ends_at) {
            return null;
        }

        $newEnd = $subscription->ends_at->copy()->subDays($payment->period_days);

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
     * Если учитель попросил сохранить карту и ЮKassa подтвердила сохранение —
     * привязываем способ оплаты. Автопродление включается только при отдельном
     * согласии (meta.auto_renew_opt_in); уже включённое — не выключаем.
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
                'payment_method_title' => $method['title'] ?? 'Банковская карта',
                'auto_renew' => $user->auto_renew || !empty($payment->meta['auto_renew_opt_in']),
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

                return "Лимит занятий по тарифу «{$tariff->name}» исчерпан ({$used} из {$tariff->lessons_per_month}).{$resetText} "
                    . 'Докупите занятия или перейдите на тариф выше, чтобы продолжить занятия в этом периоде.';
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

        return $user->activeSubscription();
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
        $tariff = $user?->activeSubscription()?->tariff;

        if (!$user || !$tariff || $tariff->lessons_per_month === null) {
            return false;
        }

        // Сессия ещё не помечена, поэтому входит в счётчик лимита
        if (self::lessonsUsedThisPeriod($user) <= $tariff->lessons_per_month) {
            return false;
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
