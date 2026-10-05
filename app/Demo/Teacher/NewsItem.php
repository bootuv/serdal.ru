<?php

namespace App\Demo\Teacher;

use App\Demo\Screen;
use App\Services\AnnouncementService;
use App\Support\HumanDate;
use App\Support\RichText;

/** Новость целиком — App\Livewire\Cabinet\NewsItem. Новости — News::announcements(). */
class NewsItem extends Screen
{
    public const PATH = 'news/{id}';

    public const EXAMPLES = ['news/15', 'news/14', 'news/13', 'news/12', 'news/11'];

    public string $view = 'livewire.cabinet.news-item';

    public string $title = 'Новости';

    public ?string $active = 'news';

    public function data(): array
    {
        $all = News::announcements();
        // Нет такой новости — самая свежая (в настоящем кабинете — возврат к списку с тостом)
        $a = $all->get((int) $this->param('id')) ?? $all->sortByDesc('published_at')->first();

        return [
            'title' => $a->title,
            'when' => HumanDate::at($a->published_at),
            'body' => RichText::players(RichText::html($a->body)),
            'backUrl' => AnnouncementService::listUrl(auth()->user()),
            'announcementId' => $a->id,
        ];
    }
}
