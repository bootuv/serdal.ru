<?php

namespace App\Livewire\Cabinet\Admin;

use App\Livewire\Cabinet\Admin\Concerns\AdminScreen;
use App\Models\Announcement;
use App\Services\AnnouncementService;
use App\Services\HelpCenterService;
use App\Support\HumanDate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Новость: новая (/cabinet/admin/news/new) или существующая. Черновик → публикация сразу или по времени.
 * Уведомление уходит один раз, когда новость выходит; после этого «Кому» и «Письмо на почту» не меняются.
 */
#[Layout('components.layouts.cabinet', ['title' => 'Новость', 'active' => 'news'])]
class NewsItem extends Component
{
    use AdminScreen;
    use WithFileUploads;

    public const IMAGE_DIR = 'news';

    public ?int $announcementId = null;

    public string $title = '';
    public string $body = '';
    public string $audience = Announcement::AUDIENCE_TEACHERS;
    public bool $important = false;
    public bool $pinned = false;
    public bool $sendMail = false;

    /** Когда публиковать: now — сразу, later — в publishAt («2026-10-05T10:00»). */
    public string $when = 'now';
    public string $publishAt = '';

    /** Картинка для текста (временная загрузка, см. x-ui.editor-media). */
    public $image = null;

    public bool $confirmPublish = false;
    public bool $confirmDelete = false;

    public function mount(string $announcement): void
    {
        $this->authorizeAdmin();

        if ($announcement === 'new') {
            return;
        }

        abort_unless(ctype_digit($announcement), 404);
        $model = Announcement::findOrFail((int) $announcement);

        $this->announcementId = $model->id;
        $this->title = $model->title;
        $this->body = (string) $model->body;
        $this->audience = $model->audience;
        $this->important = $model->is_important;
        $this->pinned = $model->is_pinned;
        $this->sendMail = $model->send_mail;
        if ($model->isScheduled()) {
            $this->when = 'later';
            $this->publishAt = $model->published_at->format('Y-m-d\TH:i');
        }
    }

    private function model(): ?Announcement
    {
        return $this->announcementId ? Announcement::findOrFail($this->announcementId) : null;
    }

    private function service(): AnnouncementService
    {
        return app(AnnouncementService::class);
    }

    protected function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'body' => ['nullable', 'string'],
            'audience' => ['required', Rule::in(array_keys(Announcement::AUDIENCES))],
        ];
    }

    protected function messages(): array
    {
        return ['title.required' => 'Введите заголовок'];
    }

    private function data(?\Carbon\CarbonInterface $publishedAt): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            'audience' => $this->audience,
            'is_important' => $this->important,
            'is_pinned' => $this->pinned,
            'send_mail' => $this->sendMail,
            'published_at' => $publishedAt,
        ];
    }

    /** Время из поля «Когда»: обязательно и в будущем. */
    private function scheduledTime(): \Carbon\CarbonInterface
    {
        $at = AnnouncementService::parseTime($this->publishAt);
        if (! $at) {
            throw ValidationException::withMessages(['publishAt' => 'Выберите дату и время публикации']);
        }
        if ($at->isPast()) {
            throw ValidationException::withMessages(['publishAt' => 'Это время уже прошло — выберите позже или опубликуйте сразу']);
        }

        return $at;
    }

    private function saveAndGo(?\Carbon\CarbonInterface $publishedAt, string $toast): void
    {
        $existing = $this->model();
        $announcement = $this->service()->save($existing, $this->data($publishedAt), auth()->user());

        session()->flash('toast', $toast);
        $this->redirectRoute('cabinet.admin.news-item', ['announcement' => $announcement->id]);
    }

    /** Черновик: не видна никому. */
    public function saveDraft(): void
    {
        $this->validate();
        $this->saveAndGo(null, 'Черновик сохранён');
    }

    /** Главная кнопка: для черновика — опубликовать (сразу — с подтверждением) или запланировать; для остальных — сохранить. */
    public function submit(): void
    {
        $this->validate();
        $model = $this->model();

        if ($model?->isPublished()) {
            $this->saveAndGo($model->published_at, 'Новость сохранена');

            return;
        }

        if ($this->when === 'later') {
            $at = $this->scheduledTime();
            $this->saveAndGo($at, 'Новость выйдет ' . HumanDate::at($at));

            return;
        }

        $this->confirmPublish = true;
    }

    public function publish(): void
    {
        $this->validate();
        $this->confirmPublish = false;
        $this->saveAndGo(now(), 'Новость опубликована');
    }

    public function closePublish(): void
    {
        $this->confirmPublish = false;
    }

    public function unpublish(): void
    {
        $model = $this->model();
        abort_unless($model, 404);
        $this->service()->unpublish($model);

        $this->when = 'now';
        $this->publishAt = '';
        $this->confirmDelete = false;
        $this->dispatch('toast', message: 'Новость снята с публикации — теперь это черновик');
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
        $model = $this->model();
        abort_unless($model, 404);
        $this->service()->delete($model);

        session()->flash('toast', 'Новость удалена');
        $this->redirectRoute('cabinet.admin.news');
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

        $url = app(HelpCenterService::class)->storeImage($this->image, self::IMAGE_DIR);
        $this->image = null;

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

        $facts = match ($status) {
            'published' => 'Опубликована ' . HumanDate::at($model->published_at),
            'scheduled' => 'Выйдет ' . HumanDate::at($model->published_at),
            'draft' => 'Черновик — видна только в админке',
            default => null,
        };

        // Сколько человек получат уведомление — для окна подтверждения и подписи «Кому»
        $preview = new Announcement(['audience' => $this->audience]);
        $recipients = $this->service()->recipients($model?->notified_at ? $model : $preview)->count();
        $who = match ($model?->notified_at ? $model->audience : $this->audience) {
            Announcement::AUDIENCE_STUDENTS => plural_ru($recipients, 'ученик', 'ученика', 'учеников'),
            Announcement::AUDIENCE_ALL => plural_ru($recipients, 'человек', 'человека', 'человек') . ' — учителя и ученики',
            default => plural_ru($recipients, 'учитель', 'учителя', 'учителей'),
        };

        $heading = trim($this->title) !== '' ? trim($this->title) : ($model ? 'Без заголовка' : 'Новая новость');

        return view('livewire.cabinet.admin.news-item', [
            'model' => $model,
            'status' => $status,
            'heading' => $heading,
            'facts' => $facts,
            'notified' => (bool) $model?->notified_at,
            'audiences' => Announcement::AUDIENCES,
            'who' => $who,
            'stats' => $status === 'published' ? $this->service()->stats($model) : null,
            'submitLabel' => match (true) {
                $status === 'published', $status === 'scheduled' && $this->when === 'later' => 'Сохранить',
                $this->when === 'later' => 'Запланировать',
                default => 'Опубликовать',
            },
        ]);
    }
}
