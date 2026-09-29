<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Расход на инфраструктуру (сервер, домен, почта, сервисы). period — month | year | once:
 * годовой расход во взносах делится на 12; разовый (once) собирается один раз вместе со сбором за месяц charge_period.
 * is_active = false — расход в списке, но во взносах не учитывается.
 */
class FounderExpense extends Model
{
    public const PERIOD_MONTH = 'month';

    public const PERIOD_YEAR = 'year';

    public const PERIOD_ONCE = 'once';

    protected $fillable = ['name', 'note', 'amount', 'period', 'charge_period', 'is_active', 'sort'];

    protected $casts = [
        'amount' => 'decimal:2',
        'charge_period' => 'date',
        'is_active' => 'boolean',
    ];

    public function isOneOff(): bool
    {
        return $this->period === self::PERIOD_ONCE;
    }

    /** Сколько расход стоит в месяц (разовый в ежемесячные расходы не входит). */
    public function monthly(): float
    {
        return match ($this->period) {
            self::PERIOD_YEAR => (float) $this->amount / 12,
            self::PERIOD_ONCE => 0.0,
            default => (float) $this->amount,
        };
    }
}
