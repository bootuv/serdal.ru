<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * «Ученик сообщил об оплате»: выбранные неоплаченные начисления у одного учителя, чеки и комментарий.
 * Пока заявка ждёт учителя, начисления остаются неоплаченными (блокировка не снимается);
 * подтверждение отмечает их оплаченными, отклонение — возвращает всё как было.
 * Логика — App\Services\PaymentClaimService.
 */
class PaymentClaim extends Model
{
    const STATUS_PENDING = 'pending';
    const STATUS_CONFIRMED = 'confirmed';
    const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'teacher_id',
        'student_id',
        'status',
        'comment',
        'files',
        'amount',
        'reject_reason',
        'decided_at',
    ];

    protected $casts = [
        'files' => 'array',
        'amount' => 'integer',
        'decided_at' => 'datetime',
    ];

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function records(): BelongsToMany
    {
        return $this->belongsToMany(PaymentRecord::class, 'payment_claim_record');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
