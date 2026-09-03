<?php

namespace App\Filament\App\Pages;

use App\Models\SubscriptionPayment;
use App\Models\Tariff;
use App\Services\YooKassaService;
use App\Services\SubscriptionService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class ManageSubscription extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationLabel = 'Подписка';

    protected static ?string $title = 'Подписка';

    protected static ?string $slug = 'subscription';

    protected static ?string $navigationGroup = '';

    protected static ?int $navigationSort = 95;

    /**
     * Страница доступна из выпадающего меню профиля, в сайдбаре не показывается.
     */
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    protected static string $view = 'filament.app.pages.manage-subscription';

    /**
     * Период оплаты, выбранный переключателем «Помесячно / На год»: month | year.
     */
    public string $billingPeriod = 'month';

    /**
     * За сколько дней до окончания подписки показывать кнопку «Продлить».
     */
    const RENEW_WINDOW_DAYS = 14;

    public function mount(): void
    {
        // Сообщения после возврата с платёжной страницы банка
        if (session()->has('subscription_message')) {
            $message = session()->pull('subscription_message');
            Notification::make()
                ->title($message['title'])
                ->{$message['type']}()
                ->send();
        }

        // ?pay=<id> — сразу открываем оплату тарифа (переход после онбординга,
        // если тариф был выбран на публичной странице)
        $payTariffId = request()->integer('pay');
        if ($payTariffId && !self::tariffUnavailable($payTariffId)) {
            $tariff = Tariff::find($payTariffId);

            if ($tariff && !$tariff->isFree() && auth()->user()->activeSubscription()?->tariff_id !== $tariff->id) {
                $this->mountAction('selectTariff', ['tariff' => $tariff->id, 'primary' => true]);
            }
        }
    }

    protected function getViewData(): array
    {
        $user = auth()->user();
        $subscription = $user->activeSubscription();
        $tariffs = Tariff::active()->get();

        // Была подписка, но срок вышел (или крон ещё не пометил её истёкшей)
        $expired = $subscription ? null : $user->subscriptions()
            ->whereIn('status', [\App\Models\Subscription::STATUS_EXPIRED, \App\Models\Subscription::STATUS_ACTIVE])
            ->with('tariff')
            ->latest('starts_at')
            ->first();

        // Какая карточка получает залитую primary-кнопку (одна на страницу):
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
            'payments' => SubscriptionPayment::where('user_id', $user->id)
                // Неудавшиеся попытки не показываем — они остаются в админке для сверки
                ->where('status', '!=', SubscriptionPayment::STATUS_FAILED)
                ->with('tariff')
                ->latest()
                ->take(20)
                ->get(),
        ];
    }

    /**
     * Тариф нельзя оформить: удалён (мягко) или снят с продажи.
     */
    protected static function tariffUnavailable(int|string|null $tariffId): bool
    {
        $tariff = Tariff::withTrashed()->find($tariffId);

        return !$tariff || $tariff->trashed() || !$tariff->is_active;
    }

    /**
     * Кнопка «Подключить» в карточке тарифа: кастомная модалка подтверждения
     * вместо системного confirm браузера.
     */
    public function selectTariffAction(): Action
    {
        return Action::make('selectTariff')
            ->label(function (array $arguments) {
                if (!empty($arguments['renew'])) {
                    return 'Продлить';
                }

                $tariff = Tariff::find($arguments['tariff'] ?? null);

                return $tariff?->isFree() ? 'Подключить' : 'Оплатить и подключить';
            })
            ->extraAttributes(['class' => 'w-full justify-center'])
            // Иерархия кнопок: одна залитая primary-кнопка на странице (самое
            // ожидаемое действие), остальные — контурные серые
            ->color(fn(array $arguments) => !empty($arguments['primary']) ? 'primary' : 'gray')
            ->outlined(fn(array $arguments) => empty($arguments['primary']))
            ->requiresConfirmation()
            ->modalIcon('heroicon-o-credit-card')
            ->modalHeading(fn(array $arguments) => self::tariffUnavailable($arguments['tariff'] ?? null)
                ? 'Тариф недоступен'
                : 'Смена тарифа')
            ->modalDescription(function (array $arguments) {
                // Тариф удалён или снят с продажи (например, продление подписки
                // на архивный тариф) — предлагаем выбрать другой
                if (self::tariffUnavailable($arguments['tariff'] ?? null)) {
                    $name = Tariff::withTrashed()->find($arguments['tariff'] ?? null)?->name;

                    return 'Тариф' . ($name ? ' «' . $name . '»' : '') . ' больше недоступен для оформления. Выберите другой тариф из списка.';
                }

                $tariff = Tariff::find($arguments['tariff'] ?? null);
                $current = auth()->user()->activeSubscription();
                $yearly = $this->billingPeriod === 'year' && $tariff?->hasYearly();
                $periodText = $yearly
                    ? ' на год (' . number_format($tariff->yearly_price, 0, ',', ' ') . ' ₽)'
                    : '';

                if (!empty($arguments['renew'])) {
                    return 'Продлить тариф «' . $tariff?->name . '»' . $periodText . ' и перейти к оплате?';
                }

                // Даунгрейд на бесплатный тариф при действующем оплаченном периоде —
                // предупреждаем, что переключение произойдёт после его окончания
                if ($tariff?->isFree() && $current && !$current->tariff->isFree() && $current->ends_at?->isFuture()) {
                    return 'Тариф «' . $tariff->name . '» будет подключён ' . $current->ends_at->format('d.m.Y')
                        . ' — после окончания оплаченного периода. До этой даты продолжат действовать условия тарифа «'
                        . $current->tariff->name . '». Запланировать переключение?';
                }

                return 'Переключиться на тариф «' . $tariff?->name . '»'
                    . ($tariff?->isFree() ? '' : $periodText . ' и перейти к оплате') . '?';
            })
            ->modalSubmitActionLabel('Переключиться')
            ->modalCancelActionLabel('Отмена')
            // Для недоступного тарифа оставляем только «Отмена» — оформлять нечего
            ->modalSubmitAction(fn($action, array $arguments) => self::tariffUnavailable($arguments['tariff'] ?? null)
                ? false
                : $action)
            ->form(function (array $arguments) {
                $tariff = Tariff::find($arguments['tariff'] ?? null);

                // Выбор способа оплаты показываем, если карта ещё не привязана
                if (!$tariff || !$tariff->is_active || $tariff->isFree() || auth()->user()->yookassa_payment_method_id || !YooKassaService::isConfigured()) {
                    return [];
                }

                // Ключи — типы payment_method_data ЮKassa
                $methods = [
                    'sbp' => ['icon' => 'sbp.svg', 'title' => 'СБП', 'subtitle' => 'Приложение вашего банка — рекомендуем'],
                    'sberbank' => ['icon' => 'sberpay.svg', 'title' => 'SberPay', 'subtitle' => 'Быстрая оплата для клиентов Сбера'],
                    'tinkoff_bank' => ['icon' => 'tpay.svg', 'title' => 'T-Pay', 'subtitle' => 'Приложение Т-Банка'],
                    'bank_card' => ['icon' => 'card.svg', 'title' => 'Банковская карта', 'subtitle' => 'Любой банк'],
                    'yoo_money' => ['icon' => 'yoomoney.png', 'title' => 'ЮMoney', 'subtitle' => 'Кошелёк или привязанная карта'],
                ];

                return [
                    \Filament\Forms\Components\Radio::make('payment_method')
                        ->label('Способ оплаты')
                        ->options(array_map(fn($m) => $m['title'], $methods))
                        ->view('filament.forms.components.payment-methods')
                        ->viewData(['methods' => $methods])
                        ->default('sbp')
                        ->live(),
                    \Filament\Forms\Components\Checkbox::make('save_method')
                        ->label('Сохранить карту')
                        ->helperText('Следующие оплаты пройдут в один клик, без повторного ввода данных карты. Отвязать карту можно в любой момент на этой странице.')
                        ->visible(fn(\Filament\Forms\Get $get) => $get('payment_method') === 'bank_card' && YooKassaService::recurringEnabled())
                        ->live(),
                    \Filament\Forms\Components\Checkbox::make('auto_renew')
                        ->label('Включить автопродление')
                        ->helperText('Подписка будет продлеваться автоматически в конце оплаченного периода — мы предупредим о списании заранее. Отключается в любой момент.')
                        ->visible(fn(\Filament\Forms\Get $get) => $get('payment_method') === 'bank_card' && $get('save_method') && YooKassaService::recurringEnabled()),
                ];
            })
            ->action(fn(array $arguments, array $data) => $this->selectTariff(
                (int) $arguments['tariff'],
                (bool) ($data['save_method'] ?? false),
                (bool) ($data['auto_renew'] ?? false),
                $data['payment_method'] ?? null,
            ));
    }

    /**
     * Включает/выключает автопродление по сохранённой карте.
     */
    public function toggleAutoRenew(): void
    {
        $user = auth()->user();

        if (!$user->yookassa_payment_method_id) {
            Notification::make()
                ->title('Сначала привяжите карту')
                ->body('Отметьте «Сохранить карту» при следующей оплате — после неё автопродление можно будет включить.')
                ->info()
                ->send();
            return;
        }

        $user->update(['auto_renew' => !$user->auto_renew]);

        Notification::make()
            ->title($user->auto_renew ? 'Автопродление включено' : 'Автопродление выключено')
            ->success()
            ->send();
    }

    /**
     * Кнопка «Привязать карту» с кастомной модалкой подтверждения.
     */
    public function bindCardAction(): Action
    {
        return Action::make('bindCard')
            ->label('Привязать карту')
            ->color('gray')
            ->outlined()
            ->requiresConfirmation()
            ->modalIcon('heroicon-o-credit-card')
            ->modalHeading('Привязка карты')
            ->modalDescription('Для проверки карты спишется 1 ₽ и сразу вернётся обратно. После привязки оплата будет проходить в один клик.')
            ->modalSubmitActionLabel('Привязать')
            ->modalCancelActionLabel('Отмена')
            ->action(fn() => $this->bindCard());
    }

    /**
     * Кнопка «Отвязать карту» с кастомной модалкой подтверждения.
     */
    public function removeCardAction(): Action
    {
        return Action::make('removeCard')
            ->label('Отвязать карту')
            ->color('danger')
            ->outlined()
            ->requiresConfirmation()
            ->modalIcon('heroicon-o-trash')
            ->modalHeading('Отвязать карту?')
            ->modalDescription('Автопродление будет отключено, а оплата снова будет проходить через платёжную страницу.')
            ->modalSubmitActionLabel('Отвязать')
            ->modalCancelActionLabel('Отмена')
            ->action(fn() => $this->removePaymentMethod());
    }

    /**
     * Привязка карты без покупки: проверочный платёж на 1 ₽ с сохранением
     * способа оплаты; рубль возвращается сразу после подтверждения.
     */
    public function bindCard()
    {
        $user = auth()->user();

        if ($user->yookassa_payment_method_id) {
            Notification::make()->title('Карта уже привязана')->info()->send();
            return null;
        }

        if (!YooKassaService::recurringEnabled()) {
            Notification::make()
                ->title('Привязка карты пока недоступна')
                ->body('Автоплатежи подключаются на стороне платёжного сервиса. Попробуйте позже.')
                ->warning()
                ->send();
            return null;
        }

        // Платёж требует тариф (FK): берём текущий или первый платный — на подписку не влияет
        $tariff = $user->activeSubscription()?->tariff;
        if (!$tariff || $tariff->isFree()) {
            $tariff = Tariff::active()->where('price', '>', 0)->first();
        }

        if (!$tariff) {
            Notification::make()->title('Нет доступных тарифов')->danger()->send();
            return null;
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

        try {
            $url = YooKassaService::createPayment(
                $payment,
                route('subscription.payment.return', $payment),
                savePaymentMethod: true,
                methodType: 'bank_card',
            );
        } catch (\Throwable $e) {
            $payment->update(['status' => SubscriptionPayment::STATUS_FAILED]);
            Notification::make()->title('Не удалось привязать карту')->body($e->getMessage())->danger()->send();
            return null;
        }

        return redirect()->away($url);
    }

    /**
     * Отвязывает сохранённую карту и выключает автопродление.
     */
    public function removePaymentMethod(): void
    {
        auth()->user()->update([
            'yookassa_payment_method_id' => null,
            'payment_method_title' => null,
            'auto_renew' => false,
        ]);

        Notification::make()->title('Карта отвязана, автопродление выключено')->success()->send();
    }

    /**
     * Выбор тарифа: бесплатный активируется сразу; платный — списание с сохранённой
     * карты в один клик, либо платёж с уводом на платёжную страницу.
     * $saveMethod — сохранить карту; $autoRenew — включить автопродление (требует $saveMethod).
     * $method — выбранный способ оплаты (тип payment_method_data ЮKassa) или null.
     */
    public function selectTariff(int $tariffId, bool $saveMethod = false, bool $autoRenew = false, ?string $method = null)
    {
        $user = auth()->user();

        // Сохранение карты возможно только при оплате картой и только когда
        // магазину включены автоплатежи — иначе ЮKassa отклонит платёж
        $saveMethod = $saveMethod && $method === 'bank_card' && YooKassaService::recurringEnabled();

        if (self::tariffUnavailable($tariffId)) {
            Notification::make()
                ->title('Тариф больше недоступен')
                ->body('Этот тариф снят с продажи. Выберите другой тариф из списка.')
                ->warning()
                ->send();
            return null;
        }

        $tariff = Tariff::active()->findOrFail($tariffId);
        $subscription = $user->activeSubscription();

        if ($tariff->isFree()) {
            if ($subscription && $subscription->tariff_id === $tariff->id) {
                Notification::make()->title('Этот тариф уже подключён')->info()->send();
                return null;
            }

            // Даунгрейд с оплаченного тарифа: переключаем только после окончания
            // оплаченного периода, чтобы оплаченные лимиты не сгорали
            if ($subscription && !$subscription->tariff->isFree() && $subscription->ends_at?->isFuture()) {
                if ($user->scheduledSubscription()?->tariff_id === $tariff->id) {
                    Notification::make()->title('Переключение уже запланировано')->info()->send();
                    return null;
                }

                SubscriptionService::scheduleTariffChange($user, $tariff, $subscription->ends_at);
                Notification::make()
                    ->title('Переключение запланировано')
                    ->body('Тариф «' . $tariff->name . '» будет подключён ' . $subscription->ends_at->format('d.m.Y')
                        . ', после окончания оплаченного периода. До этого действуют условия тарифа «'
                        . $subscription->tariff->name . '».')
                    ->success()
                    ->persistent()
                    ->send();
                return null;
            }

            SubscriptionService::activate($user, $tariff);
            Notification::make()->title('Тариф «' . $tariff->name . '» подключён')->success()->send();
            return null;
        }

        if (!YooKassaService::isConfigured()) {
            Notification::make()
                ->title('Онлайн-оплата подключается')
                ->body('Пока платные тарифы можно оформить через поддержку: info@serdal.ru — мы подключим тариф вручную.')
                ->warning()
                ->persistent()
                ->send();
            return null;
        }

        $yearly = $this->billingPeriod === 'year' && $tariff->hasYearly();

        $payment = SubscriptionPayment::create([
            'user_id' => $user->id,
            'tariff_id' => $tariff->id,
            'amount' => $yearly ? $tariff->yearly_price : $tariff->price,
            'period_days' => $yearly ? 365 : $tariff->period_days,
            'status' => SubscriptionPayment::STATUS_PENDING,
            'gateway' => 'yookassa',
            'meta' => $saveMethod ? ['save_method' => true, 'auto_renew_opt_in' => $autoRenew] : null,
        ]);

        // Карта уже сохранена — списываем в один клик, без ухода на платёжную страницу
        if ($user->yookassa_payment_method_id) {
            $status = YooKassaService::createRecurringPayment($payment, $user->yookassa_payment_method_id);

            if ($status === 'succeeded') {
                SubscriptionService::applyPaidPayment($payment);
                Notification::make()
                    ->title('Оплачено')
                    ->body('Списано с карты ' . ($user->payment_method_title ?? '') . '. Тариф «' . $tariff->name . '» подключён.')
                    ->success()
                    ->send();
                return null;
            }

            if ($status === 'pending' || $status === 'waiting_for_capture') {
                Notification::make()
                    ->title('Платёж обрабатывается')
                    ->body('Подписка активируется автоматически после подтверждения оплаты.')
                    ->info()
                    ->send();
                return null;
            }

            // Списание не прошло — отправляем на обычную платёжную страницу
        }

        try {
            $url = YooKassaService::createPayment(
                $payment,
                route('subscription.payment.return', $payment),
                savePaymentMethod: $saveMethod,
                methodType: $method,
            );
        } catch (\Throwable $e) {
            $payment->update(['status' => SubscriptionPayment::STATUS_FAILED]);
            Notification::make()
                ->title('Не удалось создать платёж')
                ->body($e->getMessage())
                ->danger()
                ->send();
            return null;
        }

        return redirect()->away($url);
    }
}
