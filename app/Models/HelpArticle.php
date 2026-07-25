<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Статья базы знаний (Help Center)
 */
class HelpArticle extends Model
{
    protected $fillable = [
        'help_category_id',
        'title',
        'slug',
        'excerpt',
        'video_url',
        'video_file',
        'content',
        'sort_order',
        'is_published',
        'views_count',
    ];

    protected $casts = [
        'is_published' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $article) {
            if (blank($article->slug)) {
                $article->slug = static::generateSlug($article->title, $article->id);
            }
        });
    }

    public static function generateSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug(Str::transliterate($title)) ?: 'article';
        $slug = $base;
        $i = 2;

        while (static::where('slug', $slug)
            ->when($ignoreId, fn($q) => $q->where('id', '!=', $ignoreId))
            ->exists()) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(HelpCategory::class, 'help_category_id');
    }

    public function getUrlAttribute(): string
    {
        return route('help.article', [$this->category->audience_slug, $this->category->slug, $this->slug]);
    }

    /**
     * Есть ли у статьи видео (загруженный файл или внешняя ссылка)
     */
    public function getHasVideoAttribute(): bool
    {
        return filled($this->video_file) || filled($this->video_url);
    }

    /**
     * Публичный URL загруженного на CDN видеофайла
     */
    public function getVideoFileUrlAttribute(): ?string
    {
        return $this->video_file
            ? \Illuminate\Support\Facades\Storage::disk('s3')->url($this->video_file)
            : null;
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    /**
     * Поиск по опубликованным статьям в опубликованных категориях
     */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $term) . '%';

        return $query
            ->published()
            ->whereHas('category', fn($q) => $q->where('is_published', true))
            ->where(function (Builder $q) use ($like) {
                $q->where('title', 'LIKE', $like)
                    ->orWhere('excerpt', 'LIKE', $like)
                    ->orWhere('content', 'LIKE', $like);
            });
    }

    /**
     * Превращает ссылку на видео в URL для встраивания (iframe).
     * Поддерживает Kinescope, YouTube, VK Видео и Rutube.
     */
    public function getEmbedUrlAttribute(): ?string
    {
        $url = trim((string) $this->video_url);

        if ($url === '') {
            return null;
        }

        // Kinescope: https://kinescope.io/{id} или уже embed-ссылка
        if (preg_match('#kinescope\.io/(?:embed/)?([\w-]+)#', $url, $m)) {
            return 'https://kinescope.io/embed/' . $m[1];
        }

        // YouTube: watch?v=, youtu.be, shorts, embed
        if (preg_match('#(?:youtube\.com/(?:watch\?v=|shorts/|embed/)|youtu\.be/)([\w-]{6,})#', $url, $m)) {
            return 'https://www.youtube.com/embed/' . $m[1];
        }

        // Rutube: https://rutube.ru/video/{id}/
        if (preg_match('#rutube\.ru/(?:video|play/embed)/([\w-]+)#', $url, $m)) {
            return 'https://rutube.ru/play/embed/' . $m[1];
        }

        // VK Видео: https://vk.com/video-123456_654321 или vkvideo.ru/video-123456_654321
        if (preg_match('#(?:vk\.com|vkvideo\.ru)/video(-?\d+)_(\d+)#', $url, $m)) {
            return "https://vk.com/video_ext.php?oid={$m[1]}&id={$m[2]}&hd=2";
        }

        return null;
    }
}
