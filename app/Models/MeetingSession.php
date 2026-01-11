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
        'pricing_snapshot',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'analytics_data' => 'array',
        'settings_snapshot' => 'array',
        'pricing_snapshot' => 'array',
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

    /**
     * Capture pricing data at the moment of session completion.
     * This stores prices as they were when the lesson occurred.
     */
    public function capturePricingSnapshot(): array
    {
        if (!$this->relationLoaded('room')) {
            $this->load('room.participants', 'room.user.lessonTypes');
        }

        $room = $this->room;
        if (!$room) {
            return [];
        }

        // Get payment type from lesson type
        $lessonType = $room->user?->lessonTypes?->where('type', $room->type)->first();
        $paymentType = $lessonType?->payment_type ?? 'per_lesson';

        $snapshot = [
            'payment_type' => $paymentType,
            'base_price' => $room->base_price,
            'room_type' => $room->type,
            'participants' => [],
            'total_cost' => 0,
        ];

        // Get attended participant IDs
        $analytics = $this->analytics_data ?? [];
        $participantsData = $analytics['participants'] ?? [];
        $attendedIds = collect($participantsData)
            ->pluck('user_id')
            ->map(fn($id) => (string) $id)
            ->toArray();

        // Calculate prices for each participant
        foreach ($room->participants as $participant) {
            $price = $room->getEffectivePrice($participant->id) ?? 0;
            $attended = in_array((string) $participant->id, $attendedIds);

            $participantData = [
                'user_id' => $participant->id,
                'name' => $participant->name,
                'price' => $price,
                'attended' => $attended,
            ];

            $snapshot['participants'][] = $participantData;

            // Calculate total based on payment type
            if ($paymentType === 'monthly') {
                // Monthly: all participants count
                $snapshot['total_cost'] += $price;
            } else {
                // Per-lesson: only attended participants
                if ($attended) {
                    $snapshot['total_cost'] += $price;
                }
            }
        }

        return $snapshot;
    }
}
