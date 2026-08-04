<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Категория базы знаний (Help Center)
 */
class HelpCategory extends Model
{
    public const AUDIENCE_STUDENT = 'student';
    public const AUDIENCE_TUTOR = 'tutor';

    public const AUDIENCES = [
        self::AUDIENCE_STUDENT => 'Для учеников',
        self::AUDIENCE_TUTOR => 'Для репетиторов',
    ];

    /** URL-слаги разделов: /help/students, /help/tutors */
    public const AUDIENCE_SLUGS = [
        self::AUDIENCE_STUDENT => 'students',
        self::AUDIENCE_TUTOR => 'tutors',
    ];

    public static function audienceFromSlug(string $slug): ?string
    {
        $audience = array_search($slug, self::AUDIENCE_SLUGS, true);

        return $audience === false ? null : $audience;
    }

    protected $fillable = [
        'audience',
        'name',
        'slug',
        'description',
        'icon',
        'sort_order',
        'is_published',
    ];

    protected $casts = [
        'is_published' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $category) {
            if (blank($category->slug)) {
                $category->slug = static::generateSlug($category->name, $category->id);
            }
        });
    }

    public static function generateSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug(Str::transliterate($name)) ?: 'category';
        $slug = $base;
        $i = 2;

        while (static::where('slug', $slug)
            ->when($ignoreId, fn($q) => $q->where('id', '!=', $ignoreId))
            ->exists()) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }

    public function articles(): HasMany
    {
        return $this->hasMany(HelpArticle::class)->orderBy('sort_order');
    }

    public function publishedArticles(): HasMany
    {
        return $this->articles()->where('is_published', true);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function getAudienceLabelAttribute(): string
    {
        return self::AUDIENCES[$this->audience] ?? $this->audience;
    }

    public function getAudienceSlugAttribute(): string
    {
        return self::AUDIENCE_SLUGS[$this->audience] ?? $this->audience;
    }

    public function getAudienceUrlAttribute(): string
    {
        return route('help.section', $this->audience_slug);
    }

    public function getUrlAttribute(): string
    {
        return route('help.category', [$this->audience_slug, $this->slug]);
    }
}
