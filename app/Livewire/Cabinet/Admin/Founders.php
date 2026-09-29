<?php

namespace App\Livewire\Cabinet\Admin;

use App\Livewire\Cabinet\Admin\Concerns\AdminScreen;
use App\Models\Founder;
use App\Models\FounderContribution;
use App\Models\FounderExpense;
use App\Services\FounderService;
use App\Support\HumanDate;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Основатели: ежемесячный сбор на расходы платформы.
 * Вкладки: «Взносы» (текущий сбор — кто сколько вносит, отметка «Внесено», долги, окно «Настройки сбора»),
 * «История» (все месяцы: кто внёс, кто нет, кто отметил; итоги и долги по каждому основателю),
 * «Расходы» (инфраструктура с ценами в месяц или в год), «Доли» (основатели, их доли и почта для напоминаний).
 * Логика — FounderService.
 */
#[Layout('components.layouts.cabinet', ['title' => 'Основатели', 'active' => 'founders'])]
class Founders extends Component
{
    use AdminScreen;

    #[Url]
    public string $tab = 'collect';

    /** История: all — все месяцы, debts — только где кто-то не внёс. */
    #[Url(as: 'show')]
    public string $historyFilter = 'all';

    /** Сколько месяцев истории показано. */
    public int $historyMonths = self::HISTORY_PAGE;

    public const HISTORY_PAGE = 12;

    /* Окно расхода: id или 0 — новый; null — закрыто. */
    public ?int $expenseId = null;
    public string $expenseName = '';
    public string $expenseAmount = '';
    public string $expensePeriod = FounderExpense::PERIOD_MONTH;
    public string $expenseNote = '';
    public bool $expenseActive = true;

    /* Окно основателя: id или 0 — новый; null — закрыто. */
    public ?int $founderId = null;
    public string $founderName = '';
    public string $founderEmail = '';
    public string $founderShare = '';

    /* Окно «Удалить?»: expense | founder. */
    public ?string $deleting = null;

    /* Окно «Настройки сбора». */
    public bool $settingsOpen = false;
    public string $day = '';
    public string $remindDays = '';
    public bool $reminders = true;

    public function mount(): void
    {
        $this->authorizeAdmin();
        if (! in_array($this->tab, ['collect', 'history', 'expenses', 'shares'], true)) {
            $this->tab = 'collect';
        }
    }

    private function service(): FounderService
    {
        return app(FounderService::class);
    }

    private static function number(string $v): string
    {
        $v = str_replace([' ', "\u{00A0}", ','], ['', '', '.'], trim($v));

        return preg_replace('/[^\d.]/', '', $v);
    }

    /* ---------- Взносы ---------- */

    public function togglePaid(int $id): void
    {
        $this->authorizeAdmin();
        $c = FounderContribution::with('founder')->findOrFail($id);
        $this->service()->setPaid($c, ! $c->paid_at, auth()->user());
        $name = $c->founder->name;
        $month = HumanDate::month($c->period);
        $this->dispatch('toast', message: $c->paid_at ? $name . ': взнос за ' . $month . ' отмечен' : $name . ': отметка за ' . $month . ' снята');
    }

    /** «Все внесли» за месяц (ключ «2026-10»). */
    public function markAllPaid(string $month): void
    {
        $this->authorizeAdmin();
        abort_unless(preg_match('/^\d{4}-\d{2}$/', $month), 404);
        $period = \Illuminate\Support\Carbon::createFromFormat('Y-m-d', $month . '-01')->startOfDay();
        $n = $this->service()->markAllPaid($period, auth()->user());
        $this->dispatch('toast', message: $n ? 'За ' . HumanDate::month($period) . ' все внесли' : 'За этот месяц уже всё отмечено');
    }

    public function updatedHistoryFilter(): void
    {
        $this->historyMonths = self::HISTORY_PAGE;
    }

    public function moreHistory(): void
    {
        $this->historyMonths += self::HISTORY_PAGE;
    }

    /* ---------- Расходы ---------- */

    public function editExpense(int $id = 0): void
    {
        $e = $id ? FounderExpense::findOrFail($id) : null;
        $this->expenseId = $e?->id ?? 0;
        $this->expenseName = (string) $e?->name;
        $this->expenseAmount = $e ? rtrim(rtrim(number_format((float) $e->amount, 2, ',', ''), '0'), ',') : '';
        $this->expensePeriod = $e?->period ?? FounderExpense::PERIOD_MONTH;
        $this->expenseNote = (string) $e?->note;
        $this->expenseActive = $e ? (bool) $e->is_active : true;
        $this->resetErrorBag();
    }

    public function closeExpense(): void
    {
        $this->expenseId = null;
    }

    public function saveExpense(): void
    {
        $this->authorizeAdmin();
        $this->expenseAmount = self::number($this->expenseAmount);
        $this->validate([
            'expenseName' => ['required', 'string', 'max:120'],
            'expenseAmount' => ['required', 'numeric', 'min:0', 'max:100000000'],
            'expensePeriod' => ['required', 'in:month,year'],
            'expenseNote' => ['nullable', 'string', 'max:255'],
        ], [
            'expenseName.required' => 'Укажите, за что платим',
            'expenseAmount.required' => 'Укажите цену',
            'expenseAmount.numeric' => 'Только число, например 990 или 990,50',
        ]);

        $data = [
            'name' => trim($this->expenseName),
            'amount' => round((float) $this->expenseAmount, 2),
            'period' => $this->expensePeriod,
            'note' => trim($this->expenseNote) ?: null,
            'is_active' => $this->expenseActive,
        ];

        if ($this->expenseId) {
            FounderExpense::findOrFail($this->expenseId)->update($data);
            $message = 'Расход сохранён';
        } else {
            FounderExpense::create($data + ['sort' => (int) FounderExpense::max('sort') + 1]);
            $message = 'Расход добавлен';
        }

        $this->expenseId = null;
        $this->dispatch('toast', message: $message);
    }

    /* ---------- Доли ---------- */

    public function editFounder(int $id = 0): void
    {
        $f = $id ? Founder::findOrFail($id) : null;
        $this->founderId = $f?->id ?? 0;
        $this->founderName = (string) $f?->name;
        $this->founderEmail = (string) $f?->email;
        $this->founderShare = $f ? rtrim(rtrim(number_format((float) $f->share, 2, ',', ''), '0'), ',') : '';
        $this->resetErrorBag();
    }

    public function closeFounder(): void
    {
        $this->founderId = null;
    }

    public function saveFounder(): void
    {
        $this->authorizeAdmin();
        $this->founderShare = self::number($this->founderShare);
        $this->founderEmail = trim($this->founderEmail);
        $this->validate([
            'founderName' => ['required', 'string', 'max:120'],
            'founderEmail' => ['nullable', 'email', 'max:255'],
            'founderShare' => ['required', 'numeric', 'min:0', 'max:100'],
        ], [
            'founderName.required' => 'Укажите имя',
            'founderEmail.email' => 'Проверьте адрес почты',
            'founderShare.required' => 'Укажите долю в процентах',
            'founderShare.numeric' => 'Только число, например 50 или 33,33',
            'founderShare.max' => 'Не больше 100 %',
        ]);

        $data = [
            'name' => trim($this->founderName),
            'email' => $this->founderEmail ?: null,
            'share' => round((float) $this->founderShare, 2),
        ];

        if ($this->founderId) {
            Founder::findOrFail($this->founderId)->update($data);
            $message = 'Доля сохранена';
        } else {
            Founder::create($data + ['sort' => (int) Founder::max('sort') + 1]);
            $message = 'Основатель добавлен';
        }

        $this->founderId = null;
        $this->dispatch('toast', message: $message);
    }

    /* ---------- Удаление ---------- */

    public function askDelete(string $what): void
    {
        $this->deleting = in_array($what, ['expense', 'founder'], true) ? $what : null;
    }

    public function cancelDelete(): void
    {
        $this->deleting = null;
    }

    public function confirmDelete(): void
    {
        $this->authorizeAdmin();
        if ($this->deleting === 'expense' && $this->expenseId) {
            FounderExpense::whereKey($this->expenseId)->delete();
            $this->expenseId = null;
            $this->dispatch('toast', message: 'Расход удалён');
        } elseif ($this->deleting === 'founder' && $this->founderId) {
            Founder::whereKey($this->founderId)->delete();
            $this->founderId = null;
            $this->dispatch('toast', message: 'Основатель удалён');
        }
        $this->deleting = null;
    }

    /* ---------- Настройки сбора ---------- */

    public function openSettings(): void
    {
        $s = FounderService::settings();
        $this->day = (string) $s['day'];
        $this->remindDays = (string) $s['remindDays'];
        $this->reminders = $s['reminders'];
        $this->resetErrorBag();
        $this->settingsOpen = true;
    }

    public function closeSettings(): void
    {
        $this->settingsOpen = false;
    }

    public function saveSettings(): void
    {
        $this->authorizeAdmin();
        $this->day = preg_replace('/\D+/', '', $this->day);
        $this->remindDays = preg_replace('/\D+/', '', $this->remindDays);
        $this->validate([
            'day' => ['required', 'integer', 'min:1', 'max:31'],
            'remindDays' => ['required', 'integer', 'min:0', 'max:28'],
        ], [
            'day.required' => 'Укажите число от 1 до 31',
            'day.min' => 'Число от 1 до 31',
            'day.max' => 'Число от 1 до 31',
            'remindDays.required' => 'Укажите число дней, 0 — только в день сбора',
            'remindDays.max' => 'Не больше 28 дней',
        ]);

        FounderService::saveSettings((int) $this->day, (int) $this->remindDays, $this->reminders);
        $this->settingsOpen = false;
        $this->dispatch('toast', message: 'Настройки сбора сохранены');
    }

    /* ---------- Вид ---------- */

    /** Вкладка «История»: итоги по основателям и месяцы с отметками. */
    private function historyView(\Illuminate\Support\Carbon $current): array
    {
        $service = $this->service();
        $money = fn (float $v) => FounderService::money($v);
        $months = $service->journal($current, $this->historyFilter === 'debts');

        return [
            'totals' => $service->totals()->map(fn (array $t) => [
                'id' => $t['founder']->id,
                'name' => $t['founder']->name,
                'sub' => 'Внесено всего ' . $money($t['paid']),
                'debt' => $t['debt'] > 0 ? 'Долг ' . $money($t['debt']) . ' · ' . plural_ru($t['debtMonths'], 'месяц', 'месяца', 'месяцев') : null,
            ]),
            'hasMore' => $months->count() > $this->historyMonths,
            'months' => $months->take($this->historyMonths)->map(function (array $m) use ($service, $money, $current) {
                $due = $service->dueDate($m['period']);
                $items = $m['contributions'];
                $unpaid = $items->filter(fn ($c) => ! $c->paid_at && (float) $c->amount > 0)->count();
                $isCurrent = $m['period']->equalTo($current->copy()->startOfMonth());

                return [
                    'key' => $m['period']->format('Y-m'),
                    'title' => Str::ucfirst(HumanDate::month($m['period'])),
                    'sub' => $money((float) $items->sum('amount')) . ' · ' . ($isCurrent && today()->lte($due) ? 'сбор ' : 'сбор был ') . HumanDate::day($due),
                    'status' => $unpaid ? 'не внесли ' . $unpaid . ' из ' . $items->count() : 'все внесли',
                    'unpaid' => $unpaid,
                    'overdue' => $unpaid && today()->gt($due),
                    'rows' => $items->map(fn (FounderContribution $c) => [
                        'id' => $c->id,
                        'name' => $c->founder?->name ?? 'Удалённый основатель',
                        'amount' => $money((float) $c->amount),
                        'paid' => (bool) $c->paid_at,
                        'overdue' => $service->isOverdue($c) && (float) $c->amount > 0,
                        'sub' => $c->paid_at
                            ? 'внесено ' . HumanDate::day($c->paid_at) . ($c->paidBy ? ', отметка — ' . $c->paidBy->name : '')
                            : 'не внесено',
                    ]),
                ];
            }),
        ];
    }

    public function render()
    {
        $service = $this->service();
        $settings = FounderService::settings();
        $total = $service->monthlyTotal();
        $sharesTotal = $service->sharesTotal();
        $founders = $service->founders();
        $period = $service->currentPeriod();
        $due = $service->dueDate($period);
        $overdue = today()->gt($due);
        $money = fn (float $v) => FounderService::money($v);

        $contributions = $founders->isNotEmpty() ? $service->sync($period) : collect();
        $unpaid = $contributions->whereNull('paid_at')->count();
        $debts = $service->debts();
        $pastDebts = $debts->filter(fn (FounderContribution $c) => $c->period->lt($period->copy()->startOfMonth()))->values();
        $history = $this->tab === 'history' ? $this->historyView($period) : null;

        return view('livewire.cabinet.admin.founders', [
            'tabs' => ['collect' => 'Взносы', 'history' => 'История', 'expenses' => 'Расходы', 'shares' => 'Доли'],
            'tabCounts' => ['history' => $debts->count()],
            'factLine' => implode(' · ', array_filter([
                'Расходы ' . $money($total) . ' в месяц',
                $founders->isNotEmpty() ? 'сбор ' . ($overdue ? 'был ' : '') . HumanDate::day($due) : null,
            ])),
            'total' => $total,
            'money' => $money,
            'sharesTotal' => $sharesTotal,
            'sharesOk' => abs($sharesTotal - 100) < 0.01,

            'periodTitle' => 'Взнос за ' . HumanDate::month($period),
            'periodSub' => ($overdue ? 'Срок прошёл ' : 'Сбор ') . HumanDate::day($due) . ' · ' . ($unpaid ? 'не внесли ' . $unpaid . ' из ' . $contributions->count() : 'все внесли'),
            'overdue' => $overdue,
            'contributions' => $contributions->map(fn (FounderContribution $c) => [
                'id' => $c->id,
                'name' => $c->founder->name,
                'sub' => implode(' · ', array_filter([
                    FounderService::percent((float) $c->founder->share),
                    $c->paid_at ? 'внесено ' . HumanDate::day($c->paid_at) : null,
                    ! $c->paid_at && ! $c->founder->email ? 'нет почты — напоминания не придут' : null,
                ])),
                'amount' => $money((float) $c->amount),
                'paid' => (bool) $c->paid_at,
            ]),
            'remindLine' => $settings['reminders']
                ? 'Напоминание на почту ' . ($settings['remindDays'] ? 'за ' . plural_ru($settings['remindDays'], 'день', 'дня', 'дней') . ', ' : '') . 'в день сбора и через ' . plural_ru(FounderService::OVERDUE_DAYS, 'день', 'дня', 'дней') . ', если не внесено'
                : 'Напоминания на почту выключены',
            // На «Взносах» — долги прошлых месяцев; текущий месяц и так в фокус-блоке
            'debts' => $pastDebts->map(fn (FounderContribution $c) => [
                'id' => $c->id,
                'name' => $c->founder->name,
                'sub' => 'за ' . HumanDate::month($c->period) . ' · сбор был ' . HumanDate::day($service->dueDate($c->period)),
                'amount' => $money((float) $c->amount),
            ]),
            'debtsTotal' => $money((float) $pastDebts->sum('amount')),

            'expenses' => $service->expenses()->map(fn (FounderExpense $e) => [
                'id' => $e->id,
                'name' => $e->name,
                'sub' => implode(' · ', array_filter([
                    $e->period === FounderExpense::PERIOD_YEAR ? $money((float) $e->amount) . ' в год' : null,
                    $e->note,
                ])),
                'monthly' => $money($e->monthly()),
                'active' => (bool) $e->is_active,
            ]),

            'founders' => $founders->map(fn (Founder $f) => [
                'id' => $f->id,
                'name' => $f->name,
                'sub' => $f->email ?: 'Почта не указана — напоминания не придут',
                'share' => FounderService::percent((float) $f->share),
                'amount' => $money(FounderService::amountFor($f, $total)),
            ]),

            'history' => $history,
            'historyFilters' => ['all' => 'Все месяцы', 'debts' => 'Где не внесли · ' . $debts->groupBy(fn ($c) => $c->period->format('Y-m'))->count()],

            'expenseMonthly' => $this->expensePeriod === FounderExpense::PERIOD_YEAR && is_numeric(self::number($this->expenseAmount))
                ? 'В месяц — ' . $money((float) self::number($this->expenseAmount) / 12)
                : null,
        ]);
    }
}
