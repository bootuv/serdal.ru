<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/** Новость от администрации для учителей, учеников или всех. Логика — App\Services\AnnouncementService. */
class Announcement extends Model
{
    public const AUDIENCE_TEACHERS = 'teachers';
    public const AUDIENCE_STUDENTS = 'students';
    public const AUDIENCE_ALL = 'all';

    public const AUDIENCES = [
        self::AUDIENCE_TEACHERS => 'Учителям',
        self::AUDIENCE_STUDENTS => 'Ученикам',
        self::AUDIENCE_ALL => 'Всем',
    ];

    protected $fillable = [
        'title', 'body', 'audience', 'is_important', 'is_pinned', 'send_mail', 'published_at', 'notified_at', 'created_by',
    ];

    protected $casts = [
        'is_important' => 'boolean',
        'is_pinned' => 'boolean',
        'send_mail' => 'boolean',
        'published_at' => 'datetime',
        'notified_at' => 'datetime',
    ];

    public function reads(): HasMany
    {
        return $this->hasMany(AnnouncementRead::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Опубликована и время публикации наступило. */
    public function scopePublished(Builder $query): Builder
    {
        return $query->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    /** Роли, которым адресована новость. */
    public function roles(): array
    {
        return match ($this->audience) {
            self::AUDIENCE_STUDENTS => [User::ROLE_STUDENT],
            self::AUDIENCE_ALL => [User::ROLE_TUTOR, User::ROLE_STUDENT],
            default => [User::ROLE_TUTOR],
        };
    }

    /** Аудитории, в которые входит роль. */
    public static function audiencesFor(string $role): array
    {
        return $role === User::ROLE_STUDENT
            ? [self::AUDIENCE_STUDENTS, self::AUDIENCE_ALL]
            : [self::AUDIENCE_TEACHERS, self::AUDIENCE_ALL];
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null && $this->published_at->lte(now());
    }

    public function isScheduled(): bool
    {
        return $this->published_at !== null && $this->published_at->isFuture();
    }

    /** Начало текста без разметки — для списка, карточки на главной и уведомления. */
    public function excerpt(int $limit = 160): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(str_replace('</p>', '</p> ', (string) $this->body)))));

        return Str::limit($text, $limit);
    }
}
