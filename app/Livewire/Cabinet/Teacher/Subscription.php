<?php

namespace App\Livewire\Cabinet\Teacher;

use App\Livewire\Cabinet\Teacher\Concerns\TeacherScreen;
use App\Models\Tariff;
use App\Services\ReferralService;
use App\Services\SubscriptionCheckoutService;
use App\Services\SubscriptionService;
use App\Services\YooKassaService;
use App\Support\HumanDate;
use Illuminate\Support\Facades\Route;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Тариф и платежи учителя. Макет: «Учитель · Тариф и платежи» (docs/design/BRAND.md).
 * Логика — SubscriptionCheckoutService.
 */
#[Layout('components.layouts.cabinet', ['title' => 'Тариф и платежи', 'active' => null])]
class Subscription extends Component
{
    use TeacherScreen;

    /** Период оплаты: month | year. */
    public string $billingPeriod = 'month';

    // Окно смены/продления тарифа
    public ?int $selectTariffId = null;
    public bool $selectRenew = false;
    public string $payMethod = 'sbp';
    public bool $saveMethod = false;
    public bool $autoRenewOptIn = false;
    public ?string $modalError = null;

    // Докупка занятий
    public bool $buyOpen = false;
    public $quantity = 1;

    // Способ оплаты
    public bool $bindOpen = false;
    public bool $removeOpen = false;

    public function mount(): void
    {
        $user = $this->authorizeTeacher();

        // Сообщение после возврата с платёжной страницы банка
        if (session()->has('subscription_message')) {
            $this->dispatch('toast', message: session()->pull('subscription_message')['title']);
        }

        // ?pay=<id> — сразу открываем оплату тарифа (переход после онбординга)
        $payTariffId = request()->integer('pay');
        if ($payTariffId && ! SubscriptionCheckoutService::tariffUnavailable($payTariffId)) {
            $tariff = Tariff::find($payTariffId);

            if ($tariff && ! $tariff->isFree() && $user->activeSubscription()?->tariff_id !== $tariff->id) {
                $this->openSelect($tariff->id);
            }
        }

        // ?buy=1 — сразу открываем докупку занятий (переход из окна «Занятия по тарифу закончились»)
        if (request()->boolean('buy') && SubscriptionService::canBuyExtraLessons($user)) {
            $this->openBuy();
        }
    }

    public function updatedBillingPeriod(string $value): void
    {
        if (! in_array($value, ['month', 'year'], true)) {
            $this->billingPeriod = 'month';
        }
    }

    /*
     | Смена и продление тарифа
     */

    public function openSelect(int $tariffId, bool $renew = false): void
    {
        $this->selectTariffId = $tariffId;
        $this->selectRenew = $renew;
        $this->payMethod = 'sbp';
        $this->saveMethod = false;
        $this->autoRenewOptIn = false;
        $this->modalError = null;
    }

    public function closeSelect(): void
    {
        $this->selectTariffId = null;
        $this->modalError = null;
    }

    public function updatedSaveMethod(bool $value): void
    {
        if (! $value) {
            $this->autoRenewOptIn = false;
        }
    }

    public function confirmSelect(): void
    {
        if (! $this->selectTariffId) {
            return;
        }

        $user = auth()->user();
        $tariff = Tariff::find($this->selectTariffId);
        $picker = $this->needsMethodPicker($tariff);
        $saveAllowed = $picker && $this->canSaveMethod();

        $result = SubscriptionCheckoutService::selectTariff(
            $user,
            $this->selectTariffId,
            $this->billingPeriod === 'year',
            $saveAllowed && $this->saveMethod,
            $saveAllowed && $this->saveMethod && $this->autoRenewOptIn,
            $picker ? $this->payMethod : null,
        );
        $name = isset($result['tariff']) ? '«' . $result['tariff']->name . '»' : '';

        $message = match ($result['status']) {
            'redirect' => null,
            'activated' => 'Тариф ' . $name . ' подключён',
            'paid' => 'Оплачено — тариф ' . $name . ' подключён',
            'processing' => 'Платёж обрабатывается — тариф включится после подтверждения оплаты',
            'already_active' => 'Этот тариф уже подключён',
            'already_scheduled' => 'Переключение уже запланировано',
            'scheduled' => 'Тариф ' . $name . ' подключится ' . HumanDate::date($result['subscription']->ends_at),
            default => false,
        };

        if ($result['status'] === 'redirect') {
            $this->redirect($result['url']);

            return;
        }

        if ($message === false) {
            $this->modalError = match ($result['status']) {
                'unavailable' => 'Этот тариф снят с продажи. Выберите другой тариф из списка.',
                'not_configured' => 'Онлайн-оплата подключается. Пока платные тарифы можно оформить через поддержку: info@serdal.ru — мы подключим тариф вручную.',
                default => 'Не удалось создать платёж. Попробуйте ещё раз или напишите в поддержку.',
            };

            return;
        }

        $this->closeSelect();
        $this->dispatch('toast', message: $message);
    }

    /*
     | Докупка занятий
     */

    public function openBuy(): void
    {
        $this->quantity = 1;
        $this->payMethod = 'sbp';
        $this->modalError = null;
        $this->resetValidation();
        $this->buyOpen = true;
    }

    public function closeBuy(): void
    {
        $this->buyOpen = false;
        $this->modalError = null;
    }

    public function confirmBuy(): void
    {
        $max = SubscriptionService::extraLessonsMax();
        $this->validate(
            ['quantity' => ['required', 'integer', 'min:1', 'max:' . $max]],
            [],
            ['quantity' => 'количество'],
        );

        $user = auth()->user();
        $quantity = (int) $this->quantity;
        $result = SubscriptionCheckoutService::buyExtraLessons($user, $quantity, $user->yookassa_payment_method_id ? null : $this->payMethod);

        switch ($result['status']) {
            case 'redirect':
                $this->redirect($result['url']);

                return;
            case 'paid':
                $this->closeBuy();
                $this->dispatch('toast', message: 'Оплачено — зачислено ' . plural_ru($quantity, 'занятие', 'занятия', 'занятий'));

                return;
            case 'processing':
                $this->closeBuy();
                $this->dispatch('toast', message: 'Платёж обрабатывается — занятия зачислятся после подтверждения оплаты');

                return;
            case 'invalid_quantity':
                $this->addError('quantity', 'Укажите количество от 1 до ' . $result['max']);

                return;
            case 'unavailable':
                $this->modalError = $result['configured']
                    ? 'Докупать занятия можно только на тарифе с лимитом занятий.'
                    : 'Онлайн-оплата подключается. Напишите в поддержку: info@serdal.ru.';

                return;
            default:
                $this->modalError = 'Не удалось создать платёж. Попробуйте ещё раз или напишите в поддержку.';
        }
    }

    /*
     | Способ оплаты и автопродление
     */

    public function openBind(): void
    {
        $savable = YooKassaService::savableMethods();
        $this->payMethod = in_array('sbp', $savable, true) ? 'sbp' : $savable[0];
        $this->modalError = null;
        $this->bindOpen = true;
    }

    public function closeBind(): void
    {
        $this->bindOpen = false;
        $this->modalError = null;
    }

    public function confirmBind(): void
    {
        $single = count(YooKassaService::savableMethods()) === 1;
        $result = SubscriptionCheckoutService::bindCard(auth()->user(), $single ? null : $this->payMethod);

        if ($result['status'] === 'redirect') {
            $this->redirect($result['url']);

            return;
        }

        if ($result['status'] === 'already_bound') {
            $this->closeBind();
            $this->dispatch('toast', message: 'Способ оплаты уже привязан');

            return;
        }

        $this->modalError = match ($result['status']) {
            'disabled' => 'Автоплатежи подключаются на стороне платёжного сервиса. Попробуйте позже.',
            'no_tariffs' => 'Нет доступных тарифов.',
            default => 'Не удалось привязать способ оплаты. Попробуйте ещё раз.',
        };
    }

    public function confirmRemove(): void
    {
        SubscriptionCheckoutService::removePaymentMethod(auth()->user());
        $this->removeOpen = false;
        $this->dispatch('toast', message: 'Карта отвязана, автопродление выключено');
    }

    public function toggleAutoRenew(): void
    {
        $result = SubscriptionCheckoutService::toggleAutoRenew(auth()->user());

        $this->dispatch('toast', message: match ($result['status']) {
            'enabled' => 'Автопродление включено',
            'disabled' => 'Автопродление выключено',
            default => 'Сначала сохраните способ оплаты при следующей оплате',
        });
    }

    /** Выбор способа оплаты: платный доступный тариф, карта не привязана, оплата настроена (как в старом кабинете). */
    private function needsMethodPicker(?Tariff $tariff): bool
    {
        return $tariff && $tariff->is_active && ! $tariff->isFree()
            && ! auth()->user()->yookassa_payment_method_id && YooKassaService::isConfigured();
    }

    private function canSaveMethod(): bool
    {
        return YooKassaService::recurringEnabled() && in_array($this->payMethod, YooKassaService::savableMethods(), true);
    }

    public function render()
    {
        $user = auth()->user();
        $data = SubscriptionCheckoutService::overview($user);
        $subscription = $data['subscription'];
        $complimentary = $subscription?->isComplimentary() ?? false;

        // Одна жёлтая кнопка: продление → докупка при исчерпанном лимите → ожидаемый тариф
        $primary = match (true) {
            $data['expired'] !== null => 'renew',
            $data['showRenew'] && ! $complimentary => 'renew',
            $data['canBuyExtra'] && $data['limitReached'] && $data['extraBalance'] <= 0 => 'buy',
            (bool) $data['primaryTariffId'] => 'tariff',
            default => null,
        };

        $selecting = $this->selectTariffId ? Tariff::withTrashed()->find($this->selectTariffId) : null;
        $price = SubscriptionService::extraLessonPrice();
        $qty = max(1, min((int) $this->quantity, SubscriptionService::extraLessonsMax()));

        return view('livewire.cabinet.teacher.subscription', $data + [
            'user' => $user,
            'complimentary' => $complimentary,
            'primary' => $primary,
            'maxDiscount' => $data['tariffs']->max(fn (Tariff $t) => $t->yearlyDiscountPercent()),
            'selecting' => $selecting,
            'selectUnavailable' => $selecting && SubscriptionCheckoutService::tariffUnavailable($selecting->id),
            'selectDeferred' => $selecting && ! $selecting->trashed() ? SubscriptionCheckoutService::deferredUntil($user, $selecting) : null,
            // Переход на другой платный тариф: остаток текущего добавится к новому
            'carryOver' => $selecting && ! $this->selectRenew && ! SubscriptionCheckoutService::tariffUnavailable($selecting->id)
                ? SubscriptionCheckoutService::carryOverPreview($user, $selecting, $this->billingPeriod === 'year')
                : null,
            'showPicker' => $this->needsMethodPicker($selecting),
            'canSave' => $this->canSaveMethod(),
            'methods' => SubscriptionCheckoutService::paymentMethods(),
            'savableMethods' => SubscriptionCheckoutService::paymentMethods(YooKassaService::savableMethods()),
            'savedMethodType' => SubscriptionCheckoutService::savedMethodType($user),
            'recurring' => YooKassaService::recurringEnabled(),
            'extraPrice' => $price,
            'extraMax' => SubscriptionService::extraLessonsMax(),
            'extraTotal' => $qty * $price,
            'extraQty' => $qty,
            'upgrade' => $this->buyOpen ? SubscriptionCheckoutService::upgradeHint($user, $qty * $price) : null,
            'referralBonus' => ReferralService::enabled() ? ReferralService::referrerBonus() : 0,
            'referralsUrl' => Route::has('cabinet.teacher.referrals') ? route('cabinet.teacher.referrals') : url('/tutor/referrals'),
            'profileUrl' => Route::has('cabinet.teacher.profile') ? route('cabinet.teacher.profile') : url('/tutor/edit-profile'),
            'historyUrl' => Route::has('cabinet.teacher.payments') ? route('cabinet.teacher.payments') : url('/tutor/subscription'),
        ]);
    }
}
