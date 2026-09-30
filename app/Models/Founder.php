<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notifiable;

/**
 * Основатель платформы (админка «Основатели»): доля в процентах определяет, сколько он вносит на расходы.
 * Основатель — всегда существующий пользователь (user_id, администратор или учитель): имя и почта берутся из профиля,
 * напоминания уходят на почту профиля, свою страницу сбора он видит после входа. Колонки name и email — копия
 * на случай, если профиль удалят, и для основателей, добавленных до привязки к профилям.
 */
class Founder extends Model
{
    use Notifiable;

    protected $fillable = ['name', 'email', 'user_id', 'share', 'sort'];

    protected $casts = [
        'share' => 'decimal:2',
    ];

    /** Имя из профиля; без профиля — сохранённая копия. */
    protected function name(): Attribute
    {
        return Attribute::get(fn (?string $value) => $this->user_id && $this->user ? (string) $this->user->name : (string) $value);
    }

    /** Почта профиля; без профиля — сохранённая копия. */
    protected function email(): Attribute
    {
        return Attribute::get(fn (?string $value) => $this->user_id && $this->user ? ($this->user->email ?: null) : $value);
    }

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
