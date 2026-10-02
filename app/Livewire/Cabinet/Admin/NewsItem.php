<?php

namespace App\Livewire\Cabinet\Admin;

use App\Livewire\Cabinet\Admin\Concerns\AdminScreen;
use App\Livewire\Cabinet\Admin\Concerns\EditorVideo;
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
 * Экран как у статьи блога: чистый лист с блочным редактором (видео и GIF — MediaService), настройки — в панели справа.
 * Новая и черновик сохраняются сами (autosave), запланированная и опубликованная — только кнопкой.
 * Уведомление уходит один раз, когда новость выходит; после этого «Кому» и «Письмо на почту» не меняются.
 */
#[Layout('components.layouts.cabinet', ['title' => 'Новость', 'active' => 'news'])]
class NewsItem extends Component
{
    use AdminScreen;
    use EditorVideo;
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

    /** Автосохранение: что уже в базе (хеш полей) и во сколько сохранили. */
    public string $savedHash = '';
    public ?string $savedAt = null;

    public bool $confirmPublish = false;
    public bool $confirmDelete = false;

    public function mount(string $announcement): void
    {
        $this->authorizeAdmin();

        if ($announcement === 'new') {
            $this->savedHash = $this->hash();

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
        $this->savedHash = $this->hash();
    }

    private function hash(): string
    {
        return md5(json_encode([$this->title, $this->body, $this->audience, $this->important, $this->pinned, $this->sendMail]));
    }

    /** Раз в 5 секунд (wire:poll): новую — создаем черновиком, черновик — обновляем. */
    public function autosave(): void
    {
        $model = $this->model();
        if (($model && ($model->isPublished() || $model->isScheduled())) || $this->hash() === $this->savedHash) {
            return;
        }
        if (! $model && trim($this->title) === '' && trim(strip_tags($this->body, '<img><video>')) === '') {
            return;
        }
        if (mb_strlen($this->title) > 255) {
            return; // ошибку покажем при нажатии кнопки
        }

        $announcement = $this->service()->save($model, $this->data(null), auth()->user());
        if (! $model) {
            // Новая новость получила адрес — меняем его в строке браузера без перезагрузки
            $this->announcementId = $announcement->id;
            $this->js('history.replaceState(null, "", ' . json_encode(route('cabinet.admin.news-item', ['announcement' => $announcement->id])) . ')');
        }
        $this->savedHash = $this->hash();
        $this->savedAt = now()->format('H:i');
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
        // Поле времени — в панели «Настройки»: открываем ее, чтобы ошибку было видно
        if (! $at || $at->isPast()) {
            $this->dispatch('news-settings');
        }
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
        $this->ensureMediaReady($this->body);
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

    /** Из списка под главной кнопкой: опубликовать сразу (с подтверждением), даже если было выбрано «По времени». */
    public function publishNow(): void
    {
        $this->when = 'now';
        $this->submit();
    }

    /** Из списка под главной кнопкой: выбрать время — в панели настроек. */
    public function planLater(): void
    {
        $this->when = 'later';
        $this->dispatch('news-settings');
    }

    public function publish(): void
    {
        $this->validate();
        $this->ensureMediaReady($this->body);
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

    protected function mediaDir(): string
    {
        return self::IMAGE_DIR;
    }

    /**
     * Картинка в текст: сжимаем до 1600 px по ширине в WebP и возвращаем адрес.
     * GIF — в беззвучное зацикленное видео (в разы легче); редактор получает массив и вставляет видео.
     */
    public function storeImage(): string|array|null
    {
        try {
            $this->validateOnly('image', ['image' => ['required', 'image', 'max:20480']], [
                'image.image' => 'Нужна картинка PNG, JPG, GIF или WebP',
                'image.max' => 'Картинка больше 20 МБ',
            ]);
        } catch (ValidationException $e) {
            $this->image = null;
            $this->dispatch('toast', message: collect($e->errors())->flatten()->first() ?: 'Картинку не удалось загрузить', tone: 'danger');

            return null;
        }

        if ($video = $this->gifAsVideo($this->image)) {
            $this->image = null;

            return $video;
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
