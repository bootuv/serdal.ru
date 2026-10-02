<?php

namespace App\Livewire\Cabinet\Admin;

use App\Livewire\Cabinet\Admin\Concerns\AdminScreen;
use App\Models\BlogPost;
use App\Support\HumanDate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/** Блог на сайте (serdal.ru/blog): опубликованные, запланированные, черновики. Логика — App\Services\BlogService. */
#[Layout('components.layouts.cabinet', ['title' => 'Блог', 'active' => 'blog'])]
class Blog extends Component
{
    use AdminScreen;

    private const TABS = ['published' => 'Опубликованные', 'scheduled' => 'Запланированные', 'drafts' => 'Черновики'];

    #[Url(except: 'published')]
    public string $tab = 'published';

    public function mount(): void
    {
        $this->authorizeAdmin();
        $this->updatedTab();
    }

    public function updatedTab(): void
    {
        if (! array_key_exists($this->tab, self::TABS)) {
            $this->tab = 'published';
        }
    }

    public function render()
    {
        $query = match ($this->tab) {
            'scheduled' => BlogPost::whereNotNull('published_at')->where('published_at', '>', now())->orderBy('published_at'),
            'drafts' => BlogPost::whereNull('published_at')->latest('updated_at'),
            default => BlogPost::published()->latest('published_at'),
        };

        $items = $query->get()->map(fn (BlogPost $p) => [
            'id' => $p->id,
            'title' => $p->title ?: 'Без заголовка',
            'meta' => match ($this->tab) {
                'scheduled' => 'выйдет ' . HumanDate::at($p->published_at),
                'drafts' => 'изменена ' . HumanDate::day($p->updated_at),
                default => HumanDate::day($p->published_at) . ' · ' . $p->readingMinutes() . ' мин чтения',
            },
            'views' => $this->tab === 'published' ? plural_ru($p->views_count, 'просмотр', 'просмотра', 'просмотров') : null,
        ]);

        $published = BlogPost::published()->count();

        return view('livewire.cabinet.admin.blog', [
            'tabs' => self::TABS,
            'items' => $items,
            'facts' => $published ? plural_ru($published, 'статья на сайте', 'статьи на сайте', 'статей на сайте') : null,
            'empty' => match ($this->tab) {
                'scheduled' => 'Запланированных статей нет — выберите время публикации в статье',
                'drafts' => 'Черновиков нет',
                default => 'Пока ничего не опубликовано — напишите первую статью',
            },
        ]);
    }
}
