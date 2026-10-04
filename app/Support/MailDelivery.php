<?php

namespace App\Support;

use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

/**
 * Письмо, ради которого человек нажал кнопку (код, приглашение, ссылка на пароль). Сервис почты отказал
 * (дневной лимит Postbox, сервер недоступен) — показываем сообщение рядом с кнопкой, а не страницу ошибки.
 */
class MailDelivery
{
    public const FAILED = 'Извините, технические неполадки. Вернитесь позже.';

    /** true — письмо ушло; false — сервис почты отказал, ошибка уже в логе. */
    public static function attempt(callable $send): bool
    {
        try {
            $send();

            return true;
        } catch (TransportExceptionInterface $e) {
            report($e);

            return false;
        }
    }
}
