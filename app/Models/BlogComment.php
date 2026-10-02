<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Комментарий к статье блога. Логика — App\Services\BlogCommentService. */
class BlogComment extends Model
{
    public const DELETED_BY_AUTHOR = 'author';
    public const DELETED_BY_MODERATOR = 'moderator';

    protected $fillable = ['blog_post_id', 'user_id', 'parent_id', 'reply_to_user_id', 'body', 'edited_at', 'deleted_at', 'deleted_by'];

    protected $casts = ['edited_at' => 'datetime', 'deleted_at' => 'datetime'];

    public function post(): BelongsTo
    {
        return $this->belongsTo(BlogPost::class, 'blog_post_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reply_to_user_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('created_at')->orderBy('id');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(BlogCommentReport::class);
    }

    public function isDeleted(): bool
    {
        return $this->deleted_at !== null;
    }
}
