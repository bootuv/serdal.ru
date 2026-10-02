<?php

namespace App\Models;

use App\Support\Seo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/** Статья блога (serdal.ru/blog). Логика — App\Services\BlogService. */
class BlogPost extends Model
{
    protected $fillable = ['title', 'slug', 'excerpt', 'cover_url', 'body', 'published_at', 'created_by'];

    protected $casts = ['published_at' => 'datetime'];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Видна на сайте: опубликована и время публикации наступило. */
    public function scopePublished(Builder $query): Builder
    {
        return $query->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null && $this->published_at->lte(now());
    }

    public function isScheduled(): bool
    {
        return $this->published_at !== null && $this->published_at->isFuture();
    }

    public function getUrlAttribute(): string
    {
        return route('blog.show', $this->slug);
    }

    /** Описание для списка и поисковиков: своё или начало текста. */
    public function description(int $limit = 160): string
    {
        return trim((string) $this->excerpt) ?: Seo::text($this->body, $limit);
    }

    /** «5 мин» чтения: 180 слов в минуту. */
    public function readingMinutes(): int
    {
        $words = str_word_count(strip_tags((string) $this->body), 0, 'АБВГДЕЁЖЗИЙКЛМНОПРСТУФХЦЧШЩЪЫЬЭЮЯабвгдеёжзийклмнопрстуфхцчшщъыьэюя');

        return max(1, (int) ceil($words / 180));
    }

    /** Русские буквы → латиница, как привыкли поисковики: «занятия» → zanyatiya, «щука» → shchuka. */
    private const TRANSLIT = [
        'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'e', 'ж' => 'zh', 'з' => 'z',
        'и' => 'i', 'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r',
        'с' => 's', 'т' => 't', 'у' => 'u', 'ф' => 'f', 'х' => 'kh', 'ц' => 'ts', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'shch',
        'ъ' => '', 'ы' => 'y', 'ь' => '', 'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
    ];

    /** Адрес из текста: «Как подготовиться к ОГЭ» → kak-podgotovitsya-k-oge. */
    public static function toSlug(string $text): string
    {
        return Str::limit(trim(Str::slug(strtr(mb_strtolower($text), self::TRANSLIT)), '-'), 80, '');
    }

    /** Свободный адрес для статьи (повтор — с номером: …-2). */
    public static function slugFrom(string $text, ?int $ignoreId = null): string
    {
        $base = rtrim(static::toSlug($text), '-') ?: 'statya';
        $slug = $base;
        $i = 2;
        while (static::where('slug', $slug)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists()) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }
}
