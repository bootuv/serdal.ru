<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** Тег статьи блога: страница /blog/tag/{slug}, популярные — в боковой колонке ленты. */
class BlogTag extends Model
{
    protected $fillable = ['name', 'slug'];

    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(BlogPost::class, 'blog_post_tag');
    }

    public function getUrlAttribute(): string
    {
        return route('blog.tag', $this->slug);
    }
}
