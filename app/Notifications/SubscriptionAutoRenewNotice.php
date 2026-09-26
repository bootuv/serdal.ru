<?php

namespace App\Notifications;

use App\Notifications\Messages\CabinetMessage;
use Illuminate\Support\Carbon;

/**
 * Предупреждение о предстоящем автосписании (требование ЮKassa —
 * информировать пользователя перед списанием).
 */
class SubscriptionAutoRenewNotice extends CabinetNotification
{
    protected bool $mail = true;

    public function __construct(
        public string $tariffName,
        public int $amount,
        public Carbon $chargeDate,
    ) {
    }

    public function toDatabase(object $notifiable): array
    {
        return CabinetMessage::make('Скоро автопродление')
            ->body(\App\Support\HumanDate::date($this->chargeDate) . ' тариф «' . $this->tariffName . '» продлится автоматически — спишем ' . \App\Support\Money::format((int) $this->amount) . ' с сохранённого способа оплаты. Отключить автопродление можно на странице тарифа')
            ->icon('repeat')
            ->action('Тариф и платежи', route('cabinet.teacher.subscription'))
            ->toArray();
    }
}
