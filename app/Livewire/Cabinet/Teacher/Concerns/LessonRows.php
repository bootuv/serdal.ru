<?php

namespace App\Livewire\Cabinet\Teacher\Concerns;

use App\Models\MeetingSession;
use App\Models\Recording;
use App\Services\TeacherScheduleService;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;

/**
 * Строки занятий учителя для экранов «Сегодня» и «Расписание» (partials/lesson-row.blade.php).
 * Вхождения приходят из TeacherScheduleService::lessons().
 */
trait LessonRows
{
    /**
     * Экран занятия: новый, если готов, иначе — страница занятия в старом кабинете.
     * $at — исходное время конкретного занятия серии (для отменённых и перенесённых).
     */
    protected function lessonUrl(int $roomId, ?int $sessionId = null, ?CarbonInterface $at = null): string
    {
        return Route::has('cabinet.teacher.lesson')
            ? route('cabinet.teacher.lesson', ['room' => $roomId]
                + ($sessionId ? ['session' => $sessionId] : [])
                + ($at ? ['at' => $at->format('Y-m-d\TH:i')] : []))
            : url('/tutor/rooms/' . $roomId);
    }

    /** Ссылка на занятие из строки расписания: у отменённого и перенесённого — на это занятие серии. */
    protected function occurrenceUrl(array $lesson, ?int $sessionId = null): string
    {
        return $this->lessonUrl($lesson['roomId'], $sessionId,
            ! $sessionId && ($lesson['cancelled'] || $lesson['movedFrom']) ? $lesson['originalStart'] : null);
    }

    /**
     * Пометки занятия в подписи строки: [срочное жирным, остальное].
     * «отменено · причина», «Перенесено с пятницы, 15:00», «Первое занятие · пробное», «Дополнительное занятие».
     *
     * @return array{0:?string, 1:?string}|null
     */
    protected function occurrenceMarks(array $lesson): ?array
    {
        return match (true) {
            $lesson['cancelled'] => [null, 'Отменено' . ($lesson['reason'] ? ': ' . $lesson['reason'] : '')],
            (bool) $lesson['movedFrom'] => ['Перенесено', TeacherScheduleService::movedFromLabel($lesson['movedFrom'])],
            $lesson['trial'] => ['Первое занятие', 'пробное'],
            $lesson['extra'] => [null, 'Дополнительное занятие'],
            default => null,
        };
    }

    /** Ссылка на запись занятия: экран «Записи», иначе — запись в старом кабинете. */
    protected function recordingUrl(Recording $recording): string
    {
        return Route::has('cabinet.teacher.recordings')
            ? route('cabinet.teacher.recordings', ['open' => $recording->id])
            : url('/tutor/recordings/' . $recording->id);
    }

    /**
     * Главная кнопка ближайшего занятия: «Вернуться в класс» (идёт), «Начать занятие» (ссылка на запуск
     * или окно «Занятие недоступно», если подписка не позволяет). Пока идёт другое занятие — кнопки нет (RoomController::start).
     */
    protected function startAction(array $lesson, ?int $runningRoomId, bool $blocked): ?array
    {
        return match (true) {
            $lesson['running'] => ['kind' => 'join', 'url' => route('rooms.connect', $lesson['roomId'])],
            $runningRoomId !== null && $runningRoomId !== $lesson['roomId'] => null,
            $blocked => ['kind' => 'blocked'],
            default => ['kind' => 'start', 'url' => route('rooms.start', $lesson['roomId'])],
        };
    }

    /** «Индивидуальное» / «Групповое · 5 учеников». */
    protected function kindFacts(array $lesson): string
    {
        return $lesson['group']
            ? 'Групповое · ' . plural_ru($lesson['count'], 'ученик', 'ученика', 'учеников')
            : 'Индивидуальное';
    }

    protected function lessonRow(array $lesson, array $o = []): array
    {
        $avatar = null;
        if ($o['withAvatar'] ?? false) {
            $avatar = $lesson['student'] ? ['name' => $lesson['student']->name, 'id' => $lesson['student']->id, 'photo' => $lesson['student']->photoThumb()] : ['group' => true];
        }

        return [
            'key' => $lesson['key'],
            'time' => $lesson['start']->format('H:i'),
            'duration' => ($o['withDuration'] ?? false) ? $lesson['duration'] . ' мин' : null,
            'dim' => $o['dim'] ?? $lesson['past'],
            'heading' => $lesson['heading'],
            'status' => $o['status'] ?? null,
            'facts' => $o['facts'] ?? null,
            // «Перенесено с пятницы, 15:00» — без точки между жирным и остальным
            'glue' => ($o['status'] ?? null) === 'Перенесено' ? ' ' : ' · ',
            'badge' => $o['badge'] ?? null,
            'url' => $this->occurrenceUrl($lesson, $o['sessionId'] ?? null),
            'avatar' => $avatar,
            'focus' => $o['focus'] ?? false,
            'action' => $o['action'] ?? null,
        ];
    }

    /**
     * Проведённые занятия для прошедших вхождений: занятие той же комнаты, начатое в пределах двух часов от времени по расписанию.
     *
     * @return array<string, MeetingSession> ключ вхождения → занятие
     */
    protected function matchSessions(Collection $lessons, Collection $sessions): array
    {
        $matched = [];
        $used = [];

        foreach ($lessons as $lesson) {
            $session = $sessions
                ->filter(fn (MeetingSession $s) => $s->room_id === $lesson['roomId'] && ! isset($used[$s->id])
                    && $s->started_at->between($lesson['start']->copy()->subHours(2), $lesson['end']->copy()->addHours(2)))
                ->sortBy(fn (MeetingSession $s) => abs($s->started_at->diffInMinutes($lesson['start'])))
                ->first();

            if ($session) {
                $matched[$lesson['key']] = $session;
                $used[$session->id] = true;
            }
        }

        return $matched;
    }

    /**
     * Записи, которые уже можно смотреть, для проведённых занятий.
     *
     * @return array<int, Recording> id занятия → запись
     */
    protected function readyRecordings(Collection $sessions): array
    {
        if ($sessions->isEmpty()) {
            return [];
        }

        $recordings = Recording::whereIn('meeting_id', $sessions->pluck('meeting_id')->filter()->unique())
            ->where(fn ($q) => $q->whereNotNull('s3_url')->orWhereNotNull('url'))
            ->where('start_time', '>=', $sessions->min('started_at')->copy()->subDay())
            ->get();

        $result = [];
        foreach ($sessions as $session) {
            $recording = $recordings->first(fn (Recording $r) => $r->belongsToSession($session));
            if ($recording) {
                $result[$session->id] = $recording;
            }
        }

        return $result;
    }
}
