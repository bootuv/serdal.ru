<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Письмо на прежнюю почту: почту для входа сменили (Cabinet\Account). */
class EmailChanged extends Notification
{
    use Queueable;

    public function __construct(
        public string $newEmail
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Почта для входа изменена — ' . \App\Support\Seo::SITE_NAME)
            ->greeting('Почта для входа изменена')
            ->line('Теперь в кабинет ' . \App\Support\Seo::SITE_NAME . ' входят с почтой ' . $this->newEmail . '. Письма тоже будут приходить туда.')
            ->line('Если это были не вы, срочно напишите в поддержку — адрес внизу письма.');
    }
}
