<?php

namespace App\Livewire\Cabinet\Admin;

use App\Models\BlogPost;
use App\Models\User;
use App\Services\AnnouncementService;
use App\Services\BlogService;
use App\Support\HumanDate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Статья блога: новая (…/blog/new) или существующая — общий экран админки и кабинета учителя
 * (учителю — наследник App\Livewire\Cabinet\Teacher\BlogArticle). Блочный редактор посередине, настройки — в панели справа.
 *
 * Админ: публикует сразу или по времени, назначает автором любого учителя, проверяет статьи учителей
 * («Опубликовать» / «Вернуть на доработку» с комментарием).
 * Учитель: пишет свою статью и отправляет на проверку; опубликованную не меняет (только снять с публикации и править).
 * Черновики сохраняются сами (autosave), опубликованная — только кнопкой.
 */
#[Layout('components.layouts.cabinet', ['title' => 'Статья', 'active' => 'blog'])]
class BlogArticle extends Component
{
    use WithFileUploads;

    public ?int $postId = null;

    public string $title = '';
    public string $body = '';
    public string $slug = '';
    public string $excerpt = '';
    public string $coverUrl = '';
    /** @var array<int, string> */
    public array $tags = [];
    public string $newTag = '';
    /** Автор (только админ): id учителя или '' — «Команда Serdal». */
    public string $authorId = '';

    public string $when = 'now';
    public string $publishAt = '';

    /** Картинка в текст (x-ui.block-editor) и обложка — временные загрузки. */
    public $image = null;
    public $cover = null;

    public bool $confirmDelete = false;
    public bool $returning = false;
    public string $returnNote = '';

    /** Автосохранение: отпечаток последней сохраненной версии и время сохранения («14:32»). */
    public string $savedHash = '';
    public ?string $savedAt = null;

    /** Поля, которые живут в панели «Настройки статьи»: ошибка в них — открыть панель, иначе ее не видно. */
    private const SETTINGS_FIELDS = ['slug', 'excerpt', 'publishAt'];

    public function mount(string $post): void
    {
        $user = $this->authorizeRole();

        if ($post !== 'new') {
            abort_unless(ctype_digit($post), 404);
            $model = BlogPost::with('tags')->findOrFail((int) $post);
            abort_if($this->isTeacher() && ! $this->service()->teacherCanSee($model, $user), 404);

            $this->postId = $model->id;
            $this->title = $model->title;
            $this->body = (string) $model->body;
            $this->slug = $model->slug;
            $this->excerpt = (string) $model->excerpt;
            $this->coverUrl = (string) $model->cover_url;
            $this->tags = $model->tags->pluck('name')->all();
            $this->authorId = (string) $model->author_id;
            if ($model->isScheduled()) {
                $this->when = 'later';
                $this->publishAt = $model->published_at->format('Y-m-d\TH:i');
            }
        }

        $this->savedHash = $this->hash();
    }

    /* ---------- Роль ---------- */

    protected function isTeacher(): bool
    {
        return false;
    }

    protected function authorizeRole(): User
    {
        $user = auth()->user();
        abort_unless($user && $user->role === ($this->isTeacher() ? User::ROLE_TUTOR : User::ROLE_ADMIN), 403);

        return $user;
    }

    private function route(string $name): string
    {
        return ($this->isTeacher() ? 'cabinet.teacher.' : 'cabinet.admin.') . $name;
    }

    /** Учитель правит только свою неопубликованную статью. */
    private function canEdit(?BlogPost $model): bool
    {
        return ! $this->isTeacher() || ! $model || $this->service()->teacherCanEdit($model, auth()->user());
    }

    private function model(): ?BlogPost
    {
        return $this->postId ? BlogPost::findOrFail($this->postId) : null;
    }

    private function service(): BlogService
    {
        return app(BlogService::class);
    }

    /* ---------- Сохранение ---------- */

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

    public function exception($e, $stopPropagation): void
    {
        if ($e instanceof ValidationException && array_intersect(array_keys($e->errors()), self::SETTINGS_FIELDS)) {
            $this->dispatch('blog-settings');
        }
    }

    /** Отпечаток того, что пишет автор: изменился — есть несохраненные правки. */
    private function hash(): string
    {
        return md5(json_encode([trim($this->title), $this->body, trim($this->slug), trim($this->excerpt), $this->coverUrl, $this->tags, $this->authorId]));
    }

    /** Данные для BlogService::save. Без published_at — статус и время публикации не трогаем. */
    private function data(array $extra = []): array
    {
        $data = [
            'title' => $this->title,
            'excerpt' => $this->excerpt,
            'cover_url' => $this->coverUrl,
            'body' => $this->body,
            'tags' => $this->tags,
        ];
        if (! $this->isTeacher()) {
            $data['slug'] = $this->slug;
            $data['author_id'] = $this->authorId !== '' ? (int) $this->authorId : null;
        }

        return $data + $extra;
    }

    private function persist(array $extra = []): BlogPost
    {
        $model = $this->model();
        abort_unless($this->canEdit($model), 403);

        return $this->service()->save($model, $this->data($extra), auth()->user());
    }

    private function saveAndGo(array $extra, string $toast): BlogPost
    {
        $post = $this->persist($extra);
        session()->flash('toast', $toast);
        $this->redirectRoute($this->route('blog-article'), ['post' => $post->id]);

        return $post;
    }

    /**
     * Автосохранение (опрос раз в 5 секунд, пока вкладка открыта): черновик, запланированную и статью на проверке
     * сохраняем сами, статус не меняется. Опубликованную — нет: недописанная правка сразу ушла бы на сайт.
     */
    public function autosave(): void
    {
        $model = $this->model();
        if ($model?->isPublished() || ! $this->canEdit($model) || $this->hash() === $this->savedHash) {
            return;
        }
        if (! $model && trim($this->title) === '' && trim(strip_tags($this->body)) === '') {
            return;
        }

        try {
            $this->validate([
                'title' => ['nullable', 'string', 'max:255'],
                'slug' => ['nullable', 'string', 'max:120'],
                'excerpt' => ['nullable', 'string', 'max:500'],
            ]);
        } catch (ValidationException) {
            return; // ошибки покажем при нажатии кнопки
        }

        $post = $this->persist();
        if (! $model) {
            // Новая статья получила адрес — меняем его в строке браузера без перезагрузки
            $this->postId = $post->id;
            $this->js('history.replaceState(null, "", ' . json_encode(route($this->route('blog-article'), ['post' => $post->id])) . ')');
        }
        if ($this->slug !== '') {
            $this->slug = $post->slug;
        }
        $this->savedHash = $this->hash();
        $this->savedAt = now()->format('H:i');
    }

    /** Главная кнопка. Админ: опубликованную — сохранить; иначе опубликовать сразу или по времени. Учитель: отправить на проверку. */
    public function submit(): void
    {
        $this->validate();
        $model = $this->model();

        if ($this->isTeacher()) {
            $post = $this->persist();
            $this->service()->submit($post);
            session()->flash('toast', 'Статья отправлена на проверку — пришлем уведомление, когда ее опубликуют');
            $this->redirectRoute($this->route('blog-article'), ['post' => $post->id]);

            return;
        }

        if ($model?->isPublished()) {
            $this->saveAndGo([], 'Статья сохранена');

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
            $this->publishAndGo($at, 'Статья выйдет ' . HumanDate::at($at));

            return;
        }

        $this->publishAndGo(now(), 'Статья опубликована');
    }

    private function publishAndGo(\Carbon\CarbonInterface $at, string $toast): void
    {
        $was = $this->model();
        $post = $this->saveAndGo(['published_at' => $at], $toast);
        $this->service()->notifyPublished($post->fresh(), (bool) $was?->published_at);
    }

    /** «Опубликовать сейчас» из выпадающего меню — даже если выбрано «По времени». */
    public function publishNow(): void
    {
        abort_if($this->isTeacher(), 403);
        $this->validate();
        $this->publishAndGo(now(), 'Статья опубликована');
    }

    /** «Запланировать…» — открыть настройки на выборе времени. */
    public function planLater(): void
    {
        abort_if($this->isTeacher(), 403);
        $this->when = 'later';
        $this->dispatch('blog-settings');
    }

    /** «Сохранить как черновик» / «Вернуть в черновики» / «Снять с публикации» — статья уходит с сайта вместе с правками. */
    public function saveDraft(): void
    {
        abort_if($this->isTeacher(), 403);
        $this->validate();
        $was = $this->model();
        $this->saveAndGo(['published_at' => null], match (true) {
            (bool) $was?->isPublished() => 'Статья снята с сайта — теперь это черновик',
            (bool) $was?->isScheduled() => 'Публикация отменена — статья снова черновик',
            default => 'Черновик сохранен',
        });
    }

    /** Снять с публикации без сохранения правок (ссылка в настройках; учителю — чтобы снова править свою статью). */
    public function unpublish(): void
    {
        $model = $this->model();
        abort_unless($model, 404);
        abort_if($this->isTeacher() && ! $this->service()->teacherCanSee($model, auth()->user()), 403);
        $this->service()->unpublish($model);
        session()->flash('toast', $this->isTeacher() ? 'Статья снята с сайта — можно править и отправить на проверку снова' : 'Статья снята с сайта — теперь это черновик');
        $this->redirectRoute($this->route('blog-article'), ['post' => $model->id]);
    }

    /** Учитель забирает статью с проверки. */
    public function withdraw(): void
    {
        $model = $this->model();
        abort_unless($model && $this->isTeacher() && $this->service()->teacherCanEdit($model, auth()->user()), 403);
        $this->service()->withdraw($model);
        $this->dispatch('toast', message: 'Статья снята с проверки — это снова черновик');
    }

    /* ---------- Проверка (админ) ---------- */

    public function askReturn(): void
    {
        $this->returnNote = '';
        $this->resetValidation('returnNote');
        $this->returning = true;
    }

    public function closeReturn(): void
    {
        $this->returning = false;
    }

    public function returnForRework(): void
    {
        abort_if($this->isTeacher(), 403);
        $model = $this->model();
        abort_unless($model?->isTeacherDraft(), 404);
        $this->validate(['returnNote' => ['required', 'string', 'max:2000']], ['returnNote.required' => 'Напишите, что поправить']);
        $this->persist();
        $this->service()->returnForRework($model->fresh(), $this->returnNote);
        session()->flash('toast', 'Статья возвращена автору с комментарием');
        $this->redirectRoute('cabinet.admin.blog', ['tab' => 'review']);
    }

    /* ---------- Теги ---------- */

    /** Тег из списка существующих или новый (x-ui.tag-picker). */
    public function addTag(?string $name = null): void
    {
        $name = trim(preg_replace('/\s+/u', ' ', (string) ($name ?? $this->newTag)));
        $this->newTag = '';
        if ($name === '' || count($this->tags) >= 8 || in_array(mb_strtolower($name), array_map('mb_strtolower', $this->tags), true)) {
            return;
        }
        // Есть такой тег (без учета регистра) — берем его написание, чтобы не плодить «ЕГЭ» и «егэ»
        $existing = \App\Models\BlogTag::where('slug', BlogPost::toSlug($name))->value('name');
        $this->tags[] = $existing ?? mb_substr($name, 0, 40);
    }

    public function removeTag(int $i): void
    {
        unset($this->tags[$i]);
        $this->tags = array_values($this->tags);
    }

    /* ---------- Удаление ---------- */

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
        $model = $this->model();
        abort_unless($model, 404);
        abort_if($this->isTeacher() && ! $this->service()->teacherCanSee($model, auth()->user()), 403);
        $this->service()->delete($model);
        session()->flash('toast', 'Статья удалена');
        $this->redirectRoute($this->route('blog'));
    }

    /* ---------- Картинки ---------- */

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
            $this->validateOnly($prop, [$prop => ['required', 'image', 'max:15360']], [
                $prop . '.image' => 'Нужна картинка PNG, JPG, GIF или WebP',
                $prop . '.max' => 'Картинка больше 15 МБ',
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

    /* ---------- Вид ---------- */

    public function render()
    {
        $model = $this->model();
        $teacher = $this->isTeacher();
        $status = match (true) {
            ! $model => 'new',
            $model->isPublished() => 'published',
            $model->isScheduled() => 'scheduled',
            $model->review_status === BlogPost::REVIEW_PENDING => 'pending',
            $model->review_status === BlogPost::REVIEW_RETURNED => 'returned',
            default => 'draft',
        };

        $authors = $teacher ? [] : User::where('role', User::ROLE_TUTOR)
            ->where(fn ($q) => $q->where('is_blocked', false)->orWhereNull('is_blocked'))
            ->orderBy('name')->get(['id', 'name', 'email'])
            ->map(fn (User $u) => ['id' => $u->id, 'name' => (string) $u->name, 'email' => (string) $u->email])->all();

        return view('livewire.cabinet.admin.blog-article', [
            'model' => $model,
            'teacher' => $teacher,
            'editable' => $this->canEdit($model),
            'status' => $status,
            'backUrl' => route($this->route('blog')),
            'facts' => match ($status) {
                'published' => 'На сайте с ' . HumanDate::date($model->published_at) . ' · ' . plural_ru($model->views_count, 'просмотр', 'просмотра', 'просмотров'),
                'scheduled' => 'Выйдет ' . HumanDate::at($model->published_at),
                'pending' => $teacher ? 'На проверке с ' . HumanDate::at($model->submitted_at ?? $model->updated_at) : 'Ждет проверки · автор ' . $model->authorName(),
                'returned' => 'Вернули на доработку',
                'draft' => $teacher ? 'Черновик — виден только вам' : 'Черновик — виден только в админке',
                default => null,
            },
            'authors' => $authors,
            'tagOptions' => \App\Models\BlogTag::orderBy('name')->pluck('name')->all(),
            'dirty' => $this->hash() !== $this->savedHash,
            'siteUrl' => $model && ($model->isPublished() || ! $teacher) ? route('blog.show', $model->slug) : null,
            'slugPreview' => $this->slug !== '' ? $this->slug : ($model?->slug ?: BlogPost::slugFrom($this->title ?: 'statya', $this->postId)),
            'descriptionLength' => mb_strlen(trim($this->excerpt)),
            'submitLabel' => match (true) {
                $teacher => $status === 'pending' ? 'Обновить на проверке' : 'Отправить на проверку',
                $status === 'published', $status === 'scheduled' && $this->when === 'later' => 'Сохранить',
                $this->when === 'later' => 'Запланировать',
                default => 'Опубликовать',
            },
        ]);
    }
}
