<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Начисление по партнёрской программе за первую оплату приглашённого учителя.
 */
class ReferralReward extends Model
{
    /** Бонусы начислены обоим. */
    const STATUS_CREDITED = 'credited';

    /** Пригласивший превысил лимит начислений в месяц — бонус получил только приглашённый. */
    const STATUS_LIMIT = 'limit';

    /** Подозрение на приглашение самого себя — бонусы не начислены. */
    const STATUS_REJECTED = 'rejected';

    /** Платёж возвращён — бонусы списаны. */
    const STATUS_REVOKED = 'revoked';

    protected $fillable = [
        'referrer_id',
        'referred_id',
        'payment_id',
        'referrer_lessons',
        'referred_lessons',
        'status',
        'note',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'referrer_lessons' => 'integer',
            'referred_lessons' => 'integer',
            'revoked_at' => 'datetime',
        ];
    }

    public static function statusLabels(): array
    {
        return [
            self::STATUS_CREDITED => 'Начислено',
            self::STATUS_LIMIT => 'Лимит в месяц',
            self::STATUS_REJECTED => 'Отклонено',
            self::STATUS_REVOKED => 'Отозвано (возврат)',
        ];
    }

    public function referrer()
    {
        return $this->belongsTo(User::class, 'referrer_id');
    }

    public function referred()
    {
        return $this->belongsTo(User::class, 'referred_id');
    }

    public function payment()
    {
        return $this->belongsTo(SubscriptionPayment::class, 'payment_id');
    }
}
