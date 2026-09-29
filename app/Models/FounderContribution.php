<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Взнос основателя за месяц (period — первое число месяца). title = null — ежемесячный взнос на расходы;
 * иначе — доля в разовом расходе founder_expense_id (title — его название).
 */
class FounderContribution extends Model
{
    protected $fillable = ['founder_id', 'founder_expense_id', 'period', 'title', 'amount', 'paid_at', 'paid_by_id', 'reminded_at'];

    protected $casts = [
        'period' => 'date',
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'reminded_at' => 'datetime',
    ];

    public function founder(): BelongsTo
    {
        return $this->belongsTo(Founder::class);
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(FounderExpense::class, 'founder_expense_id');
    }

    public function isOneOff(): bool
    {
        return $this->title !== null;
    }

    /** «Ежемесячный взнос» или «Разово: Лицензия». */
    public function label(): string
    {
        return $this->isOneOff() ? 'Разово: ' . $this->title : 'Ежемесячный взнос';
    }

    /** Кто отметил взнос внесённым. */
    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by_id');
    }
}
