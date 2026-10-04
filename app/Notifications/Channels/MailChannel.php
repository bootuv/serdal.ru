<?php

namespace App\Notifications\Channels;

use App\Notifications\EmailVerificationCode;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Channels\MailChannel as BaseMailChannel;
use Illuminate\Notifications\Notification;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

/**
 * Сбой почты не ломает действие: урок назначен, отзыв сохранён, уведомление в кабинете есть, а письмо
 * не ушло — ошибка уходит в лог. Коды и ссылка на пароль бросают ошибку дальше: без письма действие
 * бессмысленно, вызывающий код показывает App\Support\MailDelivery::FAILED.
 */
class MailChannel extends BaseMailChannel
{
    private const MUST_DELIVER = [EmailVerificationCode::class, ResetPassword::class];

    public function send($notifiable, Notification $notification)
    {
        try {
            return parent::send($notifiable, $notification);
        } catch (TransportExceptionInterface $e) {
            foreach (self::MUST_DELIVER as $class) {
                if ($notification instanceof $class) {
                    throw $e;
                }
            }
            report($e);

            return null;
        }
    }
}
