<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\SubscriptionPayment;
use App\Models\User;

class PaymentReceiptController extends Controller
{
    /**
     * Печатная квитанция об оплате подписки. Скачивается в PDF
     * средствами браузера (кнопка «Скачать PDF» вызывает печать).
     */
    public function show(SubscriptionPayment $payment)
    {
        $user = auth()->user();

        if ($payment->user_id !== $user->id && $user->role !== User::ROLE_ADMIN) {
            abort(403);
        }

        if ($payment->status !== SubscriptionPayment::STATUS_PAID) {
            abort(404);
        }

        $legal = Setting::whereIn('key', ['legal_name', 'legal_inn', 'legal_ogrn'])
            ->pluck('value', 'key');

        return view('receipt', [
            'payment' => $payment->load(['tariff', 'user']),
            'legal' => $legal,
        ]);
    }
}
