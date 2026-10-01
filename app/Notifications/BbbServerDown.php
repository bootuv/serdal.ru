<?php

namespace App\Notifications;

use App\Models\BbbServer;
use App\Notifications\Messages\CabinetMessage;

/**
 * Администраторам: сервер видеосвязи не отвечает на проверки (BbbServerMonitor, несколько минут подряд).
 * Письмом тоже — занятия на этом сервере не начнутся, узнать нужно сразу, а не от учителей.
 */
class BbbServerDown extends CabinetNotification
{
    protected bool $mail = true;

    public bool $broadcastSound = true;

    public function __construct(
        public BbbServer $server,
        public string $reason,
        public bool $hasReserve,
    ) {
    }

    public function toDatabase(object $notifiable): array
    {
        return CabinetMessage::make('Сервер видеосвязи не отвечает')
            ->body("«{$this->server->name}» ({$this->server->host()}): " . mb_strtolower($this->reason) . '. '
                . ($this->hasReserve
                    ? 'Новые занятия идут на другие серверы, но занятия на этом прервались.'
                    : 'Других серверов на связи нет — новые занятия не начнутся.'))
            ->icon('video')
            ->action('Серверы видеосвязи', route('cabinet.admin.settings', ['tab' => 'video']))
            ->toArray();
    }
}
