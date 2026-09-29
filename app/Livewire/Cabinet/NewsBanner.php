<?php

namespace App\Livewire\Cabinet;

use App\Models\Announcement;
use App\Services\AnnouncementService;
use Livewire\Component;

/**
 * Важная непрочитанная новость — белая карточка над фокус-блоком на «Сегодня» учителя и «Главной» ученика.
 * Пропадает, когда новость прочитали или закрыли крестиком (закрыть = прочитать).
 */
class NewsBanner extends Component
{
    public function dismiss(int $id): void
    {
        if ($announcement = Announcement::find($id)) {
            app(AnnouncementService::class)->markRead($announcement, auth()->user());
        }
    }

    public function render()
    {
        $user = auth()->user();
        $service = app(AnnouncementService::class);
        $news = $service->banner($user);

        return view('livewire.cabinet.news-banner', [
            'news' => $news ? [
                'id' => $news->id,
                'title' => $news->title,
                'excerpt' => $news->excerpt(),
                'url' => AnnouncementService::url($news, $user),
            ] : null,
        ]);
    }
}
