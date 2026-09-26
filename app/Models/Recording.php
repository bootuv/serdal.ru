<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\SoftDeletes;

class Recording extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'meeting_id',
        'record_id',
        'name',
        'published',
        'start_time',
        'end_time',
        'participants',
        'url',
        'raw_data',
        's3_url',
        's3_uploaded_at',
    ];

    protected $casts = [
        'published' => 'boolean',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'raw_data' => 'array',
        's3_uploaded_at' => 'datetime',
    ];

    public function room()
    {
        return $this->belongsTo(Room::class, 'meeting_id', 'meeting_id');
    }

    /**
     * Записи, доступные ученику: занятия его учителей (как в старом кабинете ученика
     * и в RecordingDownloadController).
     */
    public function scopeForStudent(Builder $query, User $student): Builder
    {
        // Только записи занятий, к которым ученик назначен участником
        $meetingIds = Room::whereHas('participants', fn ($q) => $q->where('users.id', $student->id))
            ->pluck('meeting_id')
            ->filter();

        return $query->whereIn('meeting_id', $meetingIds);
    }

    /** Записи занятий учителя (как в старом кабинете учителя, RecordingResource). */
    public function scopeForTeacher(Builder $query, User $teacher): Builder
    {
        return $query->whereIn('meeting_id', Room::where('user_id', $teacher->id)->pluck('meeting_id')->filter());
    }

    /**
     * Записи, которые показываем в списке: с видео, со ссылкой на просмотр или свежие (< 2 часов, ещё обрабатываются).
     * Скрывает устаревшие записи, которые ещё не убрала синхронизация.
     */
    public function scopeListed(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q->whereNotNull('s3_url')
            ->orWhereNotNull('url')
            ->orWhere('start_time', '>', now()->subHours(2)));
    }

    /** Относится ли запись к занятию: по внутреннему id встречи или по времени. */
    public function belongsToSession(MeetingSession $session): bool
    {
        if ($this->meeting_id !== $session->meeting_id) {
            return false;
        }

        if ($session->internal_meeting_id && str_starts_with((string) $this->record_id, $session->internal_meeting_id)) {
            return true;
        }

        return $this->start_time && $session->started_at
            && $this->start_time->between($session->started_at->copy()->subMinutes(10), ($session->ended_at ?? $session->started_at->copy()->addHours(4)));
    }

    protected static function booted()
    {
        static::deleted(function ($recording) {
            // Delete from S3 if URL exists
            if (!empty($recording->s3_url)) {
                try {
                    $storageService = app(\App\Services\RecordingStorageService::class);
                    $storageService->deleteFromS3($recording->s3_url);
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error('Failed to delete S3 recording on model deletion', [
                        'recording_id' => $recording->id,
                        's3_url' => $recording->s3_url,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        });
    }
}
