<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
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
        'report_reason', 'report_note', 'reported_at', 'hidden_at', 'approved_at', 'show_on_site',
    ];

    protected $casts = [
        'teacher_read_at' => 'datetime',
        'reported_at' => 'datetime',
        'hidden_at' => 'datetime',
        'approved_at' => 'datetime',
        'show_on_site' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    /** Отзыв учителя о платформе (без teacher_id). */
    public function scopePlatform(Builder $query): Builder
    {
        return $query->whereNull('teacher_id');
    }

    /** Отзывы об учителях (от учеников). */
    public function scopeAboutTeachers(Builder $query): Builder
    {
        return $query->whereNotNull('teacher_id');
    }

    /**
     * Отзывы для публичной страницы /reviews: видимые отзывы учеников об учителях
     * и проверенные отзывы учителей о платформе, которые автор разрешил показать.
     */
    public function scopePublic(Builder $query): Builder
    {
        return $query->where('is_rejected', false)->where(fn (Builder $q) => $q
            ->where(fn (Builder $t) => $t->whereNotNull('teacher_id')->whereHas('user', fn ($u) => $u->where('role', User::ROLE_STUDENT)))
            ->orWhere(fn (Builder $p) => $p->whereNull('teacher_id')->whereNotNull('approved_at')->where('show_on_site', true)));
    }

    public function isPlatform(): bool
    {
        return $this->teacher_id === null;
    }

    /** Причина жалобы для людей: «Это не мой ученик» (null — жалобы нет или причина не указана). */
    public function getReportReasonLabelAttribute(): ?string
    {
        return self::REPORT_REASONS[$this->report_reason] ?? null;
    }
}
