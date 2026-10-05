<?php

namespace App\Demo;

use App\Demo\Teacher\News;
use App\Models\Announcement;
use App\Services\AnnouncementService;

/**
 * Баннер важной непрочитанной новости на «Сегодня» (<livewire:cabinet.news-banner />) — для Stub:
 * Screen::components() => ['cabinet.news-banner' => NewsBanner::entry()].
 * Крестик (dismiss) — действие из actions(): ?newsHidden=1 прячет баннер.
 */
final class NewsBanner
{
    /** @return array{0: string, 1: array} [view, data] */
    public static function entry(): array
    {
        $news = request()->query('newsHidden') === '1' ? null : News::announcements()
            ->filter(fn (Announcement $a) => $a->is_important && in_array($a->id, News::UNREAD, true))
            ->sortByDesc('published_at')
            ->first();

        return ['livewire.cabinet.news-banner', [
            'news' => $news ? [
                'id' => $news->id,
                'title' => $news->title,
                'excerpt' => $news->excerpt(),
                'url' => AnnouncementService::url($news, auth()->user()),
            ] : null,
        ]];
    }

    /** Действия баннера — добавить в actions() экрана, где он стоит. */
    public static function actions(): array
    {
        return ['dismiss' => ['set' => ['newsHidden' => '1']]];
    }
}
