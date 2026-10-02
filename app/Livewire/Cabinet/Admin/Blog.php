<?php

namespace App\Livewire\Cabinet\Admin;

use App\Livewire\Cabinet\Admin\Concerns\AdminScreen;
use App\Models\BlogPost;
use App\Models\BlogTag;
use App\Services\BlogService;
use App\Support\HumanDate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Блог на сайте (serdal.ru/blog): опубликованные, на проверке (статьи учителей), запланированные, черновики, теги.
 * Черновики учителей здесь не видны, пока их не отправили на проверку. Логика — App\Services\BlogService.
 */
#[Layout('components.layouts.cabinet', ['title' => 'Блог', 'active' => 'blog'])]
class Blog extends Component
{
    use AdminScreen;

    private const TABS = ['published' => 'Опубликованные', 'review' => 'На проверке', 'reports' => 'Жалобы', 'scheduled' => 'Запланированные', 'drafts' => 'Черновики', 'tags' => 'Теги'];

    #[Url(except: 'published')]
    public string $tab = 'published';

    /** Окно тега: id — переименовать; удаление — с подтверждением. */
    public ?int $editingTag = null;
    public string $tagName = '';
    public ?int $deletingTag = null;

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

    private function blog(): BlogService
    {
        return app(BlogService::class);
    }

    /* ---------- Теги ---------- */

    public function editTag(int $id): void
    {
        $this->tagName = BlogTag::findOrFail($id)->name;
        $this->resetValidation();
        $this->editingTag = $id;
    }

    public function closeTag(): void
    {
        $this->editingTag = null;
    }

    public function saveTag(): void
    {
        $this->authorizeAdmin();
        $this->validate(['tagName' => ['required', 'string', 'max:40']], ['tagName.required' => 'Напишите название']);
        $this->blog()->renameTag(BlogTag::findOrFail($this->editingTag), $this->tagName);
        $this->editingTag = null;
        $this->dispatch('toast', message: 'Тег переименован');
    }

    public function askDeleteTag(int $id): void
    {
        $this->deletingTag = $id;
    }

    public function closeDeleteTag(): void
    {
        $this->deletingTag = null;
    }

    public function deleteTag(): void
    {
        $this->authorizeAdmin();
        $this->blog()->deleteTag(BlogTag::findOrFail($this->deletingTag));
        $this->deletingTag = null;
        $this->dispatch('toast', message: 'Тег удален — у статей его больше нет');
    }

    /* ---------- Жалобы на комментарии ---------- */

    private function comments(): \App\Services\BlogCommentService
    {
        return app(\App\Services\BlogCommentService::class);
    }

    public function deleteComment(int $id): void
    {
        $this->comments()->delete(\App\Models\BlogComment::findOrFail($id), $this->authorizeAdmin());
        $this->dispatch('toast', message: 'Комментарий удален');
    }

    public function dismissReports(int $id): void
    {
        $this->authorizeAdmin();
        $this->comments()->dismissReports(\App\Models\BlogComment::findOrFail($id));
        $this->dispatch('toast', message: 'Жалобы отклонены — комментарий остается');
    }

    /* ---------- Вид ---------- */

    public function render()
    {
        $tags = $this->tab === 'tags' ? $this->blog()->tags() : collect();
        $reports = $this->tab === 'reports' ? $this->comments()->reported() : collect();
        $items = in_array($this->tab, ['tags', 'reports'], true) ? collect() : $this->items();
        $published = BlogPost::published()->count();

        return view('livewire.cabinet.admin.blog', [
            'tabs' => self::TABS,
            'counts' => ['review' => $this->blog()->pendingCount(), 'reports' => $this->comments()->reportsCount()],
            'reports' => $reports,
            'items' => $items,
            'tags' => $tags,
            'deleting' => $this->deletingTag ? $tags->firstWhere('id', $this->deletingTag) : null,
            'facts' => $published ? plural_ru($published, 'статья на сайте', 'статьи на сайте', 'статей на сайте') : null,
            'empty' => match ($this->tab) {
                'review' => 'Статей на проверке нет — когда учитель отправит статью, она появится здесь',
                'reports' => 'Жалоб нет — когда кто-то пожалуется на комментарий, он появится здесь',
                'scheduled' => 'Запланированных статей нет — выберите время публикации в статье',
                'drafts' => 'Черновиков нет',
                'tags' => 'Тегов пока нет — добавьте их в настройках статьи',
                default => 'Пока ничего не опубликовано — напишите первую статью',
            },
        ]);
    }

    private function items()
    {
        $query = match ($this->tab) {
            'review' => BlogPost::whereNull('published_at')->where('review_status', BlogPost::REVIEW_PENDING)->orderBy('submitted_at'),
            'scheduled' => BlogPost::whereNotNull('published_at')->where('published_at', '>', now())->orderBy('published_at'),
            'drafts' => BlogPost::whereNull('published_at')->whereNull('review_status')->latest('updated_at'),
            default => BlogPost::published()->latest('published_at'),
        };

        return $query->with(['author', 'tags'])->get()->map(fn (BlogPost $p) => [
            'id' => $p->id,
            'title' => $p->title ?: 'Без заголовка',
            'meta' => implode(' · ', array_filter([
                $p->author ? $p->author->name : null,
                match ($this->tab) {
                    'review' => 'прислал ' . HumanDate::at($p->submitted_at ?? $p->updated_at),
                    'scheduled' => 'выйдет ' . HumanDate::at($p->published_at),
                    'drafts' => 'изменена ' . HumanDate::day($p->updated_at),
                    default => HumanDate::day($p->published_at) . ' · ' . $p->readingMinutes() . ' мин чтения',
                },
                $p->tags->isNotEmpty() ? $p->tags->pluck('name')->implode(', ') : null,
            ])),
            'views' => $this->tab === 'published' ? plural_ru($p->views_count, 'просмотр', 'просмотра', 'просмотров') : null,
        ]);
    }
}
