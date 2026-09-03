<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tariff extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'price',
        'yearly_price',
        'period_days',
        'lessons_per_month',
        'max_participants',
        'max_duration_minutes',
        'recording_retention_days',
        'short_description',
        'description',
        'features',
        'extra_features',
        'is_active',
        'is_popular',
        'sort',
    ];

    protected function casts(): array
    {
        return [
            'features' => 'array',
            'extra_features' => 'array',
            'is_active' => 'boolean',
            'is_popular' => 'boolean',
        ];
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort');
    }

    public function isFree(): bool
    {
        return (int) $this->price === 0;
    }

    /**
     * Доступна ли оплата за год.
     */
    public function hasYearly(): bool
    {
        return !$this->isFree() && $this->yearly_price !== null && $this->yearly_price > 0;
    }

    /**
     * Скидка годовой оплаты относительно 12 месячных платежей, %.
     */
    public function yearlyDiscountPercent(): int
    {
        if (!$this->hasYearly() || $this->price <= 0) {
            return 0;
        }

        return max(0, (int) round(100 - $this->yearly_price / ($this->price * 12) * 100));
    }

    // Человекочитаемые характеристики — используются на публичной странице и в кабинете
    public function getLessonsLabelAttribute(): string
    {
        return $this->lessons_per_month
            ? $this->lessons_per_month . ' занятий в месяц'
            : 'Занятия без лимита';
    }

    public function getParticipantsLabelAttribute(): string
    {
        return 'До ' . $this->max_participants . ' участников в занятии';
    }

    public function getDurationLabelAttribute(): string
    {
        return $this->max_duration_minutes
            ? 'Длительность занятия до ' . $this->max_duration_minutes . ' мин'
            : 'Без ограничения длительности';
    }

    public function getRecordingLabelAttribute(): string
    {
        return $this->recording_retention_days
            ? 'Хранение записей занятий ' . $this->recording_retention_days . ' дней'
            : 'Записи занятий недоступны';
    }
}
