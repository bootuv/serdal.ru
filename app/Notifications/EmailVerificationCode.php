<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EmailVerificationCode extends Notification
{
    use Queueable;

    /** purpose: register — регистрация, email — смена почты (код на новый адрес), password — смена пароля. */
    public function __construct(
        public string $code,
        public string $purpose = 'register'
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Код подтверждения: ' . $this->code . ' — ' . \App\Support\Seo::SITE_NAME)
            ->markdown('emails.verification-code', ['code' => $this->code, 'purpose' => $this->purpose]);
    }
}
