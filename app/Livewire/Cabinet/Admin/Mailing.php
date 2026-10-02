<?php

namespace App\Livewire\Cabinet\Admin;

use App\Livewire\Cabinet\Admin\Concerns\AdminScreen;
use App\Models\MailingCampaign;
use App\Models\MailingDelivery;
use App\Services\AnnouncementService;
use App\Services\HelpCenterService;
use App\Services\MailingService;
use App\Support\HumanDate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

/**
 * Письмо рассылки: новое (/cabinet/admin/mailings/new) или существующее.
 * Черновик и запланированное — редактор (тема, текст, кнопка, списки, пробное письмо, когда отправить);
 * после запуска — итоги: отправлено, открыли, перешли, отписались, ссылки и адресаты.
 */
#[Layout('components.layouts.cabinet', ['title' => 'Письмо', 'active' => 'mailings'])]
class Mailing extends Component
{
    use AdminScreen;
    use WithFileUploads;

    public const IMAGE_DIR = 'mailings';

    private const PAGE = 50;

    public const FILTERS = ['all' => 'Все', 'opened' => 'Открыли', 'clicked' => 'Перешли', 'failed' => 'Ошибки', 'unsubscribed' => 'Отписались'];

    public ?int $campaignId = null;

    public string $subject = '';
    public string $preheader = '';
    public string $body = '';
    public string $buttonText = '';
    public string $buttonUrl = '';
    /** @var array<int, string> */
    public array $listIds = [];

    public string $when = 'now';
    public string $sendAt = '';

    public string $testEmail = '';

    public $image = null;

    public bool $confirmSend = false;
    public bool $confirmStop = false;
    public bool $confirmDelete = false;
    public bool $preview = false;

    /** Адресаты отправленного письма. */
    public string $filter = 'all';
    public string $search = '';
    public int $limit = self::PAGE;

    public function mount(string $campaign): void
    {
        $admin = $this->authorizeAdmin();
        $this->testEmail = (string) $admin->email;

        if ($campaign === 'new') {
            return;
        }

        abort_unless(ctype_digit($campaign), 404);
        $model = MailingCampaign::findOrFail((int) $campaign);

        $this->campaignId = $model->id;
        $this->subject = $model->subject;
        $this->preheader = (string) $model->preheader;
        $this->body = (string) $model->body;
        $this->buttonText = (string) $model->button_text;
        $this->buttonUrl = (string) $model->button_url;
        $this->listIds = $model->lists()->pluck('mailing_lists.id')->map(fn ($id) => (string) $id)->all();
        if ($model->status === MailingCampaign::SCHEDULED) {
            $this->when = 'later';
            $this->sendAt = $model->scheduled_at->format('Y-m-d\TH:i');
        }
    }

    private function model(): ?MailingCampaign
    {
        return $this->campaignId ? MailingCampaign::findOrFail($this->campaignId) : null;
    }

    private function service(): MailingService
    {
        return app(MailingService::class);
    }

    /* ---------- Редактор ---------- */

    protected function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'max:255'],
            'preheader' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string'],
            'buttonText' => ['nullable', 'string', 'max:80', 'required_with:buttonUrl'],
            'buttonUrl' => ['nullable', 'url:http,https', 'max:2048', 'required_with:buttonText'],
        ];
    }

    protected function messages(): array
    {
        return [
            'subject.required' => 'Напишите тему письма',
            'buttonText.required_with' => 'Напишите, что на кнопке',
            'buttonUrl.required_with' => 'Вставьте ссылку для кнопки',
            'buttonUrl.url' => 'Ссылка должна начинаться с https://',
        ];
    }

    private function save(): MailingCampaign
    {
        $this->authorizeAdmin();
        $this->validate();

        $model = $this->model();
        if ($model && ! $model->isEditable()) {
            abort(409);
        }

        $campaign = $this->service()->save($model, [
            'subject' => $this->subject,
            'preheader' => $this->preheader,
            'body' => $this->body,
            'button_text' => $this->buttonText,
            'button_url' => $this->buttonUrl,
        ], array_map('intval', $this->listIds), auth()->user());
        $this->campaignId = $campaign->id;

        return $campaign;
    }

    private function go(string $toast): void
    {
        session()->flash('toast', $toast);
        $this->redirectRoute('cabinet.admin.mailing', ['campaign' => $this->campaignId]);
    }

    public function saveDraft(): void
    {
        $campaign = $this->save();
        if ($campaign->status === MailingCampaign::SCHEDULED) {
            $this->service()->unschedule($campaign);
        }
        $this->go('Черновик сохранён');
    }

    /** Главная кнопка: отправить сразу (с подтверждением) или запланировать. */
    public function submit(): void
    {
        $this->validate();
        if (! $this->recipientCount()) {
            throw ValidationException::withMessages(['listIds' => 'Выберите список, в котором есть адреса']);
        }

        if ($this->when === 'later') {
            $at = AnnouncementService::parseTime($this->sendAt);
            if (! $at) {
                throw ValidationException::withMessages(['sendAt' => 'Выберите дату и время отправки']);
            }
            if ($at->isPast()) {
                throw ValidationException::withMessages(['sendAt' => 'Это время уже прошло — выберите позже или отправьте сразу']);
            }
            $campaign = $this->save();
            $this->service()->schedule($campaign, $at);
            $this->go('Письмо уйдёт ' . HumanDate::at($at));

            return;
        }

        $this->confirmSend = true;
    }

    public function send(): void
    {
        $this->confirmSend = false;
        $campaign = $this->save();
        $count = $this->service()->start($campaign);
        $this->go($count ? 'Рассылка запущена — первые письма уйдут в течение минуты' : 'Некому отправлять — в выбранных списках нет адресов');
    }

    /** Запланированное — обратно в черновик. */
    public function unschedule(): void
    {
        $this->authorizeAdmin();
        $model = $this->model();
        abort_unless($model && $model->status === MailingCampaign::SCHEDULED, 409);
        $this->service()->unschedule($model);
        $this->when = 'now';
        $this->sendAt = '';
        $this->dispatch('toast', message: 'Отправка отменена — письмо снова черновик');
    }

    public function closeSend(): void
    {
        $this->confirmSend = false;
    }

    public function sendTest(): void
    {
        $this->validate(['testEmail' => ['required', 'email']], ['testEmail.required' => 'Укажите почту', 'testEmail.email' => 'Проверьте адрес почты']);
        $campaign = $this->model();
        if (! $campaign || $campaign->isEditable()) {
            $campaign = $this->save();
        }

        try {
            $this->service()->sendTest($campaign, $this->testEmail);
        } catch (TransportExceptionInterface $e) {
            report($e);
            $this->dispatch('toast', message: 'Письмо не отправлено: ' . MailingService::errorText($e), tone: 'danger');

            return;
        }

        $this->dispatch('toast', message: 'Пробное письмо отправлено на ' . $this->testEmail);
    }

    public function openPreview(): void
    {
        $this->preview = true;
    }

    public function closePreview(): void
    {
        $this->preview = false;
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

    /* ---------- После запуска ---------- */

    public function askStop(): void
    {
        $this->confirmStop = true;
    }

    public function closeStop(): void
    {
        $this->confirmStop = false;
    }

    public function stop(): void
    {
        $this->authorizeAdmin();
        $model = $this->model();
        abort_unless($model && $model->status === MailingCampaign::SENDING, 409);
        $this->service()->stop($model);
        $this->confirmStop = false;
        $this->dispatch('toast', message: 'Отправка остановлена — остальные письма не уйдут');
    }

    public function duplicate(): void
    {
        $this->authorizeAdmin();
        $copy = $this->service()->duplicate($this->model(), auth()->user());
        session()->flash('toast', 'Копия письма — черновик');
        $this->redirectRoute('cabinet.admin.mailing', ['campaign' => $copy->id]);
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
        $this->service()->delete($this->model());
        session()->flash('toast', 'Письмо удалено');
        $this->redirectRoute('cabinet.admin.mailings');
    }

    public function updatedFilter(): void
    {
        $this->limit = self::PAGE;
    }

    public function updatedSearch(): void
    {
        $this->limit = self::PAGE;
    }

    public function more(): void
    {
        $this->limit += self::PAGE;
    }

    /* ---------- Вид ---------- */

    private function recipientCount(): int
    {
        return $this->service()->recipients(array_map('intval', $this->listIds))->count();
    }

    /** Сколько займёт отправка: «около 17 минут». */
    private function duration(int $count): string
    {
        $minutes = (int) ceil($count / max(1, (int) config('mail.newsletter.per_minute', 20)));
        if ($minutes < 60) {
            return 'около ' . plural_ru(max(1, $minutes), 'минуты', 'минут', 'минут');
        }
        $hours = round($minutes / 60, 1);

        return 'около ' . str_replace('.', ',', (string) $hours) . ' ч';
    }

    public function render()
    {
        $model = $this->model();
        $status = $model?->status ?? 'new';
        $editable = ! $model || $model->isEditable();

        $data = [
            'model' => $model,
            'status' => $status,
            'editable' => $editable,
            'heading' => trim($this->subject) !== '' ? trim($this->subject) : ($model ? 'Без темы' : 'Новое письмо'),
            'previewHtml' => $this->preview ? $this->previewHtml($model) : null,
        ];

        if ($editable) {
            $count = $this->recipientCount();

            return view('livewire.cabinet.admin.mailing', $data + [
                'facts' => match ($status) {
                    MailingCampaign::SCHEDULED => 'Уйдёт ' . HumanDate::at($model->scheduled_at),
                    MailingCampaign::DRAFT => 'Черновик',
                    default => null,
                },
                'lists' => $this->service()->lists(),
                'count' => $count,
                'duration' => $this->duration($count),
                'perMinute' => (int) config('mail.newsletter.per_minute', 20),
                'submitLabel' => $this->when === 'later' ? ($status === MailingCampaign::SCHEDULED ? 'Сохранить' : 'Запланировать') : 'Отправить',
            ]);
        }

        $stats = $this->service()->stats($model);
        $deliveries = $this->deliveries($model);

        return view('livewire.cabinet.admin.mailing', $data + [
            'facts' => match ($status) {
                MailingCampaign::SENDING => 'Отправляется · ' . $stats['sent'] . ' из ' . $stats['total'] . ' · осталось ' . $this->duration($stats['queued']),
                MailingCampaign::STOPPED => 'Остановлено ' . HumanDate::at($model->finished_at ?? $model->updated_at),
                default => 'Отправлено ' . HumanDate::at($model->finished_at ?? $model->started_at ?? $model->updated_at),
            },
            'stats' => $stats,
            'percent' => fn (int $n) => $stats['sent'] ? (int) round($n * 100 / $stats['sent']) . '%' : '',
            'links' => $model->links()->orderByDesc('clicks')->orderBy('id')->get(),
            'listNames' => $model->lists()->orderBy('name')->pluck('name'),
            'deliveries' => $deliveries['items'],
            'more' => $deliveries['more'],
            'filters' => self::FILTERS,
        ]);
    }

    private function previewHtml(?MailingCampaign $model): string
    {
        // Черновик показываем как есть на экране, даже несохранённый
        $draft = new MailingCampaign([
            'subject' => $this->subject,
            'preheader' => $this->preheader,
            'body' => \App\Support\RichText::clean($this->body),
            'button_text' => $this->buttonText && $this->buttonUrl ? $this->buttonText : null,
            'button_url' => $this->buttonText && $this->buttonUrl ? $this->buttonUrl : null,
        ]);
        $campaign = $model && ! $model->isEditable() ? $model : $draft;
        $contact = $this->service()->recipients(array_map('intval', $this->listIds))->orderBy('id')->first();

        return $this->service()->previewHtml($campaign, $contact);
    }

    private function deliveries(MailingCampaign $model): array
    {
        $query = $model->deliveries()->with('contact:id,name,city')
            ->when($this->filter === 'opened', fn ($q) => $q->whereNotNull('opened_at'))
            ->when($this->filter === 'clicked', fn ($q) => $q->whereNotNull('clicked_at'))
            ->when($this->filter === 'failed', fn ($q) => $q->where('status', MailingDelivery::FAILED))
            ->when($this->filter === 'unsubscribed', fn ($q) => $q->whereNotNull('unsubscribed_at'))
            ->when(trim($this->search) !== '', fn ($q) => $q->where('email', 'like', '%' . addcslashes(trim($this->search), '%_\\') . '%'));

        $found = (clone $query)->count();
        $items = $query->orderBy('id')->limit($this->limit)->get()->map(fn (MailingDelivery $d) => [
            'id' => $d->id,
            'email' => $d->email,
            'sub' => implode(' · ', array_filter([$d->contact?->name, $d->contact?->city])),
            'status' => match (true) {
                $d->status === MailingDelivery::FAILED => ['tone' => 'danger', 'text' => 'Не доставлено', 'note' => $d->error],
                (bool) $d->unsubscribed_at => ['tone' => 'neutral', 'text' => 'Отписался', 'note' => null],
                (bool) $d->clicked_at => ['tone' => null, 'text' => 'перешёл ' . HumanDate::at($d->clicked_at), 'note' => null],
                (bool) $d->opened_at => ['tone' => null, 'text' => 'открыл ' . HumanDate::at($d->opened_at), 'note' => null],
                $d->status === MailingDelivery::QUEUED => ['tone' => null, 'text' => 'ждёт отправки', 'note' => null],
                $d->status === MailingDelivery::SKIPPED => ['tone' => null, 'text' => 'не отправлено', 'note' => null],
                default => ['tone' => null, 'text' => null, 'note' => null],
            },
        ]);

        return ['items' => $items, 'more' => max(0, $found - $this->limit)];
    }
}
