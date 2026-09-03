<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    use HasFactory;

    const STATUS_ACTIVE = 'active';
    const STATUS_EXPIRED = 'expired';
    const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'user_id',
        'tariff_id',
        'status',
        'price',
        'starts_at',
        'ends_at',
        'cancelled_at',
        'expiring_notified_at',
        'comment',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'expiring_notified_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function tariff()
    {
        // withTrashed: подписка должна работать, даже если тариф удалили из продажи
        return $this->belongsTo(Tariff::class)->withTrashed();
    }

    public function payments()
    {
        return $this->hasMany(SubscriptionPayment::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE)
            // запланированные на будущее (отложенный даунгрейд) ещё не действуют
            ->where('starts_at', '<=', now())
            ->where(function (Builder $q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>', now());
            });
    }

    /**
     * Запланированные подписки: вступят в силу в будущем
     * (например, бесплатный тариф после окончания оплаченного периода).
     */
    public function scopeScheduled(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE)
            ->where('starts_at', '>', now());
    }

    /**
     * Платный тариф, выданный администратором без оплаты (цена-снимок 0).
     */
    public function isComplimentary(): bool
    {
        return !$this->tariff->isFree() && (float) $this->price <= 0;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE
            && ($this->ends_at === null || $this->ends_at->isFuture());
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_ACTIVE => $this->isActive() ? 'Активна' : 'Истекла',
            self::STATUS_EXPIRED => 'Истекла',
            self::STATUS_CANCELLED => 'Отменена',
            default => $this->status,
        };
    }
}
