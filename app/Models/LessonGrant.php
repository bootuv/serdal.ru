<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Занятия, начисленные учителю администратором (SubscriptionService::grantLessonsByAdmin).
 */
class LessonGrant extends Model
{
    protected $fillable = ['user_id', 'admin_id', 'lessons', 'note'];

    protected $casts = ['lessons' => 'integer'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
