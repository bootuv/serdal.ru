<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\SubscriptionPayment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Интеграция с интернет-эквайрингом Альфа-Банка (платёжный шлюз REST).
 * Документация: https://pay.alfabank.ru/ecommerce/instructions/merchantManual/
 *
 * Учётные данные задаются в админке: Настройки → Эквайринг.
 */
class AlfaBankService
{
    /**
     * Боевой и тестовый адреса шлюза.
     */
    const GATEWAY_PROD = 'https://pay.alfabank.ru/payment/rest/';
    const GATEWAY_TEST = 'https://alfa.rbsuat.com/payment/rest/';

    public static function isConfigured(): bool
    {
        return (bool) (self::setting('alfabank_username') && self::setting('alfabank_password'));
    }

    protected static function setting(string $key): ?string
    {
        return Setting::where('key', $key)->value('value');
    }

    protected static function gatewayUrl(): string
    {
        if ($url = self::setting('alfabank_gateway_url')) {
            return rtrim($url, '/') . '/';
        }

        return self::setting('alfabank_test_mode') === '1' ? self::GATEWAY_TEST : self::GATEWAY_PROD;
    }

    /**
     * Регистрирует заказ в шлюзе и возвращает URL платёжной формы.
     * Записывает gateway_order_id и payment_url в платёж.
     */
    public static function registerOrder(SubscriptionPayment $payment, string $returnUrl, string $failUrl): string
    {
        $response = Http::asForm()->post(self::gatewayUrl() . 'register.do', [
            'userName' => self::setting('alfabank_username'),
            'password' => self::setting('alfabank_password'),
            // Уникальный номер заказа на нашей стороне
            'orderNumber' => 'sub-' . $payment->id . '-' . time(),
            // Сумма в копейках
            'amount' => $payment->amount * 100,
            'currency' => 643,
            'language' => 'ru',
            'returnUrl' => $returnUrl,
            'failUrl' => $failUrl,
            'description' => 'Подписка «' . $payment->tariff->name . '» на serdal.ru',
            'jsonParams' => json_encode([
                'email' => $payment->user->email,
            ]),
        ]);

        $data = $response->json();

        if (!$response->successful() || !empty($data['errorCode'])) {
            Log::error('[AlfaBank] register.do failed', ['payment_id' => $payment->id, 'response' => $data]);
            throw new \RuntimeException($data['errorMessage'] ?? 'Платёжный шлюз недоступен, попробуйте позже.');
        }

        $payment->update([
            'gateway_order_id' => $data['orderId'],
            'payment_url' => $data['formUrl'],
            'meta' => array_merge($payment->meta ?? [], ['register_response' => $data]),
        ]);

        return $data['formUrl'];
    }

    /**
     * Запрашивает статус заказа у шлюза.
     * Возвращает true, если заказ полностью оплачен (orderStatus = 2).
     */
    public static function isOrderPaid(SubscriptionPayment $payment): bool
    {
        if (!$payment->gateway_order_id) {
            return false;
        }

        $response = Http::asForm()->post(self::gatewayUrl() . 'getOrderStatusExtended.do', [
            'userName' => self::setting('alfabank_username'),
            'password' => self::setting('alfabank_password'),
            'orderId' => $payment->gateway_order_id,
        ]);

        $data = $response->json();

        $payment->update([
            'meta' => array_merge($payment->meta ?? [], ['status_response' => $data]),
        ]);

        if (!$response->successful()) {
            Log::error('[AlfaBank] getOrderStatusExtended.do failed', ['payment_id' => $payment->id]);
            return false;
        }

        // 2 = проведена полная авторизация суммы заказа
        return (int) ($data['orderStatus'] ?? -1) === 2;
    }
}
