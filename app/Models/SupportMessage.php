<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'support_chat_id',
        'user_id',
        'content',
        'attachments',
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
        'attachments' => 'array',
    ];

    /**
     * Чат, к которому относится сообщение
     */
    public function supportChat(): BelongsTo
    {
        return $this->belongsTo(SupportChat::class);
    }

    /**
     * Автор сообщения
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected static function booted()
    {
        static::deleting(function ($message) {
            if (!empty($message->attachments)) {
                foreach ($message->attachments as $attachment) {
                    \Illuminate\Support\Facades\Storage::disk('public')->delete($attachment);
                }
            }
        });
    }
}
