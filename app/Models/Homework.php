<?php

namespace App\Models;

use App\Observers\HomeworkObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy([HomeworkObserver::class])]
class Homework extends Model
{
    use HasFactory;

    protected $table = 'homeworks';

    // Типы заданий
    const TYPE_HOMEWORK = 'homework';
    const TYPE_EXAM = 'exam';
    const TYPE_PRACTICE = 'practice';
    const TYPE_EGE = 'ege';

    protected $fillable = [
        'teacher_id',
        'room_id',
        'type',
        'title',
        'description',
        'attachments',
        'deadline',
        'max_score',
        'is_visible',
    ];

    protected $casts = [
        'attachments' => 'array',
        'deadline' => 'datetime',
        'is_visible' => 'boolean',
        'max_score' => 'integer',
    ];

    /**
     * Получить все типы заданий
     */
    public static function getTypes(): array
    {
        return [
            self::TYPE_HOMEWORK => 'Домашнее задание',
            self::TYPE_EXAM => 'Экзамен',
            self::TYPE_PRACTICE => 'Пробник ЕГЭ',
            self::TYPE_EGE => 'ЕГЭ',
        ];
    }

    /**
     * Получить название типа
     */
    public function getTypeLabelAttribute(): string
    {
        return self::getTypes()[$this->type] ?? $this->type;
    }

    /**
     * Получить иконку типа
     */
    public function getTypeIconAttribute(): string
    {
        return match ($this->type) {
            self::TYPE_HOMEWORK => 'heroicon-o-clipboard-document-list',
            self::TYPE_EXAM => 'heroicon-o-academic-cap',
            self::TYPE_PRACTICE => 'heroicon-o-document-text',
            self::TYPE_EGE => 'heroicon-o-trophy',
            default => 'heroicon-o-clipboard-document-list',
        };
    }

    /**
     * Получить цвет бейджа типа
     */
    public function getTypeColorAttribute(): string
    {
        return match ($this->type) {
            self::TYPE_HOMEWORK => 'gray',
            self::TYPE_EXAM => 'danger',
            self::TYPE_PRACTICE => 'warning',
            self::TYPE_EGE => 'success',
            default => 'gray',
        };
    }

    /**
     * Максимальный балл для типа (если не задан вручную)
     */
    public function getDefaultMaxScore(): int
    {
        return match ($this->type) {
            self::TYPE_HOMEWORK => 10,
            self::TYPE_EXAM => 100,
            self::TYPE_PRACTICE => 100,
            self::TYPE_EGE => 100,
            default => 10,
        };
    }

    /**
     * Получить максимальный балл (используя default если не задан)
     */
    public function getEffectiveMaxScoreAttribute(): int
    {
        return $this->max_score ?? $this->getDefaultMaxScore();
    }

    /**
     * Формат отображения оценки
     */
    public function formatGrade(?int $grade): string
    {
        if ($grade === null) {
            return '—';
        }

        return match ($this->type) {
            self::TYPE_HOMEWORK => (string) $grade,
            self::TYPE_EXAM, self::TYPE_PRACTICE, self::TYPE_EGE => "{$grade}/{$this->effective_max_score}",
            default => (string) $grade,
        };
    }

    /**
     * Название поля оценки
     */
    public function getGradeLabelAttribute(): string
    {
        return match ($this->type) {
            self::TYPE_HOMEWORK => 'Оценка',
            self::TYPE_EXAM, self::TYPE_PRACTICE, self::TYPE_EGE => 'Баллы',
            default => 'Оценка',
        };
    }

    /**
     * Учитель, создавший задание
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    /**
     * Урок (опционально)
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * Ученики, которым назначено задание
     */
    public function students(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'homework_student', 'homework_id', 'student_id');
    }

    /**
     * Сданные работы
     */
    public function submissions(): HasMany
    {
        return $this->hasMany(HomeworkSubmission::class);
    }

    /**
     * Проверить, сдал ли ученик работу
     */
    public function hasSubmission(User $student): bool
    {
        return $this->submissions()->where('student_id', $student->id)->exists();
    }

    /**
     * Получить работу ученика
     */
    public function getSubmission(User $student): ?HomeworkSubmission
    {
        return $this->submissions()->where('student_id', $student->id)->first();
    }

    /**
     * Количество сданных работ
     */
    public function getSubmittedCountAttribute(): int
    {
        return $this->submissions()->whereNotNull('submitted_at')->count();
    }

    /**
     * Общее количество назначенных учеников
     */
    public function getTotalStudentsAttribute(): int
    {
        return $this->students()->count();
    }

    /**
     * Просрочено ли задание
     */
    public function getIsOverdueAttribute(): bool
    {
        return $this->deadline && $this->deadline->isPast();
    }

    protected static function booted()
    {
        static::deleting(function ($homework) {
            foreach ($homework->attachments ?? [] as $attachment) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($attachment);
            }

            $homework->submissions()->get()->each->delete();
        });
    }
}
