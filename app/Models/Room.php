<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Room extends Model
{
    use HasFactory;

    protected static function boot()
    {
        parent::boot();

        // When room is deleted, delete schedules one by one to trigger observers
        static::deleting(function (Room $room) {
            foreach ($room->schedules as $schedule) {
                $schedule->delete();
            }
        });
    }

    protected $fillable = [
        'user_id',
        'name',
        'type',
        'meeting_id',
        'moderator_pw',
        'attendee_pw',
        'welcome_msg',
        'is_running',
        'presentations',
        'record',
        'auto_start_recording',
        'allow_start_stop_recording',
        'mute_on_start',
        'webcams_only_for_moderator',
        'max_participants',
        'duration',
        'logout_url',
        'next_start',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'is_running' => 'boolean',
        'presentations' => 'array',
        'record' => 'boolean',
        'auto_start_recording' => 'boolean',
        'allow_start_stop_recording' => 'boolean',
        'mute_on_start' => 'boolean',
        'webcams_only_for_moderator' => 'boolean',
        'next_start' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function schedules()
    {
        return $this->hasMany(RoomSchedule::class);
    }
    public function participants()
    {
        return $this->belongsToMany(User::class);
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    public function sessions()
    {
        return $this->hasMany(MeetingSession::class);
    }

    public function getAvatarBgColorAttribute(): string
    {
        $colors = [
            '239, 68, 68',   // red-500
            '249, 115, 22',  // orange-500
            '245, 158, 11',  // amber-500
            '34, 197, 94',   // green-500
            '16, 185, 129',  // emerald-500
            '20, 184, 166',  // teal-500
            '6, 182, 212',   // cyan-500
            '14, 165, 233',  // sky-500
            '59, 130, 246',  // blue-500
            '99, 102, 241',  // indigo-500
            '139, 92, 246',  // violet-500
            '168, 85, 247',  // purple-500
            '217, 70, 239',  // fuchsia-500
            '236, 72, 153',  // pink-500
            '244, 63, 94',   // rose-500
        ];

        $rgb = $colors[$this->id % count($colors)];
        return "rgba({$rgb}, 0.16)";
    }

    public function getAvatarTextColorAttribute(): string
    {
        $colors = [
            '#ef4444', // red-500
            '#f97316', // orange-500
            '#f59e0b', // amber-500
            '#22c55e', // green-500
            '#10b981', // emerald-500
            '#14b8a6', // teal-500
            '#06b6d4', // cyan-500
            '#0ea5e9', // sky-500
            '#3b82f6', // blue-500
            '#6366f1', // indigo-500
            '#8b5cf6', // violet-500
            '#a855f7', // purple-500
            '#d946ef', // fuchsia-500
            '#ec4899', // pink-500
            '#f43f5e', // rose-500
        ];

        return $colors[$this->id % count($colors)];
    }

    /**
     * Calculate the next start date from all schedules
     */
    public function calculateNextStart(): ?\Carbon\Carbon
    {
        $nextDates = $this->schedules->map(fn($schedule) => $schedule->getNextOccurrence())->filter();

        if ($nextDates->isEmpty()) {
            return null;
        }

        return $nextDates->min();
    }

    public function updateNextStart(): void
    {
        $earliestDate = null;
        $duration = 0;

        foreach ($this->schedules as $schedule) {
            $nextDate = $schedule->getNextOccurrence();

            if ($nextDate) {
                if ($earliestDate === null || $nextDate->lt($earliestDate)) {
                    $earliestDate = $nextDate;
                    $duration = $schedule->duration_minutes;
                }
            }
        }

        $this->updateQuietly([
            'next_start' => $earliestDate,
            'duration' => $duration ?: 45, // Fallback to 45 if 0 or null
        ]);
    }
}
