<?php

namespace App\Demo\Teacher;

use App\Demo\Concerns\TeacherBillingDemo;
use App\Demo\Screen;
use App\Models\SubscriptionPayment;
use App\Support\HumanDate;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/** «Все платежи» — App\Livewire\Cabinet\Teacher\Payments: те же платежи, что в истории на «Тарифе и платежах», по месяцам. */
class Payments extends Screen
{
    use TeacherBillingDemo;

    public const PATH = 'payments';

    public string $view = 'livewire.cabinet.teacher.payments';

    public string $title = 'Все платежи';

    public ?string $active = null;

    public function data(): array
    {
        $payments = self::demoPayments();
        $paid = $payments->where('status', SubscriptionPayment::STATUS_PAID);

        return [
            'months' => $this->months($payments),
            'sub' => implode(' · ', [
                plural_ru($payments->count(), 'платёж', 'платежа', 'платежей'),
                'оплачено ' . Money::format((int) $paid->sum('amount')),
            ]),
            'backUrl' => route('cabinet.teacher.subscription'),
            'refundDays' => 10,
        ];
    }

    /** Как Payments::months() настоящего экрана. */
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

    /** Как Payments::row(); «Чек» ведёт за пределы демо — demo-cabinet.js покажет тост. */
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
                : 'Возврат оформлен ' . HumanDate::date(Carbon::parse($p->meta['refunded_at'] ?? $p->updated_at))) : null,
            'refunded' => $refunded,
            'pending' => false,
            'payUrl' => null,
            'payUntil' => null,
            'amount' => Money::format((int) $p->amount),
            'receiptUrl' => $p->status === SubscriptionPayment::STATUS_PAID ? route('subscription.payment.receipt', $p) : null,
            'receiptLabel' => 'Чек: ' . $title . ', ' . HumanDate::date($p->created_at),
        ];
    }
}
