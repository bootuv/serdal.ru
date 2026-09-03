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
            'tariffs' => $tariffs,
            'lessonsUsed' => SubscriptionService::lessonsUsedThisPeriod($user),
            'payments' => SubscriptionPayment::where('user_id', $user->id)
                ->with('tariff')
                ->latest()
                ->take(20)
                ->get(),
        ];
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
            ->modalHeading('Смена тарифа')
            ->modalDescription(function (array $arguments) {
                $tariff = Tariff::find($arguments['tariff'] ?? null);
                $current = auth()->user()->activeSubscription();

                if (!empty($arguments['renew'])) {
                    return 'Продлить тариф «' . $tariff?->name . '» и перейти к оплате?';
                }

                // Даунгрейд на бесплатный тариф при действующем оплаченном периоде —
                // предупреждаем, что переключение произойдёт после его окончания
                if ($tariff?->isFree() && $current && !$current->tariff->isFree() && $current->ends_at?->isFuture()) {
                    return 'Тариф «' . $tariff->name . '» будет подключён ' . $current->ends_at->format('d.m.Y')
                        . ' — после окончания оплаченного периода. До этой даты продолжат действовать условия тарифа «'
                        . $current->tariff->name . '». Запланировать переключение?';
                }

                return 'Переключиться на тариф «' . $tariff?->name . '»'
                    . ($tariff?->isFree() ? '' : ' и перейти к оплате') . '?';
            })
            ->modalSubmitActionLabel('Переключиться')
            ->modalCancelActionLabel('Отмена')
            ->action(fn(array $arguments) => $this->selectTariff((int) $arguments['tariff']));
    }

    /**
     * Выбор тарифа: бесплатный активируется сразу,
     * платный — создаёт платёж и уводит на платёжную страницу банка.
     */
    public function selectTariff(int $tariffId)
    {
        $user = auth()->user();
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

        $payment = SubscriptionPayment::create([
            'user_id' => $user->id,
            'tariff_id' => $tariff->id,
            'amount' => $tariff->price,
            'status' => SubscriptionPayment::STATUS_PENDING,
            'gateway' => 'yookassa',
        ]);

        try {
            $url = YooKassaService::createPayment(
                $payment,
                route('subscription.payment.return', $payment),
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
