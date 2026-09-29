<?php

namespace App\Livewire\Cabinet;

use App\Models\Announcement;
use App\Services\AnnouncementService;
use App\Support\HumanDate;
use App\Support\RichText;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** Новость целиком. Открыл — прочитал. */
#[Layout('components.layouts.cabinet', ['title' => 'Новости', 'active' => 'news'])]
class NewsItem extends Component
{
    public int $announcementId = 0;

    public function mount(string $announcement): void
    {
        $service = app(AnnouncementService::class);
        $model = ctype_digit($announcement) ? Announcement::find((int) $announcement) : null;

        // Новость удалили или сняли с публикации, а ссылка осталась в уведомлении — возвращаем к списку
        if (! $model || ! $service->canSee($model, auth()->user())) {
            session()->flash('toast', 'Эта новость больше недоступна');
            $this->redirect(AnnouncementService::listUrl(auth()->user()));

            return;
        }

        $service->markRead($model, auth()->user());
        $this->announcementId = $model->id;
    }

    public function render()
    {
        $a = Announcement::find($this->announcementId);
        if (! $a) {
            return '<div></div>'; // уходим к списку (mount)
        }

        return view('livewire.cabinet.news-item', [
            'title' => $a->title,
            'when' => HumanDate::at($a->published_at),
            'body' => RichText::html($a->body),
            'backUrl' => AnnouncementService::listUrl(auth()->user()),
        ]);
    }
}
