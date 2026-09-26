<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HomeworkActivity extends Model
{
    use HasFactory;

    // Activity types
    public const TYPE_SUBMITTED = 'submitted';
    public const TYPE_RESUBMITTED = 'resubmitted';
    public const TYPE_ANNOTATED = 'annotated';
    public const TYPE_GRADED = 'graded';
    public const TYPE_REVISION_REQUESTED = 'revision_requested';

    protected $fillable = [
        'submission_id',
        'user_id',
        'type',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(HomeworkSubmission::class, 'submission_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get activity label
     */
    public function getLabelAttribute(): string
    {
        return match ($this->type) {
            self::TYPE_SUBMITTED => 'Работа сдана',
            self::TYPE_RESUBMITTED => 'Работа пересдана',
            self::TYPE_ANNOTATED => 'Файл аннотирован',
            self::TYPE_GRADED => 'Оценка: ' . ($this->metadata['grade'] ?? '—') . '/10',
            self::TYPE_REVISION_REQUESTED => 'Отправлено на доработку',
            default => $this->type,
        };
    }

    /**
     * Log an activity
     */
    public static function log(int $submissionId, string $type, ?int $userId = null, ?array $metadata = null): self
    {
        return self::create([
            'submission_id' => $submissionId,
            'user_id' => $userId ?? auth()->id(),
            'type' => $type,
            'metadata' => $metadata,
        ]);
    }
}
