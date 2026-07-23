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
        return $this->status === self::STATUS_UNPAID && $this->due_date->isPast() && !$this->due_date->isToday();
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
     * Отметить оплату/отмену и пересчитать блокировку ученика.
     */
    public function markAs(string $status, ?int $markedBy = null): void
    {
        $this->update([
            'status' => $status,
            'paid_at' => $status === self::STATUS_PAID ? now() : null,
            'marked_by' => $markedBy,
        ]);

        \App\Services\PaymentRecordService::recalculateBlock($this->student);
    }

    /**
     * Продлить срок оплаты: просроченным — от сегодня, остальным — от текущего срока.
     * Сбрасывает отметку о напоминании и снимает блокировку, если долгов не осталось.
     */
    public function extendDue(int $days): void
    {
        $base = $this->due_date->isPast() ? today() : $this->due_date;

        $this->update([
            'due_date' => $base->copy()->addDays($days),
            'reminded_at' => null,
        ]);

        \App\Services\PaymentRecordService::recalculateBlock($this->student);
    }
}
