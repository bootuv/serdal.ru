<?php

namespace App\Notifications;

use App\Models\BbbServer;
use App\Notifications\Messages\CabinetMessage;

/** Администраторам: сервер видеосвязи, о котором сообщали «не отвечает», снова на связи. */
class BbbServerRecovered extends CabinetNotification
{
    protected bool $mail = true;

    public function __construct(
        public BbbServer $server,
        public ?string $downFor = null,
    ) {
    }

    public function toDatabase(object $notifiable): array
    {
        return CabinetMessage::make('Сервер видеосвязи снова работает')
            ->body("«{$this->server->name}» ({$this->server->host()}) снова на связи"
                . ($this->downFor ? ", не отвечал {$this->downFor}." : '.'))
            ->icon('check')
            ->action('Серверы видеосвязи', route('cabinet.admin.settings', ['tab' => 'video']))
            ->toArray();
    }
}
