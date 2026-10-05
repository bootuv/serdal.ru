<?php

namespace App\Demo\Teacher;

use App\Demo\Concerns\TeacherBillingDemo;
use App\Demo\Screen;
use App\Services\SubscriptionCheckoutService;
use App\Services\SubscriptionService;
use App\Support\HumanDate;
use Carbon\Carbon;

/**
 * «Тариф и платежи» — App\Livewire\Cabinet\Teacher\Subscription. Тариф «Профи», сохранённая карта, история платежей.
 * Оплата в демо — только тост: ни платёжной страницы, ни списаний.
 * Состояние: ?select=<id>&renew=1 — окно смены/продления, ?buy=1&quantity=N — докупка, ?removeOpen=1 — отвязать карту, ?auto=off.
 */
class Subscription extends Screen
{
    use TeacherBillingDemo;

    public const PATH = 'subscription';

    public const EXAMPLES = ['subscription', 'subscription?buy=1', 'subscription?buy=1&quantity=5', 'subscription?select=4',
        'subscription?select=1', 'subscription?select=2', 'subscription?removeOpen=1', 'subscription?auto=off'];

    public string $view = 'livewire.cabinet.teacher.subscription';

    public string $title = 'Тариф и платежи';

    public ?string $active = null;

    public function modalParams(): array
    {
        return ['select', 'renew', 'buy', 'quantity', 'removeOpen', 'bindOpen'];
    }

    public function actions(): array
    {
        $qty = $this->quantity();
        $selected = self::demoTariff($this->state('select', 0));
        $autoOn = $this->state('auto') !== 'off';

        return [
            'openSelect' => ['set' => ['select' => '{0}', 'renew' => '{1}']],
            'closeSelect' => ['close' => true],
            'confirmSelect' => ['close' => true, 'toast' => match (true) {
                ! $selected => 'Этот тариф уже подключён',
                $selected->isFree() => 'Тариф «' . $selected->name . '» подключится ' . HumanDate::date(self::demoSubscription()->ends_at),
                default => 'Оплачено — тариф «' . $selected->name . '» подключён',
            }],
            'openBuy' => ['set' => ['buy' => '1', 'quantity' => null]],
            'closeBuy' => ['close' => true],
            'confirmBuy' => ['close' => true, 'toast' => 'Оплачено — зачислено ' . plural_ru($qty, 'занятие', 'занятия', 'занятий')],
            'confirmRemove' => ['close' => true, 'toast' => 'Карта отвязана, автопродление выключено'],
            'toggleAutoRenew' => ['set' => ['auto' => $autoOn ? 'off' : null], 'toast' => $autoOn ? 'Автопродление выключено' : 'Автопродление включено'],
            'confirmBind' => ['close' => true, 'toast' => 'Способ оплаты уже привязан'],
        ];
    }

    private function quantity(): int
    {
        return max(1, min((int) $this->state('quantity', 1), SubscriptionService::EXTRA_LESSONS_MAX));
    }

    public function data(): array
    {
        $user = self::demoBillingUser($this->state('auto') !== 'off');
        $subscription = self::demoSubscription();
        $tariffs = self::demoTariffs()->values();
        $buyOpen = $this->state('buy', false);
        $renew = $this->state('renew', false);
        $selecting = self::demoTariff($this->state('select', 0));
        $price = SubscriptionService::EXTRA_LESSON_PRICE;
        $qty = $this->quantity();

        return [
            // SubscriptionCheckoutService::overview
            'subscription' => $subscription,
            'scheduled' => null,
            'expired' => null,
            'primaryTariffId' => null, // «Профи» — уже самый популярный, апгрейд не подсказываем
            'showRenew' => false,      // «Продлить» — за 14 дней до конца, а осталось 23
            'refundProcessingDays' => 10,
            'hasYearly' => false,
            'tariffs' => $tariffs,
            'lessonsUsed' => 64,
            'limitReached' => false,
            'periodResetsAt' => $subscription->ends_at,
            'extraBalance' => 0,
            'canBuyExtra' => true,
            'payments' => self::demoPayments(),
            // render()
            'user' => $user,
            'complimentary' => false,
            'primary' => null,
            'maxDiscount' => 0,
            'selecting' => $selecting,
            'selectUnavailable' => false,
            'selectDeferred' => $selecting && $selecting->isFree() ? $subscription->ends_at : null,
            'carryOver' => $selecting && ! $renew ? $this->carryOver($subscription, $selecting) : null,
            'showPicker' => false, // карта сохранена — оплата в один клик
            'canSave' => false,
            'methods' => SubscriptionCheckoutService::paymentMethods(),
            'savableMethods' => SubscriptionCheckoutService::paymentMethods(['bank_card', 'sbp']),
            'savedMethodType' => 'bank_card',
            'recurring' => true,
            'extraPrice' => $price,
            'extraMax' => SubscriptionService::EXTRA_LESSONS_MAX,
            'extraTotal' => $qty * $price,
            'extraQty' => $qty,
            'upgrade' => null, // докупка до 1 000 ₽ — переход на «Мастер» не выгоднее
            'referralBonus' => 10,
            'referralsUrl' => route('cabinet.teacher.referrals'),
            'profileUrl' => route('cabinet.teacher.profile'),
            'historyUrl' => route('cabinet.teacher.payments'),
            // Публичные свойства компонента
            'billingPeriod' => 'month',
            'selectTariffId' => $selecting?->id,
            'selectRenew' => $renew,
            'payMethod' => 'sbp',
            'saveMethod' => false,
            'autoRenewOptIn' => false,
            'modalError' => null,
            'buyOpen' => $buyOpen,
            'quantity' => $qty,
            'bindOpen' => false,
            'removeOpen' => $this->state('removeOpen', false),
        ];
    }

    /** Остаток «Профи» в днях нового тарифа — как SubscriptionService::carryOver. */
    private function carryOver($subscription, $tariff): ?array
    {
        if ($tariff->isFree() || $tariff->id === $subscription->tariff_id) {
            return null;
        }

        $now = Carbon::now();
        $value = round(2990 * min(1, $now->diffInSeconds($subscription->ends_at) / $subscription->starts_at->diffInSeconds($subscription->ends_at)), 2);
        $days = (int) floor($value / $tariff->price * $tariff->period_days);

        return $days < 1 ? null : [
            'name' => $subscription->tariff->name,
            'left_days' => (int) $now->diffInDays($subscription->ends_at),
            'value' => $value,
            'days' => $days,
        ];
    }
}
