<?php

namespace App\Notifications;

use App\Notifications\Messages\CabinetMessage;
use App\Services\FounderService;
use App\Support\HumanDate;
use Illuminate\Support\Carbon;

/** Админу: основатель нажал «Я перевёл» на личной странице сбора — взнос нужно подтвердить. */
class FounderClaimedPayment extends CabinetNotification
{
    public function __construct(
        public string $founderName,
        public float $amount,
        public Carbon $period,
    ) {
    }

    public function toDatabase(object $notifiable): array
    {
        return CabinetMessage::make('Взнос ждёт подтверждения')
            ->body($this->founderName . ': перевод ' . FounderService::money($this->amount) . ' за ' . HumanDate::month($this->period) . '. Проверьте поступление и отметьте взнос')
            ->icon('wallet')
            ->action('Открыть взносы', route('cabinet.admin.founders'))
            ->toArray();
    }
}
