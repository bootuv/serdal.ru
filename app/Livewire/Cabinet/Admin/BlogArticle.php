<?php

namespace App\Livewire\Cabinet\Admin;

use App\Livewire\Cabinet\Admin\Concerns\AdminScreen;
use App\Models\BlogPost;
use App\Services\AnnouncementService;
use App\Services\BlogService;
use App\Support\HumanDate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Статья блога: новая (/cabinet/admin/blog/new) или существующая. Блочный редактор (x-ui.block-editor),
 * обложка, адрес и описание для поиска; черновик → опубликовать сразу или по времени.
 */
#[Layout('components.layouts.cabinet', ['title' => 'Статья', 'active' => 'blog'])]
class BlogArticle extends Component
{
    use AdminScreen;
    use WithFileUploads;

    public ?int $postId = null;

    public string $title = '';
    public string $body = '';
    public string $slug = '';
    public string $excerpt = '';
    public string $coverUrl = '';

    public string $when = 'now';
    public string $publishAt = '';

    /** Картинка в текст (x-ui.block-editor) и обложка — временные загрузки. */
    public $image = null;
    public $cover = null;

    public bool $confirmDelete = false;

    public function mount(string $post): void
    {
        $this->authorizeAdmin();

        if ($post === 'new') {
            return;
        }

        abort_unless(ctype_digit($post), 404);
        $model = BlogPost::findOrFail((int) $post);

        $this->postId = $model->id;
        $this->title = $model->title;
        $this->body = (string) $model->body;
        $this->slug = $model->slug;
        $this->excerpt = (string) $model->excerpt;
        $this->coverUrl = (string) $model->cover_url;
        if ($model->isScheduled()) {
            $this->when = 'later';
            $this->publishAt = $model->published_at->format('Y-m-d\TH:i');
        }
    }

    private function model(): ?BlogPost
    {
        return $this->postId ? BlogPost::findOrFail($this->postId) : null;
    }

    private function service(): BlogService
    {
        return app(BlogService::class);
    }

    protected function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:120'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'body' => ['nullable', 'string'],
        ];
    }

    protected function messages(): array
    {
        return ['title.required' => 'Напишите заголовок статьи'];
    }

    private function saveAndGo(?\Carbon\CarbonInterface $publishedAt, string $toast): void
    {
        $this->authorizeAdmin();
        $post = $this->service()->save($this->model(), [
            'title' => $this->title,
            'slug' => $this->slug,
            'excerpt' => $this->excerpt,
            'cover_url' => $this->coverUrl,
            'body' => $this->body,
            'published_at' => $publishedAt,
        ], auth()->user());

        session()->flash('toast', $toast);
        $this->redirectRoute('cabinet.admin.blog-article', ['post' => $post->id]);
    }

    public function saveDraft(): void
    {
        $this->validate();
        $this->saveAndGo(null, 'Черновик сохранен');
    }

    /** Главная кнопка: опубликованную — сохранить; черновик — опубликовать сразу или запланировать. */
    public function submit(): void
    {
        $this->validate();
        $model = $this->model();

        if ($model?->isPublished()) {
            $this->saveAndGo($model->published_at, 'Статья сохранена');

            return;
        }

        if ($this->when === 'later') {
            $at = AnnouncementService::parseTime($this->publishAt);
            if (! $at) {
                throw ValidationException::withMessages(['publishAt' => 'Выберите дату и время публикации']);
            }
            if ($at->isPast()) {
                throw ValidationException::withMessages(['publishAt' => 'Это время уже прошло — выберите позже или опубликуйте сразу']);
            }
            $this->saveAndGo($at, 'Статья выйдет ' . HumanDate::at($at));

            return;
        }

        $this->saveAndGo(now(), 'Статья опубликована');
    }

    public function unpublish(): void
    {
        $this->authorizeAdmin();
        $model = $this->model();
        abort_unless($model, 404);
        $this->service()->unpublish($model);
        $this->when = 'now';
        $this->publishAt = '';
        $this->dispatch('toast', message: 'Статья снята с сайта — теперь это черновик');
    }

    public function askDelete(): void
    {
        $this->confirmDelete = true;
    }

    public function closeDelete(): void
    {
        $this->confirmDelete = false;
    }

    public function delete(): void
    {
        $this->authorizeAdmin();
        $model = $this->model();
        abort_unless($model, 404);
        $this->service()->delete($model);
        session()->flash('toast', 'Статья удалена');
        $this->redirectRoute('cabinet.admin.blog');
    }

    /** Картинка в текст: на CDN, адрес вставляет редактор. */
    public function storeImage(): ?string
    {
        return $this->upload('image');
    }

    /** Обложка: загружается сразу, адрес сохранится вместе со статьей. */
    public function updatedCover(): void
    {
        $url = $this->upload('cover');
        if ($url) {
            $this->coverUrl = $url;
        }
    }

    public function removeCover(): void
    {
        $this->coverUrl = '';
    }

    private function upload(string $prop): ?string
    {
        try {
            $this->validateOnly($prop, [$prop => ['required', 'image', 'max:5120']], [
                $prop . '.image' => 'Нужна картинка PNG, JPG, GIF или WebP',
                $prop . '.max' => 'Картинка больше 5 МБ',
            ]);
        } catch (ValidationException $e) {
            $this->{$prop} = null;
            $this->dispatch('toast', message: collect($e->errors())->flatten()->first() ?: 'Картинку не удалось загрузить', tone: 'danger');

            return null;
        }

        $url = $this->service()->storeImage($this->{$prop});
        $this->{$prop} = null;

        return $url;
    }

    public function render()
    {
        $model = $this->model();
        $status = match (true) {
            ! $model => 'new',
            $model->isPublished() => 'published',
            $model->isScheduled() => 'scheduled',
            default => 'draft',
        };

        return view('livewire.cabinet.admin.blog-article', [
            'model' => $model,
            'status' => $status,
            'heading' => trim($this->title) !== '' ? trim($this->title) : ($model ? 'Без заголовка' : 'Новая статья'),
            'facts' => match ($status) {
                'published' => 'На сайте с ' . HumanDate::date($model->published_at) . ' · ' . plural_ru($model->views_count, 'просмотр', 'просмотра', 'просмотров'),
                'scheduled' => 'Выйдет ' . HumanDate::at($model->published_at),
                'draft' => 'Черновик — виден только в админке',
                default => null,
            },
            'siteUrl' => $model ? route('blog.show', $model->slug) : null,
            'slugPreview' => $this->slug !== '' ? $this->slug : ($model?->slug ?: BlogPost::slugFrom($this->title ?: 'statya', $this->postId)),
            'descriptionLength' => mb_strlen(trim($this->excerpt)),
            'submitLabel' => match (true) {
                $status === 'published', $status === 'scheduled' && $this->when === 'later' => 'Сохранить',
                $this->when === 'later' => 'Запланировать',
                default => 'Опубликовать',
            },
        ]);
    }
}
