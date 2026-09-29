<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Взнос основателя за месяц (period — первое число месяца). */
class FounderContribution extends Model
{
    protected $fillable = ['founder_id', 'period', 'amount', 'paid_at', 'paid_by_id', 'reminded_at'];

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

    /** Кто отметил взнос внесённым. */
    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by_id');
    }
}
