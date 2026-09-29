<?php

namespace App\Livewire\Cabinet;

use App\Models\Announcement;
use App\Services\AnnouncementService;
use App\Support\HumanDate;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** Новости от администрации — один экран для учителя и ученика. Логика — App\Services\AnnouncementService. */
#[Layout('components.layouts.cabinet', ['title' => 'Новости', 'active' => 'news'])]
class News extends Component
{
    private const PAGE = 20;

    public int $limit = self::PAGE;

    public function more(): void
    {
        $this->limit += self::PAGE;
    }

    public function readAll(): void
    {
        $service = app(AnnouncementService::class);
        $service->unreadFor(auth()->user())->get()->each(fn (Announcement $a) => $service->markRead($a, auth()->user()));
        $this->dispatch('toast', message: 'Все новости отмечены прочитанными');
    }

    public function render()
    {
        $user = auth()->user();
        $service = app(AnnouncementService::class);

        $query = $service->visibleFor($user);
        $total = (clone $query)->count();
        $items = $query->limit($this->limit)->get();
        $unreadIds = $service->unreadFor($user)->pluck('id')->all();

        return view('livewire.cabinet.news', [
            'items' => $items->map(fn (Announcement $a) => [
                'id' => $a->id,
                'title' => $a->title,
                'excerpt' => $a->excerpt(),
                'when' => HumanDate::day($a->published_at),
                'pinned' => $a->is_pinned,
                'unread' => in_array($a->id, $unreadIds, true),
                'url' => AnnouncementService::url($a, $user),
            ]),
            'unread' => count($unreadIds),
            'hasMore' => $total > $items->count(),
        ]);
    }
}
