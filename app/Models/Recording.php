<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Recording extends Model
{
    use HasFactory;

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
        'vk_video_id',
        'vk_video_url',
        'vk_access_key',
        'vk_uploaded_at',
    ];

    protected $casts = [
        'published' => 'boolean',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'raw_data' => 'array',
        'vk_uploaded_at' => 'datetime',
    ];

    public function room()
    {
        return $this->belongsTo(Room::class, 'meeting_id', 'meeting_id');
    }
}
