<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Жалоба на комментарий: разбирает админ (админка «Блог» → «Жалобы»). */
class BlogCommentReport extends Model
{
    protected $fillable = ['blog_comment_id', 'user_id', 'reason', 'resolved_at'];

    protected $casts = ['resolved_at' => 'datetime'];

    public function comment(): BelongsTo
    {
        return $this->belongsTo(BlogComment::class, 'blog_comment_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
