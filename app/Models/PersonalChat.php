<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * Личный чат учителя и ученика: доступен, пока ученик в списке учителя (teacher_student), даже без общих занятий.
 * Ученика убрали из списка — чат остаётся для чтения.
 */
class PersonalChat extends Model
{
    protected $fillable = ['teacher_id', 'student_id'];

    protected static function booted(): void
    {
        // Вложения сообщений удаляет хук модели Message
        static::deleting(fn (PersonalChat $chat) => $chat->messages()->get()->each->delete());
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /** Ученик в списке учителя — в чат можно писать. */
    public function isActive(): bool
    {
        return DB::table('teacher_student')
            ->where('teacher_id', $this->teacher_id)
            ->where('student_id', $this->student_id)
            ->exists();
    }

    /** Собеседник пользователя в этом чате. */
    public function other(User $user): ?User
    {
        return $user->id === $this->teacher_id ? $this->student : $this->teacher;
    }
}
