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
     * Данные для чека 54-ФЗ (используются, если в ЮKassa включена фискализация).
     */
    protected static function receiptFor(SubscriptionPayment $payment, string $description, array $amount): array
    {
        return [
            'customer' => ['email' => $payment->user->email],
            'items' => [[
                'description' => $description,
                'quantity' => '1.00',
                'amount' => $amount,
                'vat_code' => 1, // без НДС
                'payment_subject' => 'service',
                'payment_mode' => 'full_payment',
            ]],
        ];
    }

    protected static function descriptionFor(SubscriptionPayment $payment): string
    {
        $period = $payment->period_days >= 365 ? ' (год)' : '';

        return 'Подписка «' . $payment->tariff->name . '»' . $period . ' на serdal.ru';
    }

    protected static function amountFor(SubscriptionPayment $payment): array
    {
        return [
            'value' => number_format($payment->amount, 2, '.', ''),
            'currency' => 'RUB',
        ];
    }

    /**
     * Создаёт платёж в ЮKassa и возвращает URL платёжной страницы.
     * Записывает id платежа ЮKassa в gateway_order_id.
     * $savePaymentMethod — сохранить карту для последующих автосписаний.
     */
    public static function createPayment(SubscriptionPayment $payment, string $returnUrl, bool $savePaymentMethod = false): string
    {
        $description = self::descriptionFor($payment);
        $amount = self::amountFor($payment);

        $body = [
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
            'receipt' => self::receiptFor($payment, $description, $amount),
        ];

        if ($savePaymentMethod) {
            $body['save_payment_method'] = true;
        }

        $response = self::request()
            ->withHeaders(['Idempotence-Key' => (string) Str::uuid()])
            ->post(self::API_URL . 'payments', $body);

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
     * Автосписание по сохранённому способу оплаты (без участия пользователя).
     * Возвращает статус платежа ЮKassa: succeeded | pending | canceled.
     */
    public static function createRecurringPayment(SubscriptionPayment $payment, string $paymentMethodId): string
    {
        $description = self::descriptionFor($payment);
        $amount = self::amountFor($payment);

        $response = self::request()
            ->withHeaders(['Idempotence-Key' => (string) Str::uuid()])
            ->post(self::API_URL . 'payments', [
                'amount' => $amount,
                'capture' => true,
                'payment_method_id' => $paymentMethodId,
                'description' => $description . ' — автопродление',
                'metadata' => [
                    'payment_id' => $payment->id,
                ],
                'receipt' => self::receiptFor($payment, $description, $amount),
            ]);

        $data = $response->json();

        if (!$response->successful() || empty($data['id'])) {
            Log::error('[YooKassa] recurring payment failed', ['payment_id' => $payment->id, 'response' => $data]);

            $payment->update(['meta' => array_merge($payment->meta ?? [], ['create_response' => $data])]);

            return 'canceled';
        }

        $payment->update([
            'gateway_order_id' => $data['id'],
            'meta' => array_merge($payment->meta ?? [], ['create_response' => $data, 'status_response' => $data]),
        ]);

        return $data['status'] ?? 'pending';
    }

    /**
     * Возврат платежа (полный или частичный) на карту плательщика.
     * Возвращает true при успешном возврате.
     */
    public static function refundPayment(SubscriptionPayment $payment, ?int $amountRub = null): bool
    {
        if (!$payment->gateway_order_id) {
            return false;
        }

        $description = self::descriptionFor($payment);
        $amount = [
            'value' => number_format($amountRub ?? $payment->amount, 2, '.', ''),
            'currency' => 'RUB',
        ];

        $response = self::request()
            ->withHeaders(['Idempotence-Key' => (string) Str::uuid()])
            ->post(self::API_URL . 'refunds', [
                'payment_id' => $payment->gateway_order_id,
                'amount' => $amount,
                'description' => 'Возврат: ' . $description,
                'receipt' => self::receiptFor($payment, $description, $amount),
            ]);

        $data = $response->json();

        $payment->update([
            'meta' => array_merge($payment->meta ?? [], ['refund_response' => $data]),
        ]);

        if (!$response->successful() || !in_array($data['status'] ?? '', ['succeeded', 'pending'])) {
            Log::error('[YooKassa] refund failed', ['payment_id' => $payment->id, 'response' => $data]);

            return false;
        }

        return true;
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
