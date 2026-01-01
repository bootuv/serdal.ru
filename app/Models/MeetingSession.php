<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MeetingSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'room_id',
        'meeting_id',
        'internal_meeting_id',
        'started_at',
        'ended_at',
        'status',
        'participant_count',
        'analytics_data',
        'settings_snapshot',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'analytics_data' => 'array',
        'settings_snapshot' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function getStudentAttendance(): array
    {
        if (!$this->relationLoaded('room')) {
            $this->load('room.participants');
        }

        if (!$this->room) {
            return ['attended' => 0, 'total' => 0, 'color' => 'gray'];
        }

        if (!$this->room->relationLoaded('participants')) {
            $this->room->load('participants');
        }

        $room = $this->room;

        $studentIds = $room->participants->pluck('id')->map(fn($id) => (string) $id)->toArray();
        $total = count($studentIds);

        if ($total === 0) {
            return ['attended' => 0, 'total' => 0, 'color' => 'gray'];
        }

        $analytics = $this->analytics_data ?? [];
        $sessionParticipants = $analytics['participants'] ?? [];
        // Extract user IDs from session participants, ensuring they are strings for comparison
        $sessionUserIds = array_map(function ($p) {
            return (string) ($p['user_id'] ?? '');
        }, $sessionParticipants);

        $attended = 0;
        foreach ($studentIds as $id) {
            if (in_array($id, $sessionUserIds, true)) {
                $attended++;
            }
        }

        $color = '#ef4444'; // Red
        if ($attended === $total) {
            $color = '#22c55e'; // Green
        } elseif ($attended > $total / 2) {
            $color = '#f59e0b'; // Amber/Orange
        }

        return ['attended' => $attended, 'total' => $total, 'color' => $color];
    }
}
