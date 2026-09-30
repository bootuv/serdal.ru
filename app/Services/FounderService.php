<?php

namespace App\Services;

use App\Models\Founder;
use App\Models\FounderContribution;
use App\Models\FounderExpense;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\FounderContributionReminder;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Раздел «Основатели»: доли, расходы на инфраструктуру и ежемесячный сбор.
 *
 * Взнос за месяц = расходы в месяц (годовые — /12, выключенные не считаются) × доля основателя.
 * Разовый расход собирается отдельной строкой (своя отметка «Внесено») вместе со сбором за выбранный месяц.
 * Сбор — раз в месяц в назначенный день (founders_contribution_day; в коротком месяце — последний день).
 * Пока взнос не отмечен внесённым, сумма пересчитывается от текущих расходов и долей; внесённый — не меняется.
 * Напоминания на почту: за N дней до дня сбора, в сам день и через OVERDUE_DAYS дней, если не внесено.
 */
class FounderService
{
    public const OVERDUE_DAYS = 3;

    public const DEFAULTS = [
        'founders_contribution_day' => '5',
        'founders_remind_days' => '3',
        'founders_reminders_enabled' => '1',
    ];

    /* ---------- Настройки сбора ---------- */

    public static function settings(): array
    {
        $stored = Setting::whereIn('key', array_keys(self::DEFAULTS))->pluck('value', 'key');
        $get = fn (string $key) => filled($stored[$key] ?? null) ? (string) $stored[$key] : self::DEFAULTS[$key];

        return [
            'day' => max(1, min(31, (int) $get('founders_contribution_day'))),
            'remindDays' => max(0, (int) $get('founders_remind_days')),
            'reminders' => $get('founders_reminders_enabled') === '1',
        ];
    }

    public static function saveSettings(int $day, int $remindDays, bool $reminders): void
    {
        $values = [
            'founders_contribution_day' => (string) max(1, min(31, $day)),
            'founders_remind_days' => (string) max(0, min(28, $remindDays)),
            'founders_reminders_enabled' => $reminders ? '1' : '0',
        ];

        foreach ($values as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }
    }

    /* ---------- Расходы и доли ---------- */

    public function founders(): Collection
    {
        return Founder::orderBy('sort')->orderBy('id')->get();
    }

    public function expenses(): Collection
    {
        return FounderExpense::orderByDesc('is_active')->orderBy('sort')->orderBy('id')->get();
    }

    /** Расходы в месяц: годовые — делённые на 12, выключенные не считаются. */
    public function monthlyTotal(): float
    {
        return round(FounderExpense::where('is_active', true)->where('period', '!=', FounderExpense::PERIOD_ONCE)->get()->sum(fn (FounderExpense $e) => $e->monthly()), 2);
    }

    public function sharesTotal(): float
    {
        return round((float) Founder::sum('share'), 2);
    }

    /**
     * Разовые расходы, которые собираем вместе со сбором за месяц period.
     *
     * @return Collection<int, FounderExpense>
     */
    public function oneOffs(Carbon $period): Collection
    {
        return FounderExpense::where('period', FounderExpense::PERIOD_ONCE)->where('is_active', true)
            ->whereDate('charge_period', $period->copy()->startOfMonth())
            ->orderBy('sort')->orderBy('id')->get();
    }

    /** Взнос основателя от суммы расходов — в целых рублях. */
    public static function amountFor(Founder $founder, float $total): int
    {
        return (int) round($total * (float) $founder->share / 100);
    }

    /** «1 490 ₽», с копейками — «990,50 ₽». */
    public static function money(float $amount): string
    {
        $amount = round($amount, 2);

        return floor($amount) == $amount ? Money::format((int) $amount) : number_format($amount, 2, ',', ' ') . ' ₽';
    }

    /** «33 %», «33,33 %». */
    public static function percent(float $share): string
    {
        return rtrim(rtrim(number_format($share, 2, ',', ''), '0'), ',') . ' %';
    }

    /* ---------- Сбор ---------- */

    /** День сбора в месяце period; в коротком месяце — последний день. */
    public function dueDate(Carbon $period): Carbon
    {
        $month = $period->copy()->startOfMonth();

        return $month->day(min(self::settings()['day'], $month->daysInMonth));
    }

    /**
     * Текущий сбор: этот месяц, пока не прошёл день сбора или кто-то ещё не внёс; дальше — следующий месяц.
     */
    public function currentPeriod(): Carbon
    {
        $month = now()->startOfMonth();
        if (today()->lte($this->dueDate($month))) {
            return $month;
        }

        $unpaid = FounderContribution::whereDate('period', $month)->whereNull('paid_at')->exists();

        return $unpaid ? $month : $month->copy()->addMonth();
    }

    /**
     * Взносы за месяц по всем основателям: ежемесячный и по строке на каждый разовый расход этого месяца.
     * Недостающие создаются, невнесённые пересчитываются; невнесённые доли в разовых расходах,
     * которые удалили, выключили или перенесли на другой месяц, убираются. Порядок: основатель, ежемесячный, разовые.
     *
     * @return Collection<int, FounderContribution>
     */
    public function sync(Carbon $period): Collection
    {
        $period = $period->copy()->startOfMonth();
        $total = $this->monthlyTotal();
        $oneOffs = $this->oneOffs($period);
        $existing = FounderContribution::whereDate('period', $period)->get();

        // Невнесённые доли в разовых расходах, которых в этом месяце больше нет
        $stale = $existing->filter(fn (FounderContribution $c) => $c->isOneOff() && ! $c->paid_at && ! $oneOffs->contains('id', $c->founder_expense_id));
        if ($stale->isNotEmpty()) {
            FounderContribution::whereKey($stale->modelKeys())->delete();
            $existing = $existing->diff($stale);
        }

        $founders = $this->founders();
        $result = collect();
        foreach ($founders as $founder) {
            $lines = [[null, null, $total]];
            foreach ($oneOffs as $e) {
                $lines[] = [$e->id, $e->name, (float) $e->amount];
            }

            foreach ($lines as [$expenseId, $title, $sum]) {
                $c = $existing->first(fn (FounderContribution $x) => $x->founder_id === $founder->id
                    && ($expenseId ? $x->founder_expense_id === $expenseId : ! $x->isOneOff()))
                    ?? new FounderContribution(['founder_id' => $founder->id, 'founder_expense_id' => $expenseId, 'period' => $period, 'title' => $title]);

                if (! $c->paid_at) {
                    $c->amount = self::amountFor($founder, $sum);
                    $c->title = $title;
                }
                if ($c->isDirty()) {
                    $c->save();
                }
                $result->push($c->setRelation('founder', $founder));
            }
        }

        // Внесённые доли удалённых расходов остаются в истории
        $existing->filter(fn (FounderContribution $c) => $c->isOneOff() && $c->paid_at && ! $oneOffs->contains('id', $c->founder_expense_id))
            ->each(fn (FounderContribution $c) => $result->push($c->setRelation('founder', $founders->firstWhere('id', $c->founder_id))));

        return $result->filter(fn (FounderContribution $c) => $c->founder)->values();
    }

    /** Строки взносов в порядке: основатель, ежемесячный, разовые. */
    public static function ordered(Collection $contributions): Collection
    {
        return $contributions->sortBy(fn (FounderContribution $c) => sprintf('%010d-%d-%010d', $c->founder?->sort ?? 0, $c->founder_id, $c->isOneOff() ? $c->id : 0))->values();
    }

    /** Отметить взнос внесённым (by — кто отметил) или снять отметку. */
    public function setPaid(FounderContribution $c, bool $paid, ?User $by = null): void
    {
        $c->update(['paid_at' => $paid ? now() : null, 'paid_by_id' => $paid ? $by?->id : null]);
    }

    /** «Все внесли» за месяц. Возвращает, сколько взносов отмечено. */
    public function markAllPaid(Carbon $period, ?User $by = null): int
    {
        return FounderContribution::whereDate('period', $period->copy()->startOfMonth())->whereNull('paid_at')
            ->update(['paid_at' => now(), 'paid_by_id' => $by?->id, 'updated_at' => now()]);
    }

    /** Взнос просрочен: не внесён, а день сбора его месяца уже прошёл. */
    public function isOverdue(FounderContribution $c): bool
    {
        return ! $c->paid_at && today()->gt($this->dueDate($c->period));
    }

    /**
     * Долги: невнесённые взносы, у которых прошёл день сбора, — старые сверху.
     *
     * @return Collection<int, FounderContribution>
     */
    public function debts(): Collection
    {
        return FounderContribution::with('founder')->whereNull('paid_at')->where('amount', '>', 0)
            ->orderBy('period')->orderBy('id')->get()
            ->filter(fn (FounderContribution $c) => $c->founder && $this->isOverdue($c))
            ->values();
    }

    /**
     * История по месяцам до upTo включительно, новые сверху. onlyDebts — только месяцы с долгами (срок прошёл, кто-то не внёс).
     *
     * @return Collection<int, array{period: Carbon, contributions: Collection}>
     */
    public function journal(Carbon $upTo, bool $onlyDebts = false): Collection
    {
        return FounderContribution::with(['founder', 'paidBy'])
            ->whereDate('period', '<=', $upTo->copy()->startOfMonth())
            ->orderByDesc('period')->orderBy('id')
            ->get()
            ->groupBy(fn (FounderContribution $c) => $c->period->format('Y-m'))
            ->map(fn (Collection $items) => ['period' => $items->first()->period, 'contributions' => self::ordered($items)])
            ->when($onlyDebts, fn (Collection $months) => $months->filter(fn (array $m) => $m['contributions']->contains(fn ($c) => $this->isOverdue($c) && (float) $c->amount > 0)))
            ->values();
    }

    /**
     * Итоги по основателям: сколько внесено всего и сколько долга (просроченные невнесённые).
     *
     * @return Collection<int, array{founder: Founder, paid: float, debt: float, debtMonths: int}>
     */
    public function totals(): Collection
    {
        $all = FounderContribution::get()->groupBy('founder_id');

        return $this->founders()->map(function (Founder $f) use ($all) {
            $items = $all->get($f->id, collect());
            $overdue = $items->filter(fn (FounderContribution $c) => $this->isOverdue($c) && (float) $c->amount > 0);

            return [
                'founder' => $f,
                'paid' => (float) $items->whereNotNull('paid_at')->sum('amount'),
                'debt' => (float) $overdue->sum('amount'),
                'debtMonths' => $overdue->map(fn (FounderContribution $c) => $c->period->format('Y-m'))->unique()->count(),
            ];
        });
    }

    /* ---------- Напоминания ---------- */

    /**
     * Какие напоминания положены сегодня: [['period' => Carbon, 'kind' => soon|today|overdue], …].
     */
    public function remindersDue(?Carbon $today = null): array
    {
        $s = self::settings();
        if (! $s['reminders']) {
            return [];
        }

        $today = ($today ?? today())->copy()->startOfDay();
        $due = [];
        foreach ([-1, 0, 1] as $shift) {
            $period = $today->copy()->startOfMonth()->addMonths($shift);
            $date = $this->dueDate($period);
            $kind = match (true) {
                $s['remindDays'] > 0 && $today->equalTo($date->copy()->subDays($s['remindDays'])) => 'soon',
                $today->equalTo($date) => 'today',
                $today->equalTo($date->copy()->addDays(self::OVERDUE_DAYS)) => 'overdue',
                default => null,
            };
            if ($kind) {
                $due[] = ['period' => $period, 'kind' => $kind];
            }
        }

        return $due;
    }

    /** Разослать положенные сегодня напоминания тем, кто ещё не внёс: одно письмо на основателя. Возвращает число писем. */
    public function sendReminders(?Carbon $today = null): int
    {
        $sent = 0;
        foreach ($this->remindersDue($today) as ['period' => $period, 'kind' => $kind]) {
            foreach ($this->unpaidByFounder($period) as $row) {
                if ($row['remindedAt']?->isToday()) {
                    continue;
                }
                $sent += (int) $this->notifyFounder($row, $period, $kind);
            }
        }

        return $sent;
    }

    /**
     * Кто ещё не внёс за месяц: основатель, его невнесённые строки, сумма и когда напоминали в последний раз.
     *
     * @return Collection<int, array{founder: Founder, items: Collection, amount: float, remindedAt: ?Carbon}>
     */
    public function unpaidByFounder(Carbon $period): Collection
    {
        return $this->sync($period)
            ->filter(fn (FounderContribution $c) => ! $c->paid_at && (float) $c->amount > 0)
            ->groupBy('founder_id')
            ->map(fn (Collection $items) => [
                'founder' => $items->first()->founder,
                'items' => $items->values(),
                'amount' => (float) $items->sum('amount'),
                'remindedAt' => $items->max('reminded_at'),
            ])
            ->values();
    }

    /**
     * Напомнить вручную (кнопка в админке): тем из founderIds, кто не внёс за месяц и у кого есть почта.
     * Не зависит от выключателя напоминаний и от того, напоминали ли сегодня. Возвращает число писем.
     */
    public function remindNow(Carbon $period, array $founderIds): int
    {
        $due = $this->dueDate($period);
        $kind = match (true) {
            today()->lt($due) => 'soon',
            today()->equalTo($due) => 'today',
            default => 'overdue',
        };

        $sent = 0;
        foreach ($this->unpaidByFounder($period) as $row) {
            if (in_array($row['founder']->id, array_map('intval', $founderIds), true)) {
                $sent += (int) $this->notifyFounder($row, $period, $kind);
            }
        }

        return $sent;
    }

    /** Письмо одному основателю о его невнесённых строках; без почты — не отправляем. */
    private function notifyFounder(array $row, Carbon $period, string $kind): bool
    {
        if (! $row['founder']->email) {
            return false;
        }

        $expenses = FounderExpense::where('is_active', true)->where('period', '!=', FounderExpense::PERIOD_ONCE)->orderBy('sort')->orderBy('id')->get();
        $row['founder']->notify(new FounderContributionReminder($row['items'], $kind, $this->dueDate($period), $this->monthlyTotal(), $expenses));
        FounderContribution::whereKey($row['items']->pluck('id')->all())->update(['reminded_at' => now()]);

        return true;
    }
}
