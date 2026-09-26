<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * План конкретного занятия (карточка «План занятия» на экране занятия учителя).
 * starts_at — исходное время вхождения по расписанию: при переносе одного занятия план остаётся с ним.
 */
class LessonPlan extends Model
{
    protected $fillable = ['room_id', 'starts_at', 'body', 'updated_by'];

    protected $casts = ['starts_at' => 'datetime'];

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class)->withTrashed();
    }

    /** Пункты плана: по одному на строку, без пустых и маркеров списка. */
    public function items(): array
    {
        return collect(preg_split('/\R/u', (string) $this->body))
            ->map(fn ($line) => trim(preg_replace('/^\s*(?:[-–—•*]|\d+[.)])\s*/u', '', $line)))
            ->filter()
            ->values()
            ->all();
    }
}
