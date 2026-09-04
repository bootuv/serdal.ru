<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubscriptionPayment extends Model
{
    use HasFactory;

    const STATUS_PENDING = 'pending';
    const STATUS_PAID = 'paid';
    const STATUS_FAILED = 'failed';
    const STATUS_REFUNDED = 'refunded';

    protected $fillable = [
        'user_id',
        'tariff_id',
        'subscription_id',
        'amount',
        'period_days',
        'extra_lessons',
        'status',
        'gateway',
        'gateway_order_id',
        'payment_url',
        'paid_at',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'paid_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function tariff()
    {
        // withTrashed: история платежей должна показывать и удалённые тарифы
        return $this->belongsTo(Tariff::class)->withTrashed();
    }

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * Платёж за докупку занятий сверх лимита тарифа (а не за подписку).
     */
    public function isExtraLessons(): bool
    {
        return (int) $this->extra_lessons > 0;
    }

    /**
     * Человекочитаемое название того, за что платёж: тариф, привязка карты
     * или дополнительные занятия. Используется в истории, квитанции и уведомлениях.
     */
    public function getTitleAttribute(): string
    {
        if (!empty($this->meta['card_binding'])) {
            return 'Привязка карты';
        }

        if ($this->isExtraLessons()) {
            return 'Дополнительные занятия (' . $this->extra_lessons . ' ' . \App\Services\SubscriptionService::lessonsWord($this->extra_lessons) . ')';
        }

        return 'Тариф «' . $this->tariff->name . '»';
    }

    /**
     * Можно ли вернуться к оплате: платёж не завершён, а ссылка на платёжную
     * страницу ЮKassa ещё действует (неоплаченные платежи шлюз отменяет ~через час).
     */
    public function isResumable(): bool
    {
        return $this->status === self::STATUS_PENDING
            && $this->payment_url
            && $this->created_at->gt(now()->subHour());
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'Ожидает оплаты',
            self::STATUS_PAID => 'Оплачен',
            self::STATUS_FAILED => 'Не прошёл',
            self::STATUS_REFUNDED => 'Возврат',
            default => $this->status,
        };
    }
}
