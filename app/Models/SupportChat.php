<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportChat extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
    ];

    /**
     * Владелец чата (ученик или учитель)
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Сообщения в чате
     */
    public function messages(): HasMany
    {
        return $this->hasMany(SupportMessage::class);
    }

    /**
     * Получить или создать чат поддержки для пользователя
     */
    public static function getOrCreateForUser(User $user): self
    {
        return self::firstOrCreate(['user_id' => $user->id]);
    }
}
