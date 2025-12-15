<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Room extends Model
{
    use HasFactory;

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
}
