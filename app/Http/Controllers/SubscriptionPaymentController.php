<?php

namespace App\Http\Controllers;

use App\Models\SubscriptionPayment;
use App\Services\AlfaBankService;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SubscriptionPaymentController extends Controller
{
    /**
     * Возврат пользователя с платёжной страницы банка (returnUrl/failUrl).
     * Статус платежа всегда перепроверяется запросом к шлюзу — параметрам URL не доверяем.
     */
    public function return(SubscriptionPayment $payment)
    {
        $this->syncStatus($payment);

        session()->flash('subscription_message', $payment->status === SubscriptionPayment::STATUS_PAID
            ? ['type' => 'success', 'title' => 'Оплата прошла успешно — подписка активирована!']
            : ['type' => 'danger', 'title' => 'Оплата не завершена. Если деньги списались — напишите в поддержку.']);

        return redirect()->route('filament.app.pages.subscription');
    }

    /**
     * Серверный колбэк-уведомление от банка (настраивается в личном кабинете эквайринга).
     * Работает и без него: статус проверяется при возврате пользователя.
     */
    public function callback(Request $request)
    {
        $orderId = $request->input('mdOrder') ?? $request->input('orderId');

        if (!$orderId) {
            return response()->json(['error' => 'orderId is required'], 422);
        }

        $payment = SubscriptionPayment::where('gateway_order_id', $orderId)->first();

        if (!$payment) {
            Log::warning('[AlfaBank] callback for unknown order', ['orderId' => $orderId]);
            return response()->json(['error' => 'unknown order'], 404);
        }

        $this->syncStatus($payment);

        return response()->json(['status' => 'ok']);
    }

    /**
     * Сверяет статус ожидающего платежа со шлюзом и активирует подписку при оплате.
     */
    protected function syncStatus(SubscriptionPayment $payment): void
    {
        if ($payment->status !== SubscriptionPayment::STATUS_PENDING) {
            return; // уже обработан
        }

        if (AlfaBankService::isOrderPaid($payment)) {
            SubscriptionService::applyPaidPayment($payment);
        } else {
            $payment->update(['status' => SubscriptionPayment::STATUS_FAILED]);
        }
    }
}
