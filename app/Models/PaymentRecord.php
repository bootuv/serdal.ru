<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentRecord extends Model
{
    use HasFactory;

    const TYPE_PER_LESSON = 'per_lesson';
    const TYPE_MONTHLY = 'monthly';

    const STATUS_UNPAID = 'unpaid';
    const STATUS_PAID = 'paid';
    const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'teacher_id',
        'student_id',
        'type',
        'meeting_session_id',
        'period',
        'status',
        'due_date',
        'paid_at',
        'marked_by',
        'reminded_at',
    ];

    protected $casts = [
        'due_date' => 'date',
        'paid_at' => 'datetime',
        'reminded_at' => 'datetime',
    ];

    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function meetingSession()
    {
        return $this->belongsTo(MeetingSession::class);
    }

    /** Заявки «Ученик сообщил об оплате», в которые входит начисление. */
    public function claims()
    {
        return $this->belongsToMany(PaymentClaim::class, 'payment_claim_record');
    }

    public function scopeUnpaid(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_UNPAID);
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->unpaid()->whereDate('due_date', '<', today());
    }

    public function isOverdue(): bool
    {
        return $this->status === self::STATUS_UNPAID && $this->due_date->lt(today());
    }

    /**
     * Человекочитаемое описание, за что начисление: «Занятие 12.07.2026 (Математика)» или «Июль 2026».
     */
    public function getLabelAttribute(): string
    {
        if ($this->type === self::TYPE_MONTHLY && $this->period) {
            $date = \Carbon\Carbon::createFromFormat('Y-m', $this->period)->startOfMonth();
            return \Illuminate\Support\Str::ucfirst($date->translatedFormat('F Y'));
        }

        $session = $this->meetingSession;
        $date = $session?->ended_at?->format('d.m.Y');
        $roomName = $session?->room?->name;

        $label = 'Занятие' . ($date ? " {$date}" : '');
        if ($roomName) {
            $label .= " ({$roomName})";
        }

        return $label;
    }

    /**
     * За что начисление, по-человечески (новые кабинеты): «Математика · чт, 12 сентября» или «Оплата за сентябрь».
     */
    public function getHumanLabelAttribute(): string
    {
        if ($this->type === self::TYPE_MONTHLY && $this->period) {
            return 'Оплата за ' . \App\Support\HumanDate::month($this->billingMonth());
        }

        $session = $this->meetingSession;
        $parts = array_filter([
            $session?->room?->name ?: 'Занятие',
            $session?->ended_at ? \App\Support\HumanDate::day($session->ended_at) : null,
        ]);

        return implode(' · ', $parts);
    }

    /**
     * Сумма поурочного начисления, ₽: цена ученика из снимка цен на момент завершения занятия.
     * У помесячных начислений суммы нет (null).
     */
    public function amount(): ?int
    {
        $participants = $this->meetingSession?->pricing_snapshot['participants'] ?? [];
        $price = collect($participants)->firstWhere('user_id', $this->student_id)['price'] ?? null;

        return $price !== null ? (int) $price : null;
    }

    /**
     * Месяц, к которому относится начисление: период помесячной оплаты или дата занятия.
     */
    public function billingMonth(): \Illuminate\Support\Carbon
    {
        if ($this->type === self::TYPE_MONTHLY && $this->period) {
            return \Illuminate\Support\Carbon::createFromFormat('Y-m', $this->period)->startOfMonth();
        }

        return ($this->meetingSession?->ended_at ?? $this->created_at ?? now())->copy()->startOfMonth();
    }

    /**
     * Оплачено позже срока.
     */
    public function isPaidLate(): bool
    {
        return $this->status === self::STATUS_PAID && $this->paid_at && $this->due_date
            && $this->paid_at->copy()->startOfDay()->gt($this->due_date);
    }

    /**
     * Отметить оплату/отмену. Блокировка занятий вычисляется из статуса записи,
     * поэтому снимается сама.
     */
    public function markAs(string $status, ?int $markedBy = null): void
    {
        $this->update([
            'status' => $status,
            'paid_at' => $status === self::STATUS_PAID ? now() : null,
            'marked_by' => $markedBy,
        ]);
    }

    /**
     * Продлить срок оплаты: просроченным — от сегодня, остальным — от текущего срока.
     * Сбрасывает отметку о напоминании; блокировка занятий снимается сама.
     */
    public function extendDue(int $days): void
    {
        $this->update([
            'due_date' => $this->extendedDue($days),
            'reminded_at' => null,
        ]);
    }

    /**
     * Каким станет срок после продления на $days дней (для предпросмотра в окне продления).
     */
    public function extendedDue(int $days): \Illuminate\Support\Carbon
    {
        $base = $this->due_date->lt(today()) ? today() : $this->due_date;

        return $base->copy()->addDays($days);
    }
}
