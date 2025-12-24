<?php

namespace App\Livewire;

use App\Models\MeetingSession;
use Livewire\Component;

class SessionHistory extends Component
{
    public int $roomId;
    public int $perPage = 10;
    public int $currentPage = 1;
    public ?string $viewUrl = null;

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
            ->orderByDesc('started_at')
            ->take($this->perPage * $this->currentPage)
            ->get();
    }

    public function hasMore(): bool
    {
        return $this->getSessions()->count() < $this->getTotalCount();
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
