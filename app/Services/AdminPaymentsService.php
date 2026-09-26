<?php

namespace App\Services;

use App\Models\ReferralReward;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\Tariff;
use App\Models\User;
use App\Support\HumanDate;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Платежи учителей за тариф и дополнительные занятия (ЮKassa) для админки:
 * выборки, сводка за месяц, ручное подтверждение оплаты и возврат.
 * Оплату занятий между учеником и учителем администратор не видит — здесь только SubscriptionPayment.
 */
class AdminPaymentsService
{
    /** Назначения платежа кроме тарифов: докупка занятий и привязка карты. */
    public const PURPOSE_EXTRA = 'extra';
    public const PURPOSE_CARD = 'card';

    /* ---------- Выборки ---------- */

    /**
     * Платежи с фильтрами: status (all|failed|refunded|pending|paid), purpose (all|extra|card|id тарифа),
     * period (week|month|prev|all), q — имя/почта учителя или номер платежа в ЮKassa.
     */
    public function query(array $filters = []): Builder
    {
        $query = SubscriptionPayment::query()->with(['user', 'tariff']);

        $status = $filters['status'] ?? 'all';
        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $purpose = (string) ($filters['purpose'] ?? 'all');
        match (true) {
            $purpose === self::PURPOSE_EXTRA => $query->where('extra_lessons', '>', 0),
            $purpose === self::PURPOSE_CARD => $this->whereCardBinding($query),
            ctype_digit($purpose) => $this->whereTariffPayment($query)->where('tariff_id', (int) $purpose),
            default => null,
        };

        match ($filters['period'] ?? 'all') {
            'week' => $query->where('created_at', '>=', now()->subDays(7)),
            'month' => $query->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()]),
            'prev' => $query->whereBetween('created_at', [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth()]),
            default => null,
        };

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $query->where(function (Builder $w) use ($q) {
                $w->whereHas('user', fn (Builder $u) => $u->where('name', 'like', '%' . $q . '%')->orWhere('email', 'like', '%' . $q . '%'))
                    ->orWhere('gateway_order_id', 'like', '%' . $q . '%');
            });
        }

        return $query->latest('created_at')->latest('id');
    }

    /** Только привязки карты (проверочный платёж 1 ₽). */
    public function whereCardBinding(Builder $query): Builder
    {
        return $query->whereNotNull('meta->card_binding');
    }

    /** Платежи за тариф: не докупка занятий и не привязка карты. */
    public function whereTariffPayment(Builder $query): Builder
    {
        return $query->where(fn (Builder $w) => $w->whereNull('extra_lessons')->orWhere('extra_lessons', 0))
            ->whereNull('meta->card_binding');
    }

    /** Тарифы, за которые были платежи, — варианты фильтра «Назначение». */
    public function purposeTariffs(): Collection
    {
        return Tariff::withTrashed()
            ->whereIn('id', SubscriptionPayment::query()->select('tariff_id')->distinct())
            ->orderBy('sort')
            ->get();
    }

    /**
     * Ожидают оплаты дольше суток: уведомление ЮKassa не пришло, а проверка по расписанию статус не обновила.
     * Старше месяца не показываем — такие платежи учитель давно бросил.
     */
    public function stuck(string $q = ''): Collection
    {
        return $this->query(['status' => SubscriptionPayment::STATUS_PENDING, 'q' => $q])
            ->whereBetween('created_at', [now()->subDays(30), now()->subDay()])
            ->whereNull('meta->card_binding')
            ->reorder('created_at')
            ->get();
    }

    /**
     * Сводка за месяц: оплачено (сумма и число оплат) и возвраты. Проверочные платежи привязки карты не считаются.
     */
    public function monthStats(Carbon $month): array
    {
        $from = $month->copy()->startOfMonth();
        $to = $from->copy()->endOfMonth();

        $paid = SubscriptionPayment::where('status', SubscriptionPayment::STATUS_PAID)
            ->whereBetween('paid_at', [$from, $to])
            ->whereNull('meta->card_binding');

        $refunds = SubscriptionPayment::where('status', SubscriptionPayment::STATUS_REFUNDED)
            ->whereNull('meta->card_binding')
            ->where('updated_at', '>=', $from)
            ->get()
            ->filter(fn (SubscriptionPayment $p) => $this->refundedAt($p)->between($from, $to));

        return [
            'paidSum' => (int) (clone $paid)->sum('amount'),
            'paidCount' => (clone $paid)->count(),
            'refundCount' => $refunds->count(),
            'refundSum' => (int) $refunds->sum('amount'),
        ];
    }

    /* ---------- Подписки учителей (только просмотр) ---------- */

    /**
     * Подписки по сегментам: active — действующие, soon — заканчиваются за неделю,
     * gift — платный тариф предоставлен бесплатно, ended — закончились и новой нет. По одной на учителя.
     */
    public function subscriptions(string $segment, string $q = ''): Collection
    {
        $q = trim($q);
        $withUser = fn (Builder $query) => $query
            ->whereHas('user', function (Builder $u) use ($q) {
                $u->where('role', User::ROLE_TUTOR);
                if ($q !== '') {
                    $u->where(fn (Builder $w) => $w->where('name', 'like', '%' . $q . '%')->orWhere('email', 'like', '%' . $q . '%'));
                }
            })
            ->with(['user', 'tariff', 'payments']);

        if ($segment === 'ended') {
            $activeUsers = Subscription::active()->select('user_id');

            return $withUser(Subscription::query())
                ->whereIn('status', [Subscription::STATUS_ACTIVE, Subscription::STATUS_EXPIRED])
                ->whereNotNull('ends_at')
                ->where('ends_at', '<=', now())
                ->whereNotIn('user_id', $activeUsers)
                ->orderByDesc('ends_at')
                ->get()
                ->unique('user_id')
                ->values();
        }

        $active = $withUser(Subscription::active())
            ->orderByDesc('starts_at')
            ->get()
            ->unique('user_id');

        $list = match ($segment) {
            'soon' => $active->filter(fn (Subscription $s) => $this->endsSoon($s)),
            'gift' => $active->filter(fn (Subscription $s) => $s->isComplimentary()),
            default => $active,
        };

        return $list->sortBy(fn (Subscription $s) => [$s->ends_at ? 0 : 1, $s->ends_at?->timestamp ?? 0, $s->user->name])->values();
    }

    public function endsSoon(Subscription $s): bool
    {
        return $s->ends_at !== null && $s->ends_at->isFuture() && $s->ends_at->lte(now()->addDays(7));
    }

    /** Неудачные автопродления за последние 40 дней — для пометки «продление не прошло». */
    public function failedRenewals(): Collection
    {
        return SubscriptionPayment::where('status', SubscriptionPayment::STATUS_FAILED)
            ->where('meta->auto_renew', true)
            ->where('created_at', '>=', now()->subDays(40))
            ->latest()
            ->get()
            ->unique('user_id')
            ->keyBy('user_id');
    }

    /* ---------- Подробности платежа ---------- */

    /** Назначение коротко: «Тариф «Профи» · на год», «Дополнительные занятия · 10», «Привязка карты». */
    public function title(SubscriptionPayment $p): string
    {
        return match (true) {
            $this->isBinding($p) => 'Привязка карты',
            $p->isExtraLessons() => 'Дополнительные занятия · ' . $p->extra_lessons,
            default => 'Тариф «' . $p->tariff?->name . '»' . ((int) $p->period_days >= 365 ? ' · на год' : ''),
        };
    }

    public function isBinding(SubscriptionPayment $p): bool
    {
        return ! empty($p->meta['card_binding']);
    }

    /** Способ оплаты по ответу ЮKassa: «Карта •••• 2231», «СБП»; для автосписания — «, автопродление». */
    public function method(SubscriptionPayment $p): ?string
    {
        $m = $p->meta['status_response']['payment_method'] ?? null;
        $name = match (true) {
            ! $m => null,
            ($m['type'] ?? null) === 'bank_card' && ! empty($m['card']['last4']) => 'Карта •••• ' . $m['card']['last4'],
            default => SubscriptionService::paymentMethodFallbackTitle($m['type'] ?? null),
        };

        if (! empty($p->meta['auto_renew'])) {
            $name = ($name ?? 'Сохранённый способ оплаты') . ', автопродление';
        }

        return $name;
    }

    /** Куда вернутся деньги — для текста возврата. */
    private function refundTarget(SubscriptionPayment $p): string
    {
        $m = $p->meta['status_response']['payment_method'] ?? null;

        return match (true) {
            ($m['type'] ?? null) === 'bank_card' && ! empty($m['card']['last4']) => 'на карту •••• ' . $m['card']['last4'],
            ($m['type'] ?? null) === 'sbp' => 'на счёт, с которого платили через СБП,',
            default => 'тем же способом, которым платили,',
        };
    }

    /** Почему платёж не прошёл — по причине отмены из ЮKassa. */
    public function failReason(SubscriptionPayment $p): string
    {
        $reason = $p->meta['status_response']['cancellation_details']['reason'] ?? null;

        return match ($reason) {
            'insufficient_funds' => 'Банк отклонил списание: недостаточно средств',
            'card_expired' => 'Истёк срок действия карты',
            'expired_on_confirmation' => 'Учитель не подтвердил оплату вовремя',
            'expired_on_capture' => 'Платёж не подтверждён магазином вовремя',
            '3d_secure_failed' => 'Не пройдено подтверждение в банке',
            'call_issuer' => 'Банк отклонил списание — нужно позвонить в банк',
            'canceled_by_merchant' => 'Платёж отменён магазином',
            'payment_method_limit_exceeded' => 'Превышен лимит по карте',
            'payment_method_restricted' => 'Операции по карте запрещены банком',
            'fraud_suspected' => 'Банк заподозрил мошенничество',
            'permission_revoked' => 'Учитель отменил разрешение на автосписания',
            'general_decline', null => 'Банк отклонил списание без объяснения причины',
            default => 'Платёж отклонён',
        };
    }

    /** Что даёт платёж: «Тариф «Профи» на 30 дней — до 2 ноября», «10 занятий на баланс — сейчас на балансе 14». */
    public function gives(SubscriptionPayment $p): string
    {
        if ($this->isBinding($p)) {
            return 'Проверка карты для автопродления';
        }

        if ($p->isExtraLessons()) {
            $n = plural_ru((int) $p->extra_lessons, 'занятие', 'занятия', 'занятий');

            return match ($p->status) {
                SubscriptionPayment::STATUS_PAID => $n . ' на баланс — сейчас на балансе ' . (int) $p->user?->extra_lessons_balance,
                SubscriptionPayment::STATUS_REFUNDED => $n . ' — списаны возвратом',
                default => $n . ' на баланс после оплаты',
            };
        }

        $tariff = 'тариф «' . $p->tariff?->name . '» на ' . plural_ru((int) $p->period_days, 'день', 'дня', 'дней');
        $sub = $p->subscription;

        return match (true) {
            $p->status === SubscriptionPayment::STATUS_REFUNDED => Str::ucfirst($tariff) . ' — отменён возвратом',
            $p->status === SubscriptionPayment::STATUS_PAID && $sub?->isActive() && $sub->ends_at !== null => Str::ucfirst($tariff) . ' — до ' . HumanDate::date($sub->ends_at),
            $p->status === SubscriptionPayment::STATUS_PAID => Str::ucfirst($tariff),
            ! empty($p->meta['auto_renew']) => 'Продление: ' . $tariff,
            default => Str::ucfirst($tariff) . ' после оплаты',
        };
    }

    public function refundedAt(SubscriptionPayment $p): Carbon
    {
        return ! empty($p->meta['refunded_at']) ? Carbon::parse($p->meta['refunded_at']) : $p->updated_at;
    }

    /** Состояние платежа для окна: подпись и текст («Оплачен» — когда; «Причина»; «Возврат» — когда и кто). */
    public function state(SubscriptionPayment $p): array
    {
        return match ($p->status) {
            SubscriptionPayment::STATUS_PAID => ['Оплачен', Str::ucfirst(HumanDate::at($p->paid_at ?? $p->created_at))
                . (! empty($p->meta['confirmed_by']) ? ', подтвердил администратор ' . $p->meta['confirmed_by'] : '')],
            SubscriptionPayment::STATUS_FAILED => ['Причина', $this->failReason($p)],
            SubscriptionPayment::STATUS_REFUNDED => ['Возврат', Str::ucfirst(HumanDate::at($this->refundedAt($p))) . match (true) {
                ! empty($p->meta['refunded_by']) => ', оформил администратор ' . $p->meta['refunded_by'],
                $this->isBinding($p) => ', автоматически',
                default => '',
            }],
            default => ['Статус', $p->isResumable() ? 'Учитель ещё на странице оплаты' : 'Уведомление от ЮKassa не пришло'],
        };
    }

    /** Подтвердить вручную можно ожидающий платёж за тариф или занятия (не проверку карты). */
    public function canConfirm(SubscriptionPayment $p): bool
    {
        return $p->status === SubscriptionPayment::STATUS_PENDING && ! $this->isBinding($p);
    }

    public function canRefund(SubscriptionPayment $p): bool
    {
        return $p->status === SubscriptionPayment::STATUS_PAID;
    }

    /** Что произойдёт после ручного подтверждения. */
    public function confirmEffect(SubscriptionPayment $p): string
    {
        if ($p->isExtraLessons()) {
            return plural_ru((int) $p->extra_lessons, 'занятие зачислится', 'занятия зачислятся', 'занятий зачислятся')
                . ' на баланс учителя, как при обычной оплате, и учитель получит уведомление.';
        }

        $current = $p->user?->activeSubscription();
        $renew = $current && $current->tariff_id === $p->tariff_id;
        $base = $renew && $current->ends_at?->isFuture() ? $current->ends_at : now();
        // Смена платного тарифа: неиспользованный остаток прошлого добавится к сроку, как при обычной оплате
        $carry = ! $renew && $p->user && $p->tariff ? SubscriptionService::carryOver($p->user, $p->tariff, (float) $p->amount, (int) ($p->period_days ?: $p->tariff->period_days)) : null;
        $until = $base->copy()->addDays((int) $p->period_days + ($carry['days'] ?? 0));

        return 'Тариф «' . $p->tariff?->name . '» подключится до ' . HumanDate::date($until)
            . ', как при обычной оплате, и учитель получит уведомление.';
    }

    /** Что произойдёт с тарифом или балансом после возврата. */
    public function refundEffect(SubscriptionPayment $p): string
    {
        if ($this->isBinding($p)) {
            return 'Это проверочный платёж — тариф не изменится.';
        }

        $bonus = ReferralReward::where('payment_id', $p->id)
            ->whereIn('status', [ReferralReward::STATUS_CREDITED, ReferralReward::STATUS_LIMIT])
            ->exists() ? ' Бонусы партнёрской программы за этот платёж спишутся.' : '';

        if ($p->isExtraLessons()) {
            $left = max(0, (int) $p->user?->extra_lessons_balance - (int) $p->extra_lessons);

            return plural_ru((int) $p->extra_lessons, 'докупленное занятие спишется', 'докупленных занятия спишутся', 'докупленных занятий спишутся')
                . ' с баланса учителя — останется ' . $left . '.' . $bonus;
        }

        // Какую подписку и на сколько дней сократит возврат (с учётом переноса остатка при смене тарифа)
        $plan = $p->user ? SubscriptionService::refundPlan($p) : null;
        $sub = $plan['subscription'] ?? null;

        if (! $sub || ! $sub->ends_at || ! $sub->isActive()) {
            return 'Оплаченный период уже закончился — тариф не изменится.' . $bonus;
        }

        if ($plan['days'] <= 0) {
            return 'Срок тарифа не изменится.' . $bonus;
        }

        $name = 'Тариф «' . $sub->tariff?->name . '»';
        $days = plural_ru($plan['days'], 'день', 'дня', 'дней');
        $newEnd = $sub->ends_at->copy()->subDays($plan['days']);

        return ($newEnd->isPast()
            ? $name . ' закончится сегодня: оплаченные этим платежом ' . $days . ' отменятся.'
            : $name . ' сократится до ' . HumanDate::day($newEnd) . ' — на оплаченные этим платежом ' . $days . '.') . $bonus;
    }

    /** Куда и когда вернутся деньги. */
    public function refundMoney(SubscriptionPayment $p): string
    {
        if (! $p->gateway_order_id) {
            return 'У платежа нет номера в ЮKassa — он будет отмечен как возвращённый без движения денег.';
        }

        $days = (int) (\App\Support\OfferSettings::offer()['refund_processing_days'] ?? 10);

        return 'Деньги вернутся ' . $this->refundTarget($p) . ' в течение ' . plural_ru($days, 'рабочего дня', 'рабочих дней', 'рабочих дней') . '.'
            . ($this->isBinding($p) ? '' : ' Учитель получит уведомление.');
    }

    /* ---------- Действия ---------- */

    /**
     * Ручное подтверждение оплаты (уведомление ЮKassa не дошло, а деньги в личном кабинете ЮKassa видны):
     * подписка подключается или продлевается, занятия зачисляются — как при обычной оплате.
     */
    public function confirm(SubscriptionPayment $payment, ?User $admin = null): ?Subscription
    {
        abort_unless($this->canConfirm($payment), 422);

        $payment->update(['meta' => array_merge($payment->meta ?? [], array_filter([
            'confirmed_manually' => true,
            'confirmed_by' => $admin?->name,
        ]))]);

        return SubscriptionService::applyPaidPayment($payment);
    }

    /**
     * Возврат оплаченного платежа: деньги через ЮKassa (у платежа без номера — только отметка в учёте),
     * срок подписки сокращается на оплаченный период, докупленные занятия и бонусы программы списываются, учитель получает уведомление.
     *
     * @return array{ok: bool, error: ?string, subscription: ?Subscription}
     */
    public function refund(SubscriptionPayment $payment, ?User $admin = null): array
    {
        abort_unless($this->canRefund($payment), 422);

        $by = array_filter(['refunded_by' => $admin?->name]);

        if ($payment->gateway_order_id) {
            if (! YooKassaService::refundPayment($payment)) {
                return [
                    'ok' => false,
                    'error' => $payment->fresh()->meta['refund_response']['description'] ?? 'Проверьте баланс магазина и статус платежа в личном кабинете ЮKassa.',
                    'subscription' => null,
                ];
            }
            $payment->refresh();
            $payment->update(['status' => SubscriptionPayment::STATUS_REFUNDED, 'meta' => array_merge($payment->meta ?? [], $by)]);
        } else {
            $payment->update([
                'status' => SubscriptionPayment::STATUS_REFUNDED,
                'meta' => array_merge($payment->meta ?? [], ['refunded_at' => now()->toIso8601String()], $by),
            ]);
        }

        // Проверочный платёж привязки карты — служебный: подписку не трогаем, учителя не уведомляем
        if ($this->isBinding($payment)) {
            return ['ok' => true, 'error' => null, 'subscription' => null];
        }

        $adjusted = SubscriptionService::applyRefund($payment);
        $payment->user?->notify(new \App\Notifications\SubscriptionRefunded(
            $payment->title,
            $payment->amount,
            \App\Support\OfferSettings::offer()['refund_processing_days'],
            newEndsAt: $adjusted?->isActive() ? $adjusted->ends_at : null,
            subscriptionEnded: $adjusted !== null && ! $adjusted->isActive(),
        ));

        return ['ok' => true, 'error' => null, 'subscription' => $adjusted];
    }

    /** Сумма для строк: «2 990 ₽». */
    public function amount(SubscriptionPayment $p): string
    {
        return Money::format((int) round((float) $p->amount));
    }
}
