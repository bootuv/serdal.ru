<?php

namespace App\Livewire\Cabinet\Teacher;

use App\Models\BlogPost;
use App\Models\User;
use App\Services\BlogService;
use App\Support\HumanDate;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** Статьи учителя в блоге Serdal: черновики, на проверке, вернули на доработку, опубликованные. Логика — App\Services\BlogService. */
#[Layout('components.layouts.cabinet', ['title' => 'Мои статьи', 'active' => 'blog'])]
class Blog extends Component
{
    public function mount(): void
    {
        abort_unless(auth()->user()?->role === User::ROLE_TUTOR, 403);
    }

    public function render(BlogService $blog)
    {
        $teacher = auth()->user();
        $posts = $blog->forTeacher($teacher)->latest('updated_at')->get();

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

        return view('livewire.cabinet.teacher.blog', [
            'items' => $items,
            'facts' => implode(' · ', array_filter([
                $published ? plural_ru($published, 'статья на сайте', 'статьи на сайте', 'статей на сайте') : null,
                ($followers = $blog->followersCount($teacher)) ? plural_ru($followers, 'подписчик', 'подписчика', 'подписчиков') : null,
            ])) ?: null,
            'authorUrl' => $published && $teacher->username ? route('blog.author', $teacher->username) : null,
        ]);
    }
}
