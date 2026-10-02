<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Адрес для рассылки: один на всю систему, отписка действует на все списки. */
class MailingContact extends Model
{
    protected $fillable = ['email', 'name', 'city', 'user_id', 'unsubscribed_at'];

    protected $casts = ['unsubscribed_at' => 'datetime'];

    public function lists(): BelongsToMany
    {
        return $this->belongsToMany(MailingList::class, 'mailing_contact_list');
    }

    /** Пользователь платформы, если адрес добавлен из пользователей. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(MailingDelivery::class);
    }

    /** Можно писать: не отписался. */
    public function scopeSubscribed(Builder $query): Builder
    {
        return $query->whereNull('unsubscribed_at');
    }
}
