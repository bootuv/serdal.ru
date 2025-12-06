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
}
