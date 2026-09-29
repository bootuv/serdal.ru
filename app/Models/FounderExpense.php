<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Расход на инфраструктуру (сервер, домен, почта, сервисы). period — month | year:
 * годовой расход во взносах делится на 12. is_active = false — расход в списке, но во взносах не учитывается.
 */
class FounderExpense extends Model
{
    public const PERIOD_MONTH = 'month';

    public const PERIOD_YEAR = 'year';

    protected $fillable = ['name', 'note', 'amount', 'period', 'is_active', 'sort'];

    protected $casts = [
        'amount' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /** Сколько расход стоит в месяц. */
    public function monthly(): float
    {
        return $this->period === self::PERIOD_YEAR ? (float) $this->amount / 12 : (float) $this->amount;
    }
}
