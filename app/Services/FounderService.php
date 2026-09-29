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
        return round(FounderExpense::where('is_active', true)->get()->sum(fn (FounderExpense $e) => $e->monthly()), 2);
    }

    public function sharesTotal(): float
    {
        return round((float) Founder::sum('share'), 2);
    }

    /** Взнос основателя от суммы расходов в месяц — в целых рублях. */
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
     * Взносы за месяц по всем основателям: недостающие создаются, невнесённые пересчитываются.
     *
     * @return Collection<int, FounderContribution>
     */
    public function sync(Carbon $period): Collection
    {
        $period = $period->copy()->startOfMonth();
        $total = $this->monthlyTotal();

        return $this->founders()->map(function (Founder $founder) use ($period, $total) {
            $c = FounderContribution::where('founder_id', $founder->id)->whereDate('period', $period)->first()
                ?? new FounderContribution(['founder_id' => $founder->id, 'period' => $period]);

            if (! $c->paid_at) {
                $c->amount = self::amountFor($founder, $total);
            }
            if ($c->isDirty()) {
                $c->save();
            }

            return $c->setRelation('founder', $founder);
        });
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
            ->map(fn (Collection $items) => ['period' => $items->first()->period, 'contributions' => $items->values()])
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
                'debtMonths' => $overdue->count(),
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

    /** Разослать положенные сегодня напоминания тем, кто ещё не внёс. Возвращает число писем. */
    public function sendReminders(?Carbon $today = null): int
    {
        $sent = 0;
        $expenses = FounderExpense::where('is_active', true)->orderBy('sort')->orderBy('id')->get();
        $total = $this->monthlyTotal();

        foreach ($this->remindersDue($today) as ['period' => $period, 'kind' => $kind]) {
            foreach ($this->sync($period) as $c) {
                if ($c->paid_at || ! $c->founder->email || (float) $c->amount <= 0 || $c->reminded_at?->isToday()) {
                    continue;
                }

                $c->founder->notify(new FounderContributionReminder($c, $kind, $this->dueDate($period), $total, $expenses));
                $c->update(['reminded_at' => now()]);
                $sent++;
            }
        }

        return $sent;
    }
}
