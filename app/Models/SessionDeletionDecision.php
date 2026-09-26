<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Решение администратора по запросу учителя на удаление проведённого занятия (SessionDeletionService).
 * Хранит копию названия и времени: при одобрении само занятие удаляется.
 */
class SessionDeletionDecision extends Model
{
    public const DELETED = 'deleted';

    public const REJECTED = 'rejected';

    protected $fillable = [
        'meeting_session_id',
        'room_id',
        'teacher_id',
        'admin_id',
        'room_name',
        'session_started_at',
        'session_ended_at',
        'requested_at',
        'reason',
        'decision',
        'reply',
    ];

    protected $casts = [
        'session_started_at' => 'datetime',
        'session_ended_at' => 'datetime',
        'requested_at' => 'datetime',
    ];

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }
}
