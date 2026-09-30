<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notifiable;

/**
 * Основатель платформы (админка «Основатели»): доля в процентах определяет, сколько он вносит на расходы.
 * Напоминания о взносе приходят на указанную почту. Если привязан профиль на сайте (user_id) — основатель видит
 * свой сбор на личной странице после входа, даже без доступа в админку.
 */
class Founder extends Model
{
    use Notifiable;

    protected $fillable = ['name', 'email', 'user_id', 'share', 'sort'];

    protected $casts = [
        'share' => 'decimal:2',
    ];

    /** Профиль на сайте (администратор или учитель): под ним основатель открывает свою страницу сбора. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Личная страница сбора (FounderPage) — одна для всех, открывается после входа под привязанным профилем. */
    public function pageUrl(): ?string
    {
        return $this->user_id ? route('founders.page') : null;
    }

    public function contributions(): HasMany
    {
        return $this->hasMany(FounderContribution::class);
    }

    public function routeNotificationForMail(): ?string
    {
        return $this->email ?: null;
    }
}
