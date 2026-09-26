<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Исключение из правила расписания: одно занятие отменено (cancelled) или перенесено (moved).
 * Правило (RoomSchedule) не меняется; вхождения с исключениями учитывают сервисы расписания,
 * Room::updateNextStart и синхронизация с Google Календарём.
 *
 * @property Carbon $original_date
 * @property Carbon $original_starts_at
 * @property ?Carbon $starts_at
 */
class RoomScheduleException extends Model
{
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_MOVED = 'moved';

    protected $fillable = [
        'room_id',
        'room_schedule_id',
        'original_date',
        'original_starts_at',
        'status',
        'starts_at',
        'duration_minutes',
        'reason',
        'notified',
        'created_by',
        'google_event_id',
    ];

    protected $casts = [
        'original_date' => 'date',
        'original_starts_at' => 'datetime',
        'starts_at' => 'datetime',
        'duration_minutes' => 'integer',
        'notified' => 'boolean',
    ];

    protected static function booted(): void
    {
        // Правило «трогаем»: наблюдатель RoomSchedule пересчитает next_start и обновит Google Календарь
        $touch = function (RoomScheduleException $exception) {
            if ($exception->isDirty('google_event_id') && count($exception->getDirty()) === 1) {
                return;
            }
            RoomSchedule::find($exception->room_schedule_id)?->touch();
        };

        static::saved($touch);
        static::deleted(fn (RoomScheduleException $e) => RoomSchedule::find($e->room_schedule_id)?->touch());
    }

    /** Дата храним строкой Y-m-d: по ней ищем исключение (уникально для правила). */
    public function setOriginalDateAttribute($value): void
    {
        $this->attributes['original_date'] = Carbon::parse($value)->toDateString();
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class)->withTrashed();
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(RoomSchedule::class, 'room_schedule_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function isMoved(): bool
    {
        return $this->status === self::STATUS_MOVED && $this->starts_at !== null;
    }
}
