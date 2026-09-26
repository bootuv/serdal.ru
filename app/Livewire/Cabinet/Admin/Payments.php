<?php

namespace App\Livewire\Cabinet\Admin;

use App\Livewire\Cabinet\Admin\Concerns\AdminScreen;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Services\AdminPaymentsService;
use App\Support\HumanDate;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Платежи учителей за тариф и дополнительные занятия (макет AdminPayments).
 * Вкладки: «Платежи» — очередь «Ожидают оплаты дольше суток», список с фильтрами, окно платежа (подтвердить оплату, возврат);
 * «Подписки» — только просмотр, изменить тариф можно в карточке учителя.
 */
#[Layout('components.layouts.cabinet', ['title' => 'Платежи', 'active' => 'payments'])]
class Payments extends Component
{
    use AdminScreen;

    public const PER_PAGE = 20;

    #[Url]
    public string $tab = 'payments';

    #[Url]
    public string $status = 'all';

    #[Url]
    public string $purpose = 'all';

    #[Url]
    public string $period = 'month';

    #[Url]
    public string $q = '';

    #[Url]
    public string $sub = 'active';

    public int $limit = self::PER_PAGE;

    /** Открытый платёж и окно: details | confirm | refund. */
    public ?int $open = null;

    public string $modal = '';

    public bool $checked = false;

    public function mount(): void
    {
        $this->authorizeAdmin();
        $this->tab = in_array($this->tab, ['payments', 'subscriptions'], true) ? $this->tab : 'payments';
    }

    public function updated(string $name): void
    {
        if (in_array($name, ['status', 'purpose', 'period', 'q', 'sub', 'tab'], true)) {
            $this->limit = self::PER_PAGE;
        }
    }

    public function setPurpose(string $value): void
    {
        $this->purpose = $value;
        $this->limit = self::PER_PAGE;
    }

    public function setPeriod(string $value): void
    {
        $this->period = $value;
        $this->limit = self::PER_PAGE;
    }

    public function resetFilters(): void
    {
        $this->status = 'all';
        $this->purpose = 'all';
        $this->period = 'month';
        $this->q = '';
        $this->limit = self::PER_PAGE;
    }

    public function more(): void
    {
        $this->limit += self::PER_PAGE;
    }

    /* ---------- Окно платежа ---------- */

    public function openPayment(int $id): void
    {
        $this->open = $this->payment($id)->id;
        $this->modal = 'details';
        $this->checked = false;
    }

    public function closeModal(): void
    {
        $this->modal = '';
        $this->open = null;
    }

    public function backToDetails(): void
    {
        $this->modal = $this->open ? 'details' : '';
    }

    public function openConfirm(): void
    {
        if ($this->open && $this->service()->canConfirm($this->payment($this->open))) {
            $this->modal = 'confirm';
            $this->checked = false;
        }
    }

    public function openRefund(): void
    {
        if ($this->open && $this->service()->canRefund($this->payment($this->open))) {
            $this->modal = 'refund';
        }
    }

    public function confirmPayment(): void
    {
        $payment = $this->payment((int) $this->open);
        if (! $this->checked || ! $this->service()->canConfirm($payment)) {
            return;
        }

        $this->service()->confirm($payment, auth()->user());
        $payment->refresh();

        $this->closeModal();
        $this->dispatch('toast', message: $payment->isExtraLessons()
            ? 'Оплата подтверждена · ' . plural_ru((int) $payment->extra_lessons, 'занятие зачислено', 'занятия зачислены', 'занятий зачислены')
            : 'Оплата подтверждена · тариф «' . $payment->tariff?->name . '» подключён');
    }

    public function refundPayment(): void
    {
        $payment = $this->payment((int) $this->open);
        if (! $this->service()->canRefund($payment)) {
            return;
        }

        $result = $this->service()->refund($payment, auth()->user());
        if (! $result['ok']) {
            $this->modal = 'details';
            $this->dispatch('toast', message: 'Не удалось оформить возврат: ' . $result['error'], tone: 'danger');

            return;
        }

        $sub = $result['subscription'];
        $this->closeModal();
        $this->dispatch('toast', message: match (true) {
            $payment->isExtraLessons() => 'Возврат оформлен · ' . plural_ru((int) $payment->extra_lessons, 'занятие списано', 'занятия списаны', 'занятий списаны'),
            $sub !== null && $sub->isActive() && $sub->ends_at !== null => 'Возврат оформлен · тариф до ' . HumanDate::date($sub->ends_at),
            $sub !== null && ! $sub->isActive() => 'Возврат оформлен · тариф «' . $sub->tariff?->name . '» закончился',
            default => 'Возврат оформлен',
        });
    }

    /* ---------- Данные ---------- */

    private function service(): AdminPaymentsService
    {
        return app(AdminPaymentsService::class);
    }

    private function payment(int $id): SubscriptionPayment
    {
        return SubscriptionPayment::with(['user', 'tariff', 'subscription'])->findOrFail($id);
    }

    public static function userUrl(?int $id): string
    {
        if (! $id) {
            return '#';
        }

        return route('cabinet.admin.user', ['user' => $id]);
    }

    public function render()
    {
        $service = $this->service();
        $stats = $service->monthStats(now());

        return view('livewire.cabinet.admin.payments', [
            'monthLine' => $this->monthLine($stats),
            'tabs' => ['payments' => 'Платежи', 'subscriptions' => 'Подписки'],
        ] + ($this->tab === 'subscriptions' ? $this->subscriptionsData($service) : $this->paymentsData($service)) + [
            'cur' => $this->open && $this->modal ? $this->details(SubscriptionPayment::with(['user', 'tariff', 'subscription'])->find($this->open)) : null,
        ]);
    }

    private function monthLine(array $s): string
    {
        $month = HumanDate::month(now(), genitive: false);
        $prepositional = ['январь' => 'январе', 'февраль' => 'феврале', 'март' => 'марте', 'апрель' => 'апреле', 'май' => 'мае', 'июнь' => 'июне',
            'июль' => 'июле', 'август' => 'августе', 'сентябрь' => 'сентябре', 'октябрь' => 'октябре', 'ноябрь' => 'ноябре', 'декабрь' => 'декабре'][$month] ?? $month;

        if ($s['paidCount'] === 0 && $s['refundCount'] === 0) {
            return 'В ' . $prepositional . ' оплат пока нет';
        }

        return implode(' · ', array_filter([
            'В ' . $prepositional . ' оплачено на ' . Money::format($s['paidSum']),
            plural_ru($s['paidCount'], 'оплата', 'оплаты', 'оплат'),
            $s['refundCount'] ? plural_ru($s['refundCount'], 'возврат', 'возврата', 'возвратов') . ' на ' . Money::format($s['refundSum']) : null,
        ]));
    }

    private function paymentsData(AdminPaymentsService $service): array
    {
        $filters = ['purpose' => $this->purpose, 'period' => $this->period, 'q' => $this->q];
        $counts = collect([SubscriptionPayment::STATUS_FAILED, SubscriptionPayment::STATUS_REFUNDED, SubscriptionPayment::STATUS_PENDING])
            ->mapWithKeys(fn ($s) => [$s => $service->query($filters + ['status' => $s])->count()]);

        $query = $service->query($filters + ['status' => $this->status]);
        $total = (clone $query)->count();
        $rows = $query->limit($this->limit)->get()->map(fn (SubscriptionPayment $p) => $this->row($p));

        $purposes = ['all' => 'Все назначения']
            + $service->purposeTariffs()->mapWithKeys(fn ($t) => [(string) $t->id => 'Тариф «' . $t->name . '»'])->all()
            + [AdminPaymentsService::PURPOSE_EXTRA => 'Дополнительные занятия', AdminPaymentsService::PURPOSE_CARD => 'Привязка карты'];
        $periods = [
            'week' => 'Последние 7 дней',
            'month' => Str::ucfirst(HumanDate::month(now())),
            'prev' => Str::ucfirst(HumanDate::month(now()->subMonthNoOverflow())),
            'all' => 'За всё время',
        ];

        $stuck = $service->stuck($this->q);

        return [
            'queue' => $stuck->map(fn (SubscriptionPayment $p) => [
                'id' => $p->id,
                'user' => $p->user,
                'name' => $p->user?->name ?? 'Удалённый учитель',
                'meta' => $service->title($p) . ' · ' . $service->amount($p) . ' · создан ' . HumanDate::day($p->created_at) . ' · ',
                'age' => 'ждёт ' . plural_ru(max(1, (int) floor($p->created_at->diffInHours(now()) / 24)), 'день', 'дня', 'дней'),
            ]),
            'queueSum' => Money::format((int) $stuck->sum('amount')),
            'statuses' => [
                'all' => 'Все',
                SubscriptionPayment::STATUS_FAILED => 'Не прошли · ' . $counts[SubscriptionPayment::STATUS_FAILED],
                SubscriptionPayment::STATUS_REFUNDED => 'Возвраты · ' . $counts[SubscriptionPayment::STATUS_REFUNDED],
                SubscriptionPayment::STATUS_PENDING => 'Ожидают оплаты · ' . $counts[SubscriptionPayment::STATUS_PENDING],
            ],
            'purposes' => $purposes,
            'purposeLabel' => $purposes[$this->purpose] ?? 'Все назначения',
            'periods' => $periods,
            'periodLabel' => $periods[$this->period] ?? 'За всё время',
            'hasReset' => $this->status !== 'all' || $this->purpose !== 'all' || $this->period !== 'month',
            'rows' => $rows,
            'total' => $total,
        ];
    }

    private function row(SubscriptionPayment $p): array
    {
        $service = $this->service();
        $method = $service->method($p);
        $today = $p->created_at->isToday() || $p->created_at->isYesterday();

        return [
            'id' => $p->id,
            'user' => $p->user,
            'name' => $p->user?->name ?? 'Удалённый учитель',
            'what' => $service->title($p) . ($method ? ' · ' . Str::before($method, ',') : ''),
            'amount' => $service->amount($p),
            'off' => in_array($p->status, [SubscriptionPayment::STATUS_FAILED, SubscriptionPayment::STATUS_REFUNDED], true),
            'when' => Str::ucfirst($today ? HumanDate::at($p->created_at) : HumanDate::day($p->created_at)),
            'status' => $p->status,
        ];
    }

    private function details(?SubscriptionPayment $p): ?array
    {
        if (! $p) {
            return null;
        }

        $service = $this->service();
        [$stateLabel, $stateText] = $service->state($p);
        $method = $service->method($p);
        $name = $p->user?->name ?? 'Удалённый учитель';

        return [
            'id' => $p->id,
            'what' => $service->title($p),
            'headSub' => $name . ' · создан ' . HumanDate::at($p->created_at),
            'amount' => $service->amount($p),
            'status' => $p->status,
            'stateLabel' => $stateLabel,
            'stateText' => $stateText,
            'name' => $name,
            'userUrl' => self::userUrl($p->user_id),
            'gives' => $service->gives($p),
            'method' => $method ?? ($p->status === SubscriptionPayment::STATUS_PENDING ? 'Ещё не выбран' : 'Не известен'),
            'methodType' => $p->meta['status_response']['payment_method']['type'] ?? null,
            'yk' => $p->gateway_order_id,
            'note' => $p->status === SubscriptionPayment::STATUS_REFUNDED ? $this->refundNote($p) : null,
            'receiptUrl' => $p->status === SubscriptionPayment::STATUS_PAID && Route::has('subscription.payment.receipt') ? route('subscription.payment.receipt', $p) : null,
            'canConfirm' => $service->canConfirm($p),
            'canRefund' => $service->canRefund($p),
            'confirmSub' => $name . ' · ' . $service->amount($p),
            'confirmEffect' => $service->canConfirm($p) ? $service->confirmEffect($p) : '',
            'chkSub' => implode(' · ', array_filter([$p->gateway_order_id ? 'Платёж …' . substr($p->gateway_order_id, -6) : null, $service->amount($p)])),
            'refundSub' => $name . ' · ' . $service->title($p),
            'refundEffect' => $service->canRefund($p) ? $service->refundEffect($p) : '',
            'refundMoney' => $service->canRefund($p) ? $service->refundMoney($p) : '',
        ];
    }

    private function refundNote(SubscriptionPayment $p): ?string
    {
        if ($this->service()->isBinding($p)) {
            return null;
        }

        return \App\Models\ReferralReward::where('payment_id', $p->id)->where('status', \App\Models\ReferralReward::STATUS_REVOKED)->exists()
            ? 'Бонусы партнёрской программы за этот платёж списаны.'
            : 'Учитель получил уведомление о возврате.';
    }

    private function subscriptionsData(AdminPaymentsService $service): array
    {
        $active = $service->subscriptions('active', $this->q);
        $segment = in_array($this->sub, ['active', 'soon', 'gift', 'ended'], true) ? $this->sub : 'active';
        $list = $segment === 'active' ? $active : $service->subscriptions($segment, $this->q);
        $failed = $service->failedRenewals();

        return [
            'segments' => [
                'active' => 'Действующие · ' . $active->count(),
                'soon' => 'Заканчиваются за неделю · ' . $active->filter(fn (Subscription $s) => $service->endsSoon($s))->count(),
                'gift' => 'Предоставлены бесплатно · ' . $active->filter(fn (Subscription $s) => $s->isComplimentary())->count(),
                'ended' => 'Закончились',
            ],
            'subs' => $list->take($this->limit)->map(fn (Subscription $s) => $this->subRow($s, $failed->get($s->user_id))),
            'total' => $list->count(),
        ];
    }

    private function subRow(Subscription $s, ?SubscriptionPayment $failed): array
    {
        $tariff = $s->tariff;
        $gift = $s->isComplimentary();
        $yearly = (int) $s->payments->where('status', SubscriptionPayment::STATUS_PAID)->max('period_days') >= 365;
        $per = (int) $tariff->period_days === 30 ? 'в месяц' : 'за ' . plural_ru((int) $tariff->period_days, 'день', 'дня', 'дней');
        $ended = $s->ends_at !== null && ! $s->ends_at->isFuture();
        $soon = ! $ended && $this->service()->endsSoon($s);
        $user = $s->user;
        $lastPayment = $s->payments->sortByDesc('created_at')->first();
        $failedRecently = $failed && (! $s->ends_at || $failed->created_at->gte($s->ends_at->copy()->subDays(5)));

        return [
            'id' => $s->id,
            'user' => $user,
            'url' => self::userUrl($user?->id),
            'tariff' => $tariff->name,
            'price' => match (true) {
                $tariff->isFree() => 'бесплатный тариф',
                $gift => 'вместо ' . Money::format((int) $tariff->price) . ' ' . $per,
                $yearly => Money::format((int) $s->price) . ' за год',
                default => Money::format((int) $s->price) . ' ' . $per,
            },
            'until' => match (true) {
                $s->ends_at === null => 'бессрочно',
                $ended => 'закончился ' . HumanDate::day($s->ends_at),
                $soon => 'закончится ' . HumanDate::day($s->ends_at),
                default => 'до ' . HumanDate::date($s->ends_at),
            },
            'soon' => $soon,
            'gift' => $gift,
            'note' => match (true) {
                $ended && $lastPayment?->status === SubscriptionPayment::STATUS_REFUNDED => 'оформлен возврат',
                $failedRecently => 'продление не прошло' . ($ended ? '' : ' ' . HumanDate::date($failed->created_at)),
                ! $ended && $s->ends_at && ! $tariff->isFree() && $user?->auto_renew && $user?->yookassa_payment_method_id => 'продлится автоматически',
                default => null,
            },
            'extra' => (int) $user?->extra_lessons_balance > 0 ? plural_ru((int) $user->extra_lessons_balance, 'занятие', 'занятия', 'занятий') : '',
        ];
    }
}
