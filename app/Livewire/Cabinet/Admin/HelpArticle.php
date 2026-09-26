<?php

namespace App\Livewire\Cabinet\Admin;

use App\Livewire\Cabinet\Admin\Concerns\AdminScreen;
use App\Models\HelpArticle as Article;
use App\Models\HelpCategory;
use App\Services\HelpCenterService;
use App\Support\HumanDate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Статья базы знаний: новая (/cabinet/admin/help/articles/new) или существующая.
 * Макет: AdminHelpArticle. Поля и правила — как в Filament HelpArticleResource; слаг не показываем — он генерируется из заголовка.
 */
#[Layout('components.layouts.cabinet', ['title' => 'Статья базы знаний', 'active' => 'help'])]
class HelpArticle extends Component
{
    use AdminScreen;
    use WithFileUploads;

    public ?int $articleId = null;

    public string $title = '';
    public string $categoryId = '';
    public string $excerpt = '';
    public string $content = '';
    public bool $published = true;

    /** Видео: file — загруженный файл, link — ссылка Kinescope/YouTube/VK Видео/Rutube. */
    public string $videoSource = 'file';
    public string $videoUrl = '';
    public $video = null;
    public bool $removeVideo = false;

    /** Картинка для текста (временная загрузка, см. x-ui.editor-media). */
    public $image = null;

    public bool $saved = false;
    public bool $dirty = false;
    public bool $confirmDelete = false;

    public function mount(string $article): void
    {
        $this->authorizeAdmin();

        if ($article === 'new') {
            $first = HelpCategory::orderBy('audience')->orderBy('sort_order')->first();
            $this->categoryId = (string) (request()->integer('category') ?: $first?->id ?? '');

            return;
        }

        abort_unless(ctype_digit($article), 404);
        $model = Article::findOrFail((int) $article);

        $this->articleId = $model->id;
        $this->title = $model->title;
        $this->categoryId = (string) $model->help_category_id;
        $this->excerpt = (string) $model->excerpt;
        $this->content = (string) $model->content;
        $this->published = (bool) $model->is_published;
        $this->videoUrl = (string) $model->video_url;
        $this->videoSource = ! $model->video_file && filled($model->video_url) ? 'link' : 'file';
    }

    public function updated(string $property): void
    {
        if (! in_array($property, ['saved', 'dirty', 'confirmDelete', 'image'], true)) {
            $this->saved = false;
            $this->dirty = true;
        }

        if ($property === 'video') {
            $this->removeVideo = false;
            $this->validateOnly('video');
        }
    }

    public function togglePublished(): void
    {
        $this->published = ! $this->published;
        $this->saved = false;
        $this->dirty = true;
    }

    public function discardVideo(): void
    {
        $this->video = null;
        $this->removeVideo = true;
        $this->saved = false;
        $this->dirty = true;
    }

    protected function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'categoryId' => ['required', 'integer', 'exists:help_categories,id'],
            'excerpt' => ['nullable', 'string', 'max:1000'],
            'content' => ['nullable', 'string'],
            'videoSource' => ['required', 'in:file,link'],
            'videoUrl' => ['nullable', 'url', 'max:255', function ($attribute, $value, $fail) {
                if ($this->videoSource === 'link' && filled($value) && ! (new Article(['video_url' => $value]))->embed_url) {
                    $fail('Вставьте ссылку на Kinescope, YouTube, VK Видео или Rutube');
                }
            }],
            'video' => ['nullable', 'file', 'mimetypes:video/mp4,video/webm', 'max:102400'],
        ];
    }

    protected function messages(): array
    {
        return [
            'title.required' => 'Введите заголовок',
            'categoryId.required' => 'Выберите категорию',
            'videoUrl.url' => 'Вставьте ссылку на видео целиком, вместе с https://',
            'video.mimetypes' => 'Нужен файл MP4 или WebM',
            'video.max' => 'Файл больше 100 МБ — сожмите видео',
        ];
    }

    public function save(): void
    {
        $this->validate();

        $existing = $this->articleId ? Article::findOrFail($this->articleId) : null;
        $article = app(HelpCenterService::class)->saveArticle($existing, [
            'help_category_id' => $this->categoryId,
            'title' => $this->title,
            'excerpt' => $this->excerpt,
            'content' => $this->content,
            'is_published' => $this->published,
            'video_source' => $this->videoSource,
            'video_url' => $this->videoUrl,
            'video' => $this->videoSource === 'file' ? $this->video : null,
            'remove_video' => $this->removeVideo,
        ]);

        if (! $existing) {
            session()->flash('toast', 'Статья создана');
            $this->redirectRoute('cabinet.admin.help-article', ['article' => $article->id]);

            return;
        }

        $this->content = (string) $article->content;
        $this->reset('video', 'removeVideo');
        $this->saved = true;
        $this->dirty = false;
    }

    /** Картинка в текст: кладём на CDN и возвращаем адрес (вставляет редактор). */
    public function storeImage(): ?string
    {
        try {
            $this->validateOnly('image', ['image' => ['required', 'image', 'max:5120']], [
                'image.image' => 'Нужна картинка PNG, JPG, GIF или WebP',
                'image.max' => 'Картинка больше 5 МБ',
            ]);
        } catch (ValidationException $e) {
            $this->image = null;
            $this->dispatch('toast', message: collect($e->errors())->flatten()->first() ?: 'Картинку не удалось загрузить', tone: 'danger');

            return null;
        }

        $url = app(HelpCenterService::class)->storeImage($this->image);
        $this->image = null;

        return $url;
    }

    public function askDelete(): void
    {
        $this->confirmDelete = true;
    }

    public function closeDelete(): void
    {
        $this->confirmDelete = false;
    }

    /** «Снимите с публикации» вместо удаления. */
    public function unpublishInstead(): void
    {
        $article = Article::findOrFail($this->articleId);
        app(HelpCenterService::class)->setPublished($article, false);

        $this->published = false;
        $this->confirmDelete = false;
        $this->dispatch('toast', message: 'Статья снята с публикации');
    }

    public function delete(): void
    {
        $article = Article::findOrFail($this->articleId);
        app(HelpCenterService::class)->deleteArticle($article);

        session()->flash('toast', 'Статья удалена');
        $this->redirectRoute('cabinet.admin.help');
    }

    public function render()
    {
        $article = $this->articleId ? Article::with('category')->find($this->articleId) : null;
        $category = HelpCategory::find((int) $this->categoryId);

        $categories = HelpCategory::orderBy('sort_order')->orderBy('id')->get()
            ->groupBy('audience')
            ->sortKeys()
            ->mapWithKeys(fn ($group, $audience) => [
                ($audience === HelpCategory::AUDIENCE_TUTOR ? 'Для учителей' : 'Для учеников') => $group
                    ->mapWithKeys(fn (HelpCategory $c) => [$c->id => $c->name . ($c->is_published ? '' : ' · скрыта')])->all(),
            ]);

        $facts = [];
        if ($category) {
            $facts[] = ($category->audience === HelpCategory::AUDIENCE_TUTOR ? 'Для учителей' : 'Для учеников') . ' · ' . $category->name;
        }
        if ($article) {
            $facts[] = $article->is_published
                ? number_format($article->views_count, 0, ',', ' ') . ' ' . plural_ru($article->views_count, 'просмотр', 'просмотра', 'просмотров', false)
                : 'черновик — на сайте не видна';
            $facts[] = $this->dirty ? 'есть несохранённые изменения' : 'изменена ' . HumanDate::at($article->updated_at);
        }

        $heading = trim($this->title) !== '' ? trim($this->title) : ($article ? 'Без заголовка' : 'Новая статья');

        return view('livewire.cabinet.admin.help-article', [
            'article' => $article,
            'heading' => $heading,
            'facts' => implode(' · ', $facts),
            'categories' => $categories,
            'siteUrl' => $article && $article->category ? $article->url : null,
            'videoName' => $article?->video_file ? basename($article->video_file) : null,
        ]);
    }
}
