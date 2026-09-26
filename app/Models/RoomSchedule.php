<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class RoomSchedule extends Model
{
    use HasFactory;

    /** Длительность занятия по умолчанию, минут: окно «Запланировать занятие», расчёт next_start, сервисы расписания. */
    public const DEFAULT_DURATION = 60;

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

    /** Отменённые и перенесённые занятия этого правила. */
    public function exceptions()
    {
        return $this->hasMany(RoomScheduleException::class);
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
     * Вхождения правила в интервале [from, to] без учёта исключений: разовое — по дате, повторяющееся — по дням (isActiveAt).
     * Как и раньше в сервисах расписания, дни берутся целиком: от начала дня from до to.
     *
     * @return array<int, Carbon>
     */
    public function rawOccurrences(CarbonInterface $from, CarbonInterface $to): array
    {
        if ($this->type === 'once') {
            return $this->scheduled_at && $this->scheduled_at->between($from, $to) ? [$this->scheduled_at->copy()] : [];
        }

        $result = [];
        $current = Carbon::instance($from)->startOfDay();

        while ($current->lte($to)) {
            $candidate = $current->copy()->setTimeFromTimeString($this->recurrence_time ?? '00:00');
            if ($this->isActiveAt($candidate)) {
                $result[] = $candidate;
            }
            $current->addDay();
        }

        return $result;
    }

    /** Исходное время вхождения этого правила в указанную дату (без учёта исключений) или null, если в этот день занятия нет. */
    public function occurrenceOn(CarbonInterface $date): ?Carbon
    {
        return $this->rawOccurrences(Carbon::instance($date)->startOfDay(), Carbon::instance($date)->endOfDay())[0] ?? null;
    }

    /** Длительность занятия по правилу, минут. */
    public function minutes(): int
    {
        return (int) ($this->duration_minutes ?: self::DEFAULT_DURATION);
    }

    /**
     * Ближайшее вхождение с учётом исключений: идущее сейчас (ещё не закончилось по расписанию) или следующее.
     * Отменённые вхождения пропускаются, перенесённые — стоят в новое время со своей длительностью.
     *
     * @param  Collection<int, RoomScheduleException>|null  $exceptions  исключения этого правила, если уже загружены
     * @return array{start: Carbon, duration: int, original: Carbon, exception: ?RoomScheduleException}|null
     */
    public function nextOccurrenceDetails(?Collection $exceptions = null): ?array
    {
        if (! $this->is_active) {
            return null;
        }

        $exceptions ??= $this->relationLoaded('exceptions') ? $this->exceptions : $this->exceptions()->get();
        $skip = $exceptions->mapWithKeys(fn (RoomScheduleException $e) => [$e->original_date->format('Y-m-d') => true])->all();
        $now = now();
        $best = null;

        foreach ($exceptions as $exception) {
            if (! $exception->isMoved()) {
                continue;
            }
            $duration = (int) ($exception->duration_minutes ?: $this->minutes());
            if ($exception->starts_at->copy()->addMinutes($duration)->gt($now) && (! $best || $exception->starts_at->lt($best['start']))) {
                $best = [
                    'start' => $exception->starts_at->copy(),
                    'duration' => $duration,
                    'original' => $exception->original_starts_at->copy(),
                    'exception' => $exception,
                ];
            }
        }

        $raw = $this->nextRawOccurrence($skip);
        if ($raw && (! $best || $raw->lt($best['start']))) {
            $best = ['start' => $raw, 'duration' => $this->minutes(), 'original' => $raw->copy(), 'exception' => null];
        }

        return $best;
    }

    /** Ближайшее вхождение с учётом исключений (время начала). */
    public function getNextOccurrence(): ?Carbon
    {
        return $this->nextOccurrenceDetails()['start'] ?? null;
    }

    /**
     * Ближайшее вхождение по правилу (в пределах года), которое ещё не закончилось; даты из $skip пропускаются.
     *
     * @param  array<string, bool>  $skip  Y-m-d => true
     */
    private function nextRawOccurrence(array $skip = []): ?Carbon
    {
        $now = now();
        $duration = $this->minutes();

        if ($this->type === 'once') {
            return $this->scheduled_at
                && ! isset($skip[$this->scheduled_at->format('Y-m-d')])
                && $this->scheduled_at->copy()->addMinutes($duration)->gt($now)
                ? $this->scheduled_at->copy()
                : null;
        }

        $startDate = $this->start_date ? Carbon::parse($this->start_date) : $now->copy()->startOfDay();
        $current = $startDate->gt($now) ? $startDate->copy()->startOfDay() : $now->copy()->startOfDay();
        $limit = $current->copy()->addYear();
        $endDate = $this->end_date ? Carbon::parse($this->end_date)->endOfDay() : null;

        while ($current->lt($limit)) {
            if ($endDate && $current->gt($endDate)) {
                return null;
            }

            if (! isset($skip[$current->format('Y-m-d')])) {
                $candidate = $current->copy()->setTimeFromTimeString($this->recurrence_time ?? '00:00');
                if ($this->isActiveAt($candidate) && $candidate->copy()->addMinutes($duration)->gt($now)) {
                    return $candidate;
                }
            }

            $current->addDay();
        }

        return null;
    }
}
