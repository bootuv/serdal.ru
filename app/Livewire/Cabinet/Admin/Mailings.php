<?php

namespace App\Livewire\Cabinet\Admin;

use App\Livewire\Cabinet\Admin\Concerns\AdminScreen;
use App\Models\MailingCampaign;
use App\Models\MailingDelivery;
use App\Services\MailingService;
use App\Support\HumanDate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Рассылки на внешние адреса (школы): письма и списки адресов. Логика — App\Services\MailingService.
 */
#[Layout('components.layouts.cabinet', ['title' => 'Рассылки', 'active' => 'mailings'])]
class Mailings extends Component
{
    use AdminScreen;

    private const TABS = ['letters' => 'Письма', 'lists' => 'Списки'];

    #[Url(except: 'letters')]
    public string $tab = 'letters';

    public bool $creatingList = false;
    public string $listName = '';

    public function mount(): void
    {
        $this->authorizeAdmin();
        $this->updatedTab();
    }

    public function updatedTab(): void
    {
        if (! array_key_exists($this->tab, self::TABS)) {
            $this->tab = 'letters';
        }
    }

    public function newList(): void
    {
        $this->listName = '';
        $this->resetValidation();
        $this->creatingList = true;
    }

    public function closeList(): void
    {
        $this->creatingList = false;
    }

    public function createList(MailingService $service): void
    {
        $this->authorizeAdmin();
        $this->validate(['listName' => ['required', 'string', 'max:255']], ['listName.required' => 'Назовите список']);

        $list = $service->createList($this->listName);

        session()->flash('toast', 'Список создан — добавьте в него адреса');
        $this->redirectRoute('cabinet.admin.mailing-list', ['list' => $list->id]);
    }

    public function render(MailingService $service)
    {
        $letters = [];
        if ($this->tab === 'letters') {
            $campaigns = MailingCampaign::query()
                ->withCount([
                    'deliveries as total',
                    'deliveries as sent' => fn ($q) => $q->where('status', MailingDelivery::SENT),
                    'deliveries as opened' => fn ($q) => $q->whereNotNull('opened_at'),
                ])
                ->orderByRaw("case status when 'sending' then 0 when 'scheduled' then 1 when 'draft' then 2 else 3 end")
                ->orderByDesc('updated_at')
                ->get();

            $letters = $campaigns->map(fn (MailingCampaign $c) => [
                'id' => $c->id,
                'subject' => $c->subject ?: 'Без темы',
                'meta' => match ($c->status) {
                    MailingCampaign::DRAFT => 'Черновик · изменено ' . HumanDate::day($c->updated_at),
                    MailingCampaign::SCHEDULED => 'Уйдёт ' . HumanDate::at($c->scheduled_at),
                    MailingCampaign::SENDING => 'Отправляется · ' . $c->sent . ' из ' . $c->total,
                    MailingCampaign::STOPPED => 'Остановлено ' . HumanDate::at($c->finished_at ?? $c->updated_at) . ' · отправлено ' . $c->sent . ' из ' . $c->total,
                    default => 'Отправлено ' . HumanDate::at($c->finished_at ?? $c->started_at ?? $c->updated_at) . ' · ' . plural_ru($c->sent, 'адрес', 'адреса', 'адресов'),
                },
                'opened' => in_array($c->status, [MailingCampaign::SENT, MailingCampaign::STOPPED, MailingCampaign::SENDING], true) && $c->sent
                    ? 'открыли ' . (int) round($c->opened * 100 / $c->sent) . '%'
                    : null,
                'problem' => $c->status === MailingCampaign::SENDING && $c->error,
            ]);
        }

        $lists = $this->tab === 'lists' ? $service->lists()->map(fn ($l) => [
            'id' => $l->id,
            'name' => $l->name,
            'meta' => implode(' · ', array_filter([
                plural_ru($l->total, 'адрес', 'адреса', 'адресов'),
                $l->total > $l->active ? plural_ru($l->total - $l->active, 'отписался', 'отписались', 'отписались') : null,
            ])),
        ]) : [];

        return view('livewire.cabinet.admin.mailings', [
            'tabs' => self::TABS,
            'letters' => $letters,
            'lists' => $lists,
        ]);
    }
}
