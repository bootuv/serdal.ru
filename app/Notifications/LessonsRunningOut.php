<?php

namespace App\Notifications;

use App\Notifications\Messages\CabinetMessage;
use App\Services\YooKassaService;

/**
 * Учителю: занятия по тарифу заканчиваются (осталось 2 и меньше) или закончились.
 * Отправляет SubscriptionService::notifyLessonsRunningOut после проведённого занятия — один раз за период на каждый порог.
 */
class LessonsRunningOut extends CabinetNotification
{
    protected bool $mail = true;

    public function __construct(
        public int $left,
        public string $tariffName,
        public ?string $resets = null,
    ) {
    }

    public function toDatabase(object $notifiable): array
    {
        $resets = $this->resets ? ' Лимит обновится ' . $this->resets . '.' : '';
        $buy = YooKassaService::isConfigured() ? ' Докупите занятия или смените тариф.' : ' Смените тариф, чтобы продолжать занятия.';

        return CabinetMessage::make($this->left === 0 ? 'Занятия по тарифу закончились' : 'Занятия по тарифу заканчиваются')
            ->body($this->left === 0
                ? 'Новые занятия начать не получится.' . $resets . $buy
                : 'По тарифу «' . $this->tariffName . '» осталось ' . plural_ru($this->left, 'занятие', 'занятия', 'занятий') . '.' . $resets)
            ->icon('wallet')
            ->action('Тариф и платежи', route('cabinet.teacher.subscription'))
            ->toArray();
    }
}
