<?php

namespace App\Livewire;

use App\Models\MeetingSession;
use App\Models\User;
use Livewire\Component;

class SessionHistory extends Component
{
    public int $roomId;
    public int $perPage = 10;
    public int $currentPage = 1;
    public ?string $viewUrl = null;

    // Deletion request modal state
    public bool $showDeletionModal = false;
    public ?int $deletionSessionId = null;
    public string $deletionReason = '';

    public function mount(int $roomId, ?string $viewUrl = null)
    {
        $this->roomId = $roomId;
        $this->viewUrl = $viewUrl;
    }

    public function loadMore()
    {
        $this->currentPage++;
    }

    public function getTotalCount(): int
    {
        return MeetingSession::where('room_id', $this->roomId)->count();
    }

    public function getSessions()
    {
        return MeetingSession::where('room_id', $this->roomId)
            ->with(['room.participants', 'room.user.lessonTypes'])
            ->orderByDesc('started_at')
            ->take($this->perPage * $this->currentPage)
            ->get();
    }

    public function hasMore(): bool
    {
        return $this->getSessions()->count() < $this->getTotalCount();
    }

    public function openDeletionModal(int $sessionId)
    {
        $this->deletionSessionId = $sessionId;
        $this->deletionReason = '';
        $this->showDeletionModal = true;
    }

    public function closeDeletionModal()
    {
        $this->showDeletionModal = false;
        $this->deletionSessionId = null;
        $this->deletionReason = '';
    }

    public function submitDeletionRequest()
    {
        $this->validate([
            'deletionReason' => 'required|min:3',
        ], [
            'deletionReason.required' => 'Укажите причину удаления',
            'deletionReason.min' => 'Причина должна содержать минимум 3 символа',
        ]);

        $session = MeetingSession::find($this->deletionSessionId);

        if ($session) {
            $session->update([
                'deletion_requested_at' => now(),
                'deletion_reason' => $this->deletionReason,
            ]);

            // Notify admins
            $admins = User::where('role', User::ROLE_ADMIN)->get();
            foreach ($admins as $admin) {
                $admin->notify(new \App\Notifications\SessionDeletionRequested($session, auth()->user()));
            }

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Запрос на удаление отправлен',
            ]);
        }

        $this->closeDeletionModal();
    }

    public function cancelDeletionRequest(int $sessionId)
    {
        $session = MeetingSession::find($sessionId);

        if ($session && $session->deletion_requested_at) {
            $session->update([
                'deletion_requested_at' => null,
                'deletion_reason' => null,
            ]);

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Запрос на удаление отменён',
            ]);
        }
    }

    public function render()
    {
        return view('livewire.session-history', [
            'sessions' => $this->getSessions(),
            'totalCount' => $this->getTotalCount(),
            'hasMore' => $this->hasMore(),
        ]);
    }
}
