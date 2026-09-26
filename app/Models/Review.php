<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Review extends Model
{
    use HasFactory;

    /** Самый длинный отзыв, символов. */
    public const MAX_TEXT = 2000;

    /** Причины жалобы учителя на отзыв (макет RvReport). */
    public const REPORT_REASONS = [
        'rude' => 'Оскорбления или грубость',
        'ads' => 'Реклама или посторонние ссылки',
        'stranger' => 'Это не мой ученик',
        'other' => 'Другое',
    ];

    protected $fillable = [
        'text', 'user_id', 'teacher_id', 'rating', 'is_reported', 'is_rejected', 'teacher_read_at',
        'report_reason', 'report_note', 'reported_at',
    ];

    protected $casts = [
        'teacher_read_at' => 'datetime',
        'reported_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    /** Причина жалобы для людей: «Это не мой ученик» (null — жалобы нет или причина не указана). */
    public function getReportReasonLabelAttribute(): ?string
    {
        return self::REPORT_REASONS[$this->report_reason] ?? null;
    }
}
