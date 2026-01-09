<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EmailVerificationCode extends Notification
{
    use Queueable;

    public function __construct(
        public string $code
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Код подтверждения регистрации')
            ->greeting('Здравствуйте!')
            ->line('Ваш код подтверждения для регистрации:')
            ->line(new \Illuminate\Support\HtmlString('<strong style="font-size: 24px; letter-spacing: 4px;">' . $this->code . '</strong>'))
            ->line('Этот код действителен в течение 30 минут.')
            ->salutation('С уважением, Serdal.ru');
    }
}
