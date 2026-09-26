<?php

namespace App\Services;

use App\Models\MeetingSession;
use App\Models\SessionDeletionDecision;
use App\Models\User;
use App\Notifications\SessionDeletedByAdmin;
use App\Notifications\SessionDeletionApproved;
use App\Notifications\SessionDeletionRejected;
use Illuminate\Support\Facades\DB;

/**
 * Удаление проведённых занятий администратором: одобрить или отклонить запрос учителя, удалить без запроса.
 * Решения по запросам сохраняются (SessionDeletionDecision) — «Решено раньше» в админке.
 * Используют новая админка и старая (Filament, ViewMeetingSession).
 */
class SessionDeletionService
{
    /** Ожидающие решения запросы — сначала давние. */
    public function pending()
    {
        return MeetingSession::whereNotNull('deletion_requested_at')
            ->with(['room' => fn ($q) => $q->withTrashed()->with(['user', 'participants:id,name'])])
            ->orderBy('deletion_requested_at');
    }

    /** Одобрить запрос: занятие удаляется, учитель получает «Занятие удалено». */
    public function approve(MeetingSession $session, User $admin): void
    {
        $teacher = $this->teacher($session);
        $roomName = $this->roomName($session);
        $when = $this->when($session);

        DB::transaction(function () use ($session, $admin) {
            $this->record($session, $admin, SessionDeletionDecision::DELETED);
            $session->delete();
        });

        $teacher?->notify(new SessionDeletionApproved($roomName, $when));
    }

    /** Отклонить запрос: занятие остаётся, учитель получает отказ (с ответом, если он есть). */
    public function reject(MeetingSession $session, User $admin, ?string $reply = null): void
    {
        $reply = trim((string) $reply);
        $reply = $reply === '' ? null : mb_substr($reply, 0, 1000);
        $teacher = $this->teacher($session);

        DB::transaction(function () use ($session, $admin, $reply) {
            $this->record($session, $admin, SessionDeletionDecision::REJECTED, $reply);
            $session->cancelDeletionRequest();
        });

        $teacher?->notify(new SessionDeletionRejected($session, $reply));
    }

    /** Удалить проведённое занятие без запроса учителя: учитель получает уведомление. С запросом — как одобрение. */
    public function delete(MeetingSession $session, User $admin): void
    {
        if ($session->deletion_requested_at) {
            $this->approve($session, $admin);

            return;
        }

        $teacher = $this->teacher($session);
        $roomName = $this->roomName($session);
        $when = $this->when($session);

        $session->delete();

        $teacher?->notify(new SessionDeletedByAdmin($roomName, $when));
    }

    /** «сб, 21 сентября в 12:00» — без «сегодня»: уведомление читают позже. */
    public function when(MeetingSession $session): string
    {
        return $session->started_at ? TeacherLessonService::when($session->started_at) : '';
    }

    private function teacher(MeetingSession $session): ?User
    {
        return $session->room()->withTrashed()->first()?->user ?? $session->user;
    }

    private function roomName(MeetingSession $session): string
    {
        return $session->room()->withTrashed()->value('name') ?? 'Занятие';
    }

    private function record(MeetingSession $session, User $admin, string $decision, ?string $reply = null): void
    {
        if (! $session->deletion_requested_at) {
            return;
        }

        SessionDeletionDecision::create([
            'meeting_session_id' => $decision === SessionDeletionDecision::REJECTED ? $session->id : null,
            'room_id' => $session->room_id,
            'teacher_id' => $this->teacher($session)?->id,
            'admin_id' => $admin->id,
            'room_name' => $this->roomName($session),
            'session_started_at' => $session->started_at,
            'session_ended_at' => $session->ended_at,
            'requested_at' => $session->deletion_requested_at,
            'reason' => $session->deletion_reason,
            'decision' => $decision,
            'reply' => $reply,
        ]);
    }
}
