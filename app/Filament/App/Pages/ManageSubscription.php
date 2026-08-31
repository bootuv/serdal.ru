<?php

namespace App\Filament\App\Pages;

use App\Models\SubscriptionPayment;
use App\Models\Tariff;
use App\Services\AlfaBankService;
use App\Services\SubscriptionService;
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

        return [
            'subscription' => $subscription,
            'tariffs' => Tariff::active()->get(),
            'lessonsUsed' => SubscriptionService::lessonsUsedThisPeriod($user),
            'payments' => SubscriptionPayment::where('user_id', $user->id)
                ->with('tariff')
                ->latest()
                ->take(20)
                ->get(),
        ];
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

            SubscriptionService::activate($user, $tariff);
            Notification::make()->title('Тариф «' . $tariff->name . '» подключён')->success()->send();
            return null;
        }

        if (!AlfaBankService::isConfigured()) {
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
            'gateway' => 'alfabank',
        ]);

        try {
            $url = AlfaBankService::registerOrder(
                $payment,
                route('subscription.payment.return', $payment),
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
