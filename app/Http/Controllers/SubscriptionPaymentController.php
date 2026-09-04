<?php

namespace App\Http\Controllers;

use App\Models\SubscriptionPayment;
use App\Services\SubscriptionService;
use App\Services\YooKassaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SubscriptionPaymentController extends Controller
{
    /**
     * Возврат пользователя с платёжной страницы ЮKassa (return_url).
     * Статус платежа всегда перепроверяется запросом к API — параметрам URL не доверяем.
     */
    public function return(SubscriptionPayment $payment)
    {
        $status = $this->syncStatus($payment);

        if (!empty($payment->meta['card_binding'])) {
            session()->flash('subscription_message', $status === SubscriptionPayment::STATUS_PAID
                ? ['type' => 'success', 'title' => 'Способ оплаты привязан! Проверочный 1 ₽ вернётся в течение нескольких дней.']
                : ['type' => 'danger', 'title' => 'Не удалось привязать способ оплаты. Попробуйте ещё раз.']);
        } elseif ($payment->isExtraLessons()) {
            session()->flash('subscription_message', match ($status) {
                SubscriptionPayment::STATUS_PAID => ['type' => 'success', 'title' => 'Оплата прошла успешно — дополнительные занятия зачислены!'],
                SubscriptionPayment::STATUS_PENDING => ['type' => 'warning', 'title' => 'Платёж ещё обрабатывается. Занятия зачислятся автоматически после подтверждения оплаты.'],
                default => ['type' => 'danger', 'title' => 'Оплата не завершена. Если деньги списались — напишите в поддержку.'],
            });
        } else {
            session()->flash('subscription_message', match ($status) {
                SubscriptionPayment::STATUS_PAID => ['type' => 'success', 'title' => 'Оплата прошла успешно — подписка активирована!'],
                SubscriptionPayment::STATUS_PENDING => ['type' => 'warning', 'title' => 'Платёж ещё обрабатывается. Подписка активируется автоматически после подтверждения оплаты.'],
                default => ['type' => 'danger', 'title' => 'Оплата не завершена. Если деньги списались — напишите в поддержку.'],
            });
        }

        return redirect()->route('filament.app.pages.subscription');
    }

    /**
     * Серверное уведомление от ЮKassa (настраивается в личном кабинете:
     * Интеграция → HTTP-уведомления, события payment.succeeded и payment.canceled).
     * Содержимому уведомления не доверяем — статус перепроверяется запросом к API.
     */
    public function callback(Request $request)
    {
        $paymentId = $request->input('object.id');

        if (!$paymentId) {
            return response()->json(['error' => 'object.id is required'], 422);
        }

        $payment = SubscriptionPayment::where('gateway_order_id', $paymentId)->first();

        if (!$payment) {
            Log::warning('[YooKassa] callback for unknown payment', ['yookassa_id' => $paymentId]);
            return response()->json(['error' => 'unknown payment'], 404);
        }

        $this->syncStatus($payment);

        return response()->json(['status' => 'ok']);
    }

    /**
     * Сверяет статус ожидающего платежа с ЮKassa; при успехе активирует подписку.
     * Возвращает итоговый статус платежа в наших терминах.
     */
    protected function syncStatus(SubscriptionPayment $payment): string
    {
        if ($payment->status !== SubscriptionPayment::STATUS_PENDING) {
            return $payment->status; // уже обработан
        }

        $status = YooKassaService::fetchStatus($payment);

        if ($status === 'succeeded') {
            // Привязка способа оплаты: сохраняем его, возвращаем проверочный рубль,
            // подписку не трогаем
            if (!empty($payment->meta['card_binding'])) {
                $payment->update(['status' => SubscriptionPayment::STATUS_PAID, 'paid_at' => now()]);
                SubscriptionService::storeSavedPaymentMethod($payment);

                if (YooKassaService::refundPayment($payment)) {
                    $payment->update(['status' => SubscriptionPayment::STATUS_REFUNDED]);
                }

                return SubscriptionPayment::STATUS_PAID;
            }

            SubscriptionService::applyPaidPayment($payment);

            return SubscriptionPayment::STATUS_PAID;
        }

        if ($status === 'canceled') {
            $payment->update(['status' => SubscriptionPayment::STATUS_FAILED]);

            return SubscriptionPayment::STATUS_FAILED;
        }

        // pending / waiting_for_capture / ошибка запроса — ждём уведомление от ЮKassa
        return SubscriptionPayment::STATUS_PENDING;
    }
}
