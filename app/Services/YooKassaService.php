<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\SubscriptionPayment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Интеграция с ЮKassa (API v3).
 * Документация: https://yookassa.ru/developers/api
 *
 * Учётные данные задаются в админке: Настройки → Эквайринг
 * (shopId и секретный ключ из личного кабинета ЮKassa;
 * для тестового магазина — тестовые ключи, отдельный переключатель не нужен).
 */
class YooKassaService
{
    const API_URL = 'https://api.yookassa.ru/v3/';

    public static function isConfigured(): bool
    {
        return (bool) (self::setting('yookassa_shop_id') && self::setting('yookassa_secret_key'));
    }

    protected static function setting(string $key): ?string
    {
        return Setting::where('key', $key)->value('value');
    }

    protected static function request()
    {
        return Http::withBasicAuth(
            (string) self::setting('yookassa_shop_id'),
            (string) self::setting('yookassa_secret_key'),
        );
    }

    /**
     * Создаёт платёж в ЮKassa и возвращает URL платёжной страницы.
     * Записывает id платежа ЮKassa в gateway_order_id.
     */
    public static function createPayment(SubscriptionPayment $payment, string $returnUrl): string
    {
        $description = 'Подписка «' . $payment->tariff->name . '» на serdal.ru';
        $amount = [
            'value' => number_format($payment->amount, 2, '.', ''),
            'currency' => 'RUB',
        ];

        $response = self::request()
            ->withHeaders(['Idempotence-Key' => (string) Str::uuid()])
            ->post(self::API_URL . 'payments', [
                'amount' => $amount,
                'capture' => true,
                'confirmation' => [
                    'type' => 'redirect',
                    'return_url' => $returnUrl,
                ],
                'description' => $description,
                'metadata' => [
                    'payment_id' => $payment->id,
                ],
                // Данные для чека (54-ФЗ); используются, если в ЮKassa включена фискализация
                'receipt' => [
                    'customer' => ['email' => $payment->user->email],
                    'items' => [[
                        'description' => $description,
                        'quantity' => '1.00',
                        'amount' => $amount,
                        'vat_code' => 1, // без НДС
                        'payment_subject' => 'service',
                        'payment_mode' => 'full_payment',
                    ]],
                ],
            ]);

        $data = $response->json();

        if (!$response->successful() || empty($data['confirmation']['confirmation_url'])) {
            Log::error('[YooKassa] create payment failed', ['payment_id' => $payment->id, 'response' => $data]);
            throw new \RuntimeException($data['description'] ?? 'Платёжный сервис недоступен, попробуйте позже.');
        }

        $payment->update([
            'gateway_order_id' => $data['id'],
            'payment_url' => $data['confirmation']['confirmation_url'],
            'meta' => array_merge($payment->meta ?? [], ['create_response' => $data]),
        ]);

        return $data['confirmation']['confirmation_url'];
    }

    /**
     * Запрашивает актуальный статус платежа у ЮKassa.
     * Возвращает: pending | waiting_for_capture | succeeded | canceled | null (ошибка запроса).
     */
    public static function fetchStatus(SubscriptionPayment $payment): ?string
    {
        if (!$payment->gateway_order_id) {
            return null;
        }

        $response = self::request()->get(self::API_URL . 'payments/' . $payment->gateway_order_id);

        $data = $response->json();

        $payment->update([
            'meta' => array_merge($payment->meta ?? [], ['status_response' => $data]),
        ]);

        if (!$response->successful()) {
            Log::error('[YooKassa] get payment status failed', ['payment_id' => $payment->id]);
            return null;
        }

        return $data['status'] ?? null;
    }
}
