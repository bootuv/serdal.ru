<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Запрет комментировать: author_id — в статьях этого автора, null — везде (поставил админ). */
class BlogCommentBan extends Model
{
    protected $fillable = ['user_id', 'author_id', 'created_by'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
