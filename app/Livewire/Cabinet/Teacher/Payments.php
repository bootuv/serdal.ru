<?php

namespace App\Livewire\Cabinet\Teacher;

use App\Livewire\Cabinet\Teacher\Concerns\TeacherScreen;
use App\Models\SubscriptionPayment;
use App\Services\SubscriptionCheckoutService;
use App\Support\HumanDate;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Все платежи учителя за тариф и дополнительные занятия, по месяцам (ссылка «Все платежи» из «Тарифа и платежей»).
 * Строки — как в истории на экране тарифа (макет TeacherSubscription): статус-исключения, сумма, «Чек», «Оплатить».
 */
#[Layout('components.layouts.cabinet', ['title' => 'Все платежи', 'active' => null])]
class Payments extends Component
{
    use TeacherScreen;

    public function mount(): void
    {
        $this->authorizeTeacher();
    }

    public function render()
    {
        $payments = SubscriptionCheckoutService::payments(auth()->user());
        $paid = $payments->where('status', SubscriptionPayment::STATUS_PAID);

        return view('livewire.cabinet.teacher.payments', [
            'months' => $this->months($payments),
            'sub' => $payments->isEmpty() ? null : implode(' · ', array_filter([
                plural_ru($payments->count(), 'платёж', 'платежа', 'платежей'),
                $paid->isNotEmpty() ? 'оплачено ' . Money::format((int) $paid->sum('amount')) : null,
            ])),
            'backUrl' => Route::has('cabinet.teacher.subscription') ? route('cabinet.teacher.subscription') : url('/tutor/subscription'),
            'refundDays' => (int) (\App\Support\OfferSettings::offer()['refund_processing_days'] ?? 10),
        ]);
    }

    /** Платежи по месяцам, новые сверху. */
    private function months(Collection $payments): Collection
    {
        return $payments
            ->groupBy(fn (SubscriptionPayment $p) => $p->created_at->format('Y-m'))
            ->map(function (Collection $group) {
                $paid = $group->where('status', SubscriptionPayment::STATUS_PAID);

                return [
                    'title' => Str::ucfirst(HumanDate::month($group->first()->created_at)),
                    'note' => implode(' · ', array_filter([
                        plural_ru($group->count(), 'платёж', 'платежа', 'платежей'),
                        $paid->isNotEmpty() ? 'оплачено ' . Money::format((int) $paid->sum('amount')) : null,
                    ])),
                    'rows' => $group->map(fn (SubscriptionPayment $p) => $this->row($p))->values(),
                ];
            })
            ->values();
    }

    private function row(SubscriptionPayment $p): array
    {
        $binding = ! empty($p->meta['card_binding']);
        $refunded = $p->status === SubscriptionPayment::STATUS_REFUNDED;
        $title = $binding ? 'Привязка способа оплаты' : $p->title . (! $p->isExtraLessons() && $p->period_days >= 365 ? ' на год' : '');

        return [
            'id' => $p->id,
            'title' => $title,
            'meta' => HumanDate::at($p->created_at) . ($binding ? ' · проверочный платёж' : ''),
            'refund' => $refunded ? ($binding
                ? 'Проверочный 1 ₽ возвращён'
                : 'Возврат оформлен ' . HumanDate::date(! empty($p->meta['refunded_at']) ? Carbon::parse($p->meta['refunded_at']) : $p->updated_at)) : null,
            'refunded' => $refunded,
            'pending' => $p->status === SubscriptionPayment::STATUS_PENDING,
            'payUrl' => $p->isResumable() ? $p->payment_url : null,
            'payUntil' => $p->isResumable() ? $p->created_at->copy()->addHour()->format('H:i') : null,
            'amount' => Money::format((int) $p->amount),
            'receiptUrl' => $p->status === SubscriptionPayment::STATUS_PAID ? route('subscription.payment.receipt', $p) : null,
            'receiptLabel' => 'Чек: ' . $title . ', ' . HumanDate::date($p->created_at),
        ];
    }
}
