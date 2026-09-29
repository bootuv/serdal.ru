<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notifiable;

/**
 * Основатель платформы (админка «Основатели»): доля в процентах определяет, сколько он вносит на расходы.
 * Не пользователь кабинета — напоминания о взносе приходят на указанную почту.
 */
class Founder extends Model
{
    use Notifiable;

    protected $fillable = ['name', 'email', 'share', 'sort'];

    protected $casts = [
        'share' => 'decimal:2',
    ];

    public function contributions(): HasMany
    {
        return $this->hasMany(FounderContribution::class);
    }

    public function routeNotificationForMail(): ?string
    {
        return $this->email ?: null;
    }
}
