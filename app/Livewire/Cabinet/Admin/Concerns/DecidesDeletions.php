<?php

namespace App\Livewire\Cabinet\Admin\Concerns;

use App\Models\MeetingSession;
use App\Services\AdminLessonsService;
use App\Services\SessionDeletionService;
use App\Support\HumanDate;
use Illuminate\Support\Str;

/**
 * Окна «Удалить занятие?» и «Отклонить запрос?» для проведённого занятия (макет AdminSessions).
 * Разметка — partials/lessons-decision-modals.blade.php. Логика — SessionDeletionService.
 * После решения вызывается afterDecision(string $decision) экрана (переход, тост).
 */
trait DecidesDeletions
{
    /** Проведённое занятие в открытом окне. */
    public ?int $decideId = null;

    /** delete | reject */
    public ?string $decideMode = null;

    public string $rejectReply = '';

    public function askDeleteSession(int $id): void
    {
        $this->decisionTarget($id);
        $this->decideId = $id;
        $this->decideMode = 'delete';
    }

    public function askRejectSession(int $id): void
    {
        $session = $this->decisionTarget($id);
        abort_unless($session->deletion_requested_at, 404);

        $this->resetValidation();
        $this->rejectReply = '';
        $this->decideId = $id;
        $this->decideMode = 'reject';
    }

    public function closeDecision(): void
    {
        $this->decideId = null;
        $this->decideMode = null;
    }

    public function confirmDeleteSession(): void
    {
        $session = $this->decisionTarget((int) $this->decideId);
        abort_if($session->status === 'running', 422);

        $name = AdminLessonsService::firstName($session->room()->withTrashed()->first()?->user);
        app(SessionDeletionService::class)->delete($session, auth()->user());

        $this->closeDecision();
        $this->afterDecision('Занятие удалено, ' . $name . ' получит уведомление');
    }

    public function confirmRejectSession(): void
    {
        $this->validate(['rejectReply' => ['nullable', 'string', 'max:1000']], ['rejectReply.max' => 'Ответ длиннее 1000 символов']);

        $session = $this->decisionTarget((int) $this->decideId);
        abort_unless($session->deletion_requested_at, 404);

        $name = AdminLessonsService::firstName($session->room()->withTrashed()->first()?->user);
        app(SessionDeletionService::class)->reject($session, auth()->user(), $this->rejectReply);

        $this->closeDecision();
        $this->afterDecision('Запрос отклонён, ' . $name . ' получит ' . (trim($this->rejectReply) !== '' ? 'ваш ответ' : 'уведомление'));
    }

    private function decisionTarget(int $id): MeetingSession
    {
        $this->authorizeAdmin();
        $session = MeetingSession::find($id);
        abort_unless($session && $session->status !== null, 404);

        return $session;
    }

    /** Данные окна для разметки. */
    protected function decisionView(): array
    {
        if (! $this->decideId || ! $this->decideMode) {
            return ['decision' => null];
        }

        $session = MeetingSession::with(['room' => fn ($q) => $q->withTrashed()->with(['user', 'participants:id'])])->find($this->decideId);
        if (! $session) {
            return ['decision' => null];
        }

        $room = $session->room;
        $teacher = $room?->user;
        $end = $session->ended_at ?? $session->started_at;
        $single = ($room?->participants->count() ?? 0) === 1;

        return ['decision' => [
            'mode' => $this->decideMode,
            'sub' => ($session->started_at ? Str::ucfirst(HumanDate::day($session->started_at)) . ', ' . $session->started_at->format('H:i') . '–' . $end->format('H:i') : '')
                . ($teacher ? ' · ' . $teacher->name : ''),
            'who' => $single ? 'учителя и ученика' : 'учителя и учеников',
            'teacher' => AdminLessonsService::firstName($teacher),
            'requested' => (bool) $session->deletion_requested_at,
        ]];
    }
}
