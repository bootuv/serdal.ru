<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Текст без контактов: телефона, ссылки, почты и @имени (публичные отзывы — «Без телефонов и ссылок»).
 * Ошибка говорит, что именно убрать.
 */
class NoContacts implements ValidationRule
{
    /** Что нашлось в тексте: phone | link | handle | null. */
    public static function find(string $text): ?string
    {
        // Телефон: 10+ цифр подряд, разделённых пробелами, дефисами, скобками, точками («+7 (900) 123-45-67», «89001234567»)
        if (preg_match('/(?<!\d)\+?\d(?:[\s\-().]*\d){9,}/u', $text)) {
            return 'phone';
        }

        // Почта и @имя в мессенджерах
        if (preg_match('/[\w.+\-]+@[\w\-]+\.[\w.]+/u', $text) || preg_match('/(?<![\w.])@[a-z0-9_]{3,}/iu', $text)) {
            return 'handle';
        }

        // Ссылка: протокол, www или адрес с доменом (t.me/…, site.ru, сайт.рф)
        if (preg_match('~https?://|www\.|\b[a-zа-яё0-9\-]+\.(?:ru|рф|su|com|net|org|me|io|info|pro|online|site|app|dev|by|kz|ua|link|ly|gg|tv|to|cc|biz|xyz)\b~iu', $text)) {
            return 'link';
        }

        return null;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $message = match (self::find((string) $value)) {
            'phone' => 'Уберите номер телефона — в отзыве его не публикуем.',
            'handle' => 'Уберите почту или @имя — контакты в отзыве не публикуем.',
            'link' => 'Уберите ссылку — в отзыве их не публикуем.',
            default => null,
        };

        if ($message) {
            $fail($message);
        }
    }
}
