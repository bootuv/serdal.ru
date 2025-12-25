<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class RoomSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'room_id',
        'type',
        'scheduled_at',
        'recurrence_type',
        'recurrence_days',
        'recurrence_day_of_month',
        'recurrence_time',
        'start_date',
        'end_date',
        'duration_minutes',
        'is_active',
        'google_event_id',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'recurrence_days' => 'array',
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

        // Auto-fill start_date for one-time schedules
        static::creating(function ($schedule) {
            if ($schedule->type === 'once' && !$schedule->start_date && $schedule->scheduled_at) {
                $schedule->start_date = Carbon::parse($schedule->scheduled_at)->format('Y-m-d');
            }
        });

        static::saved(function ($schedule) {
            $schedule->room->updateNextStart();
        });

        static::deleted(function ($schedule) {
            $schedule->room->updateNextStart();
        });
    }

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * Check if schedule is active for a given date/time
     */
    public function isActiveAt(Carbon $datetime): bool
    {
        if (!$this->is_active) {
            return false;
        }

        // One-time schedule
        if ($this->type === 'once') {
            if (!$this->scheduled_at) {
                return false;
            }
            // Check if within 1 minute window
            return $datetime->between(
                $this->scheduled_at->copy()->subMinute(),
                $this->scheduled_at->copy()->addMinute()
            );
        }

        // Recurring schedule
        $startDate = Carbon::parse($this->start_date);
        $endDate = $this->end_date ? Carbon::parse($this->end_date) : null;

        if (
            $datetime->lt($startDate->startOfDay()) ||
            ($endDate && $datetime->gt($endDate->endOfDay()))
        ) {
            return false;
        }

        // Check time matches (within 1 minute window)
        if (!$this->recurrence_time) {
            return false;
        }

        $scheduleTime = Carbon::parse($this->recurrence_time);
        $datetimeTime = $datetime->format('H:i');

        if ($datetimeTime !== $scheduleTime->format('H:i')) {
            return false;
        }

        // Check day matches pattern
        return match ($this->recurrence_type) {
            'daily' => true,
            'weekly' => in_array($datetime->dayOfWeek, $this->recurrence_days ?? []),
            'monthly' => $datetime->day === $this->recurrence_day_of_month,
            default => false,
        };
    }

    /**
     * Get next scheduled occurrence
     */
    public function getNextOccurrence(): ?Carbon
    {
        if (!$this->is_active) {
            return null;
        }

        $now = now();

        if ($this->type === 'once') {
            return $this->scheduled_at && $this->scheduled_at->gt($now)
                ? $this->scheduled_at
                : null;
        }

        // For recurring, calculate next occurrence
        $startDate = $this->start_date ? Carbon::parse($this->start_date) : now()->startOfDay();
        $startTime = Carbon::parse($this->recurrence_time ?? '00:00');

        $searchStart = now();
        // If the start date is in the future, start searching from there
        if ($startDate->gt($searchStart)) {
            $searchStart = $startDate->copy()->setTimeFrom($startTime);
        }

        // Limit search to 1 year to avoid infinite loops
        $limit = $searchStart->copy()->addYear();

        $current = $searchStart->copy();

        // If we are starting today, check if the time has already passed
        // If strict comparison is needed, we might need to increment day immediately if time passed
        // checking the very first candidate
        $candidateTime = $current->copy()->setTimeFrom($startTime);

        $duration = $this->duration_minutes ?? 90;

        // If the calculated time for today is in the past (plus duration), move to tomorrow as a base
        if ($candidateTime->copy()->addMinutes($duration)->lt(now())) {
            $current->addDay();
        }

        while ($current->lt($limit)) {
            // Check end date
            if ($this->end_date && $current->gt(Carbon::parse($this->end_date)->endOfDay())) {
                return null;
            }

            $currentWithTime = $current->copy()->setTimeFrom($startTime);
            $isValidDay = false;

            if ($this->recurrence_type === 'daily') {
                $isValidDay = true;
            } elseif ($this->recurrence_type === 'weekly' && !empty($this->recurrence_days)) {
                if (in_array($current->dayOfWeek, $this->recurrence_days)) {
                    $isValidDay = true;
                }
            } elseif ($this->recurrence_type === 'monthly' && $this->recurrence_day_of_month) {
                if ($current->day === $this->recurrence_day_of_month) {
                    $isValidDay = true;
                }
            }

            if ($isValidDay) {
                // Check strict future OR within current duration window
                if ($currentWithTime->copy()->addMinutes($duration)->gt(now())) {
                    return $currentWithTime;
                }
            }

            $current->addDay();
        }

        return null; // No next occurrence found
    }
}
