<?php

namespace App\Livewire;

use App\Models\Founder;
use App\Models\FounderContribution;
use App\Models\FounderExpense;
use App\Services\FounderService;
use App\Support\HumanDate;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Личная страница основателя (/founder) — после входа под профилем, привязанным к основателю (founders.user_id):
 * у основателя может не быть доступа в админку (профиль учителя). Остальным — «Такой страницы нет».
 * Видит только своё: взнос за текущий сбор, куда переводить, расходы и прошлые взносы.
 * «Я перевёл» — взнос ждёт подтверждения админа (FounderService::claim), напоминания прекращаются.
 */
#[Layout('components.layouts.plain', ['title' => 'Сбор на расходы'])]
class FounderPage extends Component
{
    public function mount(): void
    {
        $this->founder();
    }

    /** Основатель вошедшего пользователя; проверяется при каждом действии. */
    private function founder(): Founder
    {
        abort_unless(auth()->check(), 403);

        return Founder::where('user_id', auth()->id())->firstOrFail();
    }

    public function claim(): void
    {
        $service = app(FounderService::class);
        $n = $service->claim($this->founder(), $service->currentPeriod());
        $this->dispatch('toast', message: $n ? 'Спасибо! Отметим взнос, когда перевод придёт' : 'Взнос уже отмечен');
    }

    public function unclaim(): void
    {
        $service = app(FounderService::class);
        $service->unclaim($this->founder(), $service->currentPeriod());
        $this->dispatch('toast', message: 'Отметка снята');
    }

    public function render()
    {
        $service = app(FounderService::class);
        $founder = $this->founder();
        $period = $service->currentPeriod();
        $due = $service->dueDate($period);
        $money = fn (float $v) => FounderService::money($v);

        $mine = FounderService::ordered($service->sync($period)->where('founder_id', $founder->id));
        $unpaid = $mine->filter(fn (FounderContribution $c) => ! $c->paid_at && (float) $c->amount > 0);
        $claimed = $unpaid->isNotEmpty() && $unpaid->every(fn (FounderContribution $c) => $c->claimed_at);
        $overdue = $unpaid->isNotEmpty() && ! $claimed && today()->gt($due);
        $total = $service->monthlyTotal();

        $past = FounderContribution::where('founder_id', $founder->id)->whereDate('period', '<', $period)
            ->orderByDesc('period')->orderBy('id')->limit(36)->get();

        return view('livewire.founder-page', [
            'name' => $founder->name,
            'homeUrl' => \App\Http\Middleware\EnsureCabinetRole::homeFor(auth()->user()),
            'share' => FounderService::percent((float) $founder->share),
            'periodTitle' => 'Взнос за ' . HumanDate::month($period),
            'amount' => $money((float) ($unpaid->isNotEmpty() ? $unpaid->sum('amount') : $mine->sum('amount'))),
            'state' => match (true) {
                $mine->isEmpty() || (float) $mine->sum('amount') <= 0 => 'none',
                $unpaid->isEmpty() => 'paid',
                $claimed => 'claimed',
                default => 'due',
            },
            'dueLine' => match (true) {
                $unpaid->isEmpty() => null,
                $overdue => 'Срок был ' . HumanDate::day($due),
                default => 'Перевести ' . (in_array(HumanDate::day($due), ['сегодня', 'завтра'], true) ? HumanDate::day($due) : 'до ' . HumanDate::date($due)),
            },
            'overdue' => $overdue,
            'claimedLine' => $claimed ? 'Вы сообщили о переводе ' . HumanDate::day($unpaid->max('claimed_at')) . ' — отметим взнос, когда он придёт' : null,
            'lines' => $mine->count() > 1 ? $mine->map(fn (FounderContribution $c) => [
                'id' => $c->id,
                'label' => $c->label(),
                'amount' => $money((float) $c->amount),
                'paid' => (bool) $c->paid_at,
            ]) : collect(),
            'payment' => FounderService::paymentRows(),
            'payNumber' => FounderService::payment()['number'],
            'expenses' => FounderExpense::where('is_active', true)->where('period', '!=', FounderExpense::PERIOD_ONCE)->orderBy('sort')->orderBy('id')->get()
                ->map(fn (FounderExpense $e) => [
                    'id' => $e->id,
                    'name' => $e->name,
                    'sub' => $e->period === FounderExpense::PERIOD_YEAR ? $money((float) $e->amount) . ' в год' : null,
                    'monthly' => $money($e->monthly()),
                ]),
            'total' => $money($total),
            'past' => $past->map(fn (FounderContribution $c) => [
                'id' => $c->id,
                'title' => Str::ucfirst(HumanDate::month($c->period)) . ($c->isOneOff() ? ' · ' . mb_strtolower(mb_substr($c->label(), 0, 1)) . mb_substr($c->label(), 1) : ''),
                'sub' => $c->paid_at ? 'внесено ' . HumanDate::day($c->paid_at) : ($c->claimed_at ? 'ждёт подтверждения' : 'не внесено'),
                'debt' => ! $c->paid_at && ! $c->claimed_at && (float) $c->amount > 0,
                'amount' => $money((float) $c->amount),
            ]),
        ]);
    }
}
