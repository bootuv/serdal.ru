<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Http\Request;

/**
 * Настройки публичной оферты, реквизитов и B2B-блока.
 * Все значения редактируются в админке («Настройки»), здесь — ключи и значения по умолчанию.
 */
class OfferSettings
{
    public const OFFER_DEFAULTS = [
        'offer_edition_date' => '',
        'offer_payment_provider' => 'ЮKassa',
        'offer_payment_methods' => 'банковской картой (МИР, Visa, Mastercard) или через СБП',
        'offer_refund_days' => '14',
        'offer_refund_processing_days' => '10',
    ];

    public const LEGAL_DEFAULTS = [
        'legal_name' => '',
        'legal_inn' => '',
        'legal_ogrn' => '',
        'legal_address' => '',
        'legal_email' => 'info@serdal.ru',
        'legal_phone' => '',
    ];

    public const B2B_DEFAULTS = [
        'b2b_enabled' => '1',
        'b2b_title' => 'Для образовательных центров (B2B)',
        'b2b_description' => 'Пакет для онлайн-школ и образовательных центров: white-label, администрирование и поддержка с SLA.',
        'b2b_price_label' => 'от 14 900 ₽',
        'b2b_price_note' => '5 рабочих мест включено',
        'b2b_features' => '["5 рабочих мест преподавателей включено (дополнительное место — 1 900 ₽/мес)","White-label: платформа под брендом вашего центра","Административная панель для управления преподавателями и учениками","Приоритетная поддержка и SLA","Обучение и онбординг команды"]',
        'b2b_email' => 'info@serdal.ru',
    ];

    /** Условия оферты (оплата, возврат, дата редакции) с подстановкой значений по умолчанию. */
    public static function offer(): array
    {
        $values = self::values(self::OFFER_DEFAULTS);

        return [
            'edition_date' => $values['offer_edition_date'] ?: null,
            'payment_provider' => $values['offer_payment_provider'],
            'payment_methods' => $values['offer_payment_methods'],
            'refund_days' => max(0, (int) $values['offer_refund_days']),
            'refund_processing_days' => max(0, (int) $values['offer_refund_processing_days']),
        ];
    }

    /** Реквизиты исполнителя (пустые поля остаются пустыми, e-mail всегда заполнен). */
    public static function legal(): array
    {
        return self::values(self::LEGAL_DEFAULTS);
    }

    /** Блок B2B со страницы тарифов. */
    public static function b2b(): array
    {
        $values = self::values(self::B2B_DEFAULTS);

        return [
            'enabled' => $values['b2b_enabled'] === '1',
            'title' => $values['b2b_title'],
            'description' => $values['b2b_description'],
            'price_label' => $values['b2b_price_label'],
            'price_note' => $values['b2b_price_note'],
            'features' => json_decode($values['b2b_features'], true) ?: [],
            'email' => $values['b2b_email'],
        ];
    }

    /** Название платформы и публичный адрес сайта — из конфигурации приложения. */
    public static function platform(?Request $request = null): array
    {
        $url = rtrim((string) config('app.url'), '/');
        $host = parse_url($url, PHP_URL_HOST);

        // Локально APP_URL указывает на localhost — берём адрес из текущего запроса
        if (!$host || in_array($host, ['localhost', '127.0.0.1'], true)) {
            $request = $request ?? request();
            $host = $request->getHost();
            $url = $request->getSchemeAndHttpHost();
        }

        return [
            'name' => config('app.name', 'Serdal'),
            'host' => $host,
            'url' => $url,
        ];
    }

    /** Значения из таблицы settings; пустые и отсутствующие заменяются значениями по умолчанию. */
    protected static function values(array $defaults): array
    {
        $stored = Setting::whereIn('key', array_keys($defaults))
            ->pluck('value', 'key')
            ->filter(fn($value) => $value !== null && $value !== '')
            ->all();

        return $stored + $defaults;
    }
}
