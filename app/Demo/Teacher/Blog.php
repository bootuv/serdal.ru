<?php

namespace App\Demo\Teacher;

use App\Demo\Concerns\TeacherBlogDemo;
use App\Demo\Screen;
use App\Models\BlogPost;
use App\Support\HumanDate;

/** «Мои статьи» — App\Livewire\Cabinet\Teacher\Blog: статья на сайте, статья на проверке и черновик. */
class Blog extends Screen
{
    use TeacherBlogDemo;

    public const PATH = 'blog';

    public string $view = 'livewire.cabinet.teacher.blog';

    public string $title = 'Мои статьи';

    public ?string $active = 'blog';

    public function data(): array
    {
        // Как в настоящем экране: новые изменения сверху
        $posts = collect(self::blogPosts())->sortByDesc(fn (BlogPost $p) => $p->updated_at->timestamp)->values();

        $items = $posts->map(fn (BlogPost $p) => [
            'id' => $p->id,
            'title' => $p->title ?: 'Без заголовка',
            'meta' => match (true) {
                $p->isPublished() => 'На сайте с ' . HumanDate::date($p->published_at) . ' · ' . plural_ru($p->views_count, 'просмотр', 'просмотра', 'просмотров'),
                $p->isScheduled() => 'Выйдет ' . HumanDate::at($p->published_at),
                $p->review_status === BlogPost::REVIEW_PENDING => 'Отправлена ' . HumanDate::at($p->submitted_at ?? $p->updated_at),
                default => 'Изменена ' . HumanDate::day($p->updated_at),
            },
            'badge' => match (true) {
                $p->isPublished() => null,
                $p->isScheduled() => ['neutral', 'Принята'],
                $p->review_status === BlogPost::REVIEW_PENDING => ['neutral', 'На проверке'],
                $p->review_status === BlogPost::REVIEW_RETURNED => ['danger', 'Вернули на доработку'],
                default => ['neutral', 'Черновик'],
            },
        ]);

        $published = $posts->filter->isPublished()->count();

        return [
            'items' => $items,
            'facts' => implode(' · ', [
                plural_ru($published, 'статья на сайте', 'статьи на сайте', 'статей на сайте'),
                plural_ru(self::BLOG_FOLLOWERS[90], 'подписчик', 'подписчика', 'подписчиков'),
            ]),
            // Публичная страница автора — за пределами демо: demo-cabinet.js покажет тост
            'authorUrl' => route('blog.author', 'zarema-malsagova'),
        ];
    }
}
