<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    use HasFactory;

    protected $fillable = [
        'room_id',
        'personal_chat_id',
        'user_id',
        'content',
        'attachments',
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
        'attachments' => 'array',
    ];

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function personalChat(): BelongsTo
    {
        return $this->belongsTo(PersonalChat::class);
    }

    /** Канал реалтайма: чат занятия или личный чат. */
    public function channelName(): string
    {
        return $this->personal_chat_id ? 'personal-chat.' . $this->personal_chat_id : 'room.' . $this->room_id;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected static function booted()
    {
        static::deleting(function ($message) {
            if (!empty($message->attachments)) {
                foreach ($message->attachments as $attachment) {
                    if (isset($attachment['path'])) {
                        \Illuminate\Support\Facades\Storage::disk('s3')->delete($attachment['path']);
                    }
                }
            }
        });
    }
}
