<?php

namespace App\Livewire\Cabinet\Admin;

use App\Livewire\Cabinet\Admin\Concerns\AdminScreen;
use App\Models\MailingContact;
use App\Models\MailingList as MailingListModel;
use App\Models\User;
use App\Services\MailingService;
use App\Support\MailingImport;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Список адресов рассылки: адреса, поиск, загрузка из файла (.xlsx, CSV) или вставкой из таблицы.
 * Логика — App\Services\MailingService, разбор — App\Support\MailingImport.
 */
#[Layout('components.layouts.cabinet', ['title' => 'Список адресов', 'active' => 'mailings'])]
class MailingList extends Component
{
    use AdminScreen;
    use WithFileUploads;

    private const PAGE = 50;

    public int $listId;

    public string $search = '';
    public string $filter = 'all';
    public int $limit = self::PAGE;

    public bool $importing = false;
    public string $importMode = 'file';
    public $file = null;
    public string $pasted = '';
    /** Из пользователей: tutors | students | people; для учителей — фильтр по тарифу; people — выбранные id. */
    public string $userGroup = 'tutors';
    public string $tariff = 'all';
    /** @var array<int, int> */
    public array $userIds = [];

    /** Итог загрузки, когда есть строки без почты: показываем их в окне. */
    public ?array $importResult = null;

    public bool $renaming = false;
    public string $name = '';

    public bool $confirmDelete = false;

    public function mount(MailingListModel $list): void
    {
        $this->authorizeAdmin();
        $this->listId = $list->id;
    }

    private function list(): MailingListModel
    {
        return MailingListModel::findOrFail($this->listId);
    }

    private function service(): MailingService
    {
        return app(MailingService::class);
    }

    public function updatedSearch(): void
    {
        $this->limit = self::PAGE;
    }

    public function updatedFilter(): void
    {
        $this->limit = self::PAGE;
    }

    public function more(): void
    {
        $this->limit += self::PAGE;
    }

    /* ---------- Загрузка ---------- */

    public function openImport(): void
    {
        $this->reset(['file', 'pasted', 'importResult', 'userIds']);
        $this->resetValidation();
        $this->importing = true;
    }

    public function closeImport(): void
    {
        $this->importing = false;
        $this->reset(['file', 'pasted', 'importResult']);
    }

    public function import(): void
    {
        $this->authorizeAdmin();

        if ($this->importMode === 'users') {
            $users = $this->service()->users($this->userGroup, $this->tariff, $this->userIds);
            if (! (clone $users)->exists()) {
                throw ValidationException::withMessages(['userIds' => $this->userGroup === 'people' ? 'Выберите хотя бы одного человека' : 'Таких пользователей нет']);
            }
            $result = $this->service()->importUsers($this->list(), $users);
            $this->reset(['userIds']);
            $this->importing = false;
            $this->dispatch('toast', message: $this->summary($result));

            return;
        }

        if ($this->importMode === 'paste') {
            $this->validate(['pasted' => ['required', 'string', 'max:2000000']], ['pasted.required' => 'Вставьте адреса — каждый с новой строки']);
            $parsed = MailingImport::fromText($this->pasted);
        } else {
            $this->validate(
                ['file' => ['required', 'file', 'max:10240', 'extensions:xlsx,xls,csv,txt']],
                ['file.required' => 'Выберите файл', 'file.max' => 'Файл больше 10 МБ', 'file.extensions' => 'Нужен файл .xlsx или .csv'],
            );
            try {
                $parsed = MailingImport::fromFile($this->file->getRealPath(), $this->file->getClientOriginalExtension());
            } catch (\InvalidArgumentException $e) {
                throw ValidationException::withMessages(['file' => $e->getMessage()]);
            }
        }

        if (! $parsed['contacts']) {
            $field = $this->importMode === 'paste' ? 'pasted' : 'file';
            throw ValidationException::withMessages([$field => 'Не нашли ни одного адреса почты — проверьте, что в таблице есть колонка с почтой']);
        }

        $result = $this->service()->import($this->list(), $parsed);
        $this->reset(['file', 'pasted']);

        $summary = $this->summary($result);
        if ($result['invalid']) {
            $this->importResult = ['summary' => $summary, 'invalid' => array_slice($result['invalid'], 0, 20), 'more' => max(0, count($result['invalid']) - 20)];

            return;
        }

        $this->importing = false;
        $this->dispatch('toast', message: $summary);
    }

    /** Выбор людей поштучно: повторное нажатие снимает отметку. */
    public function pickUser(int $id): void
    {
        $this->userIds = in_array($id, $this->userIds, true)
            ? array_values(array_diff($this->userIds, [$id]))
            : [...$this->userIds, $id];
        $this->resetValidation('userIds');
    }

    public function dropUser(int $id): void
    {
        $this->userIds = array_values(array_diff($this->userIds, [$id]));
    }

    private function summary(array $r): string
    {
        $parts = [$r['added'] ? 'Добавлено ' . plural_ru($r['added'], 'адрес', 'адреса', 'адресов') : 'Новых адресов нет'];
        if ($r['existing']) {
            $parts[] = plural_ru($r['existing'], 'уже был в списке', 'уже были в списке', 'уже были в списке');
        }
        if ($r['unsubscribed']) {
            $parts[] = plural_ru($r['unsubscribed'], 'адрес отписался', 'адреса отписались', 'адресов отписались') . ' раньше — им не напишем';
        }

        return implode(', ', $parts);
    }

    /* ---------- Список ---------- */

    public function remove(int $contactId): void
    {
        $this->authorizeAdmin();
        $this->service()->removeContacts($this->list(), [$contactId]);
        $this->dispatch('toast', message: 'Адрес убран из списка');
    }

    public function rename(): void
    {
        $this->name = $this->list()->name;
        $this->resetValidation();
        $this->renaming = true;
    }

    public function closeRename(): void
    {
        $this->renaming = false;
    }

    public function saveName(): void
    {
        $this->authorizeAdmin();
        $this->validate(['name' => ['required', 'string', 'max:255']], ['name.required' => 'Назовите список']);
        $this->service()->renameList($this->list(), $this->name);
        $this->renaming = false;
        $this->dispatch('toast', message: 'Список переименован');
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
        $this->service()->deleteList($this->list());
        session()->flash('toast', 'Список удалён');
        $this->redirectRoute('cabinet.admin.mailings', ['tab' => 'lists']);
    }

    public function render()
    {
        $list = $this->list();
        $total = $list->contacts()->count();
        $unsubscribed = $list->contacts()->whereNotNull('unsubscribed_at')->count();

        $query = $list->contacts()
            ->when($this->filter === 'unsubscribed', fn ($q) => $q->whereNotNull('unsubscribed_at'))
            ->when(trim($this->search) !== '', function ($q) {
                $s = '%' . addcslashes(trim($this->search), '%_\\') . '%';
                $q->where(fn ($q) => $q->where('email', 'like', $s)->orWhere('name', 'like', $s)->orWhere('city', 'like', $s));
            });
        $found = (clone $query)->count();
        $roles = [User::ROLE_TUTOR => 'учитель', User::ROLE_STUDENT => 'ученик'];
        $contacts = $query->with('user:id,role')->orderBy('mailing_contacts.email')->limit($this->limit)->get()->map(fn (MailingContact $c) => [
            'id' => $c->id,
            'email' => $c->email,
            'sub' => implode(' · ', array_filter([$c->name, $c->city, $roles[$c->user?->role] ?? null])),
            'unsubscribed' => (bool) $c->unsubscribed_at,
        ]);

        return view('livewire.cabinet.admin.mailing-list', [
            'list' => $list,
            'facts' => implode(' · ', array_filter([
                $total ? plural_ru($total, 'адрес', 'адреса', 'адресов') : 'Адресов пока нет',
                $unsubscribed ? plural_ru($unsubscribed, 'отписался', 'отписались', 'отписались') : null,
            ])),
            'total' => $total,
            'hasUnsubscribed' => $unsubscribed > 0,
            'contacts' => $contacts,
            'more' => max(0, $found - $this->limit),
            'users' => $this->importing && $this->importMode === 'users' ? $this->usersView() : null,
        ]);
    }

    /** Окно «Добавить адреса» → «Пользователи»: сколько добавим, люди для выбора поштучно. */
    private function usersView(): array
    {
        $people = $this->userGroup === 'people'
            ? $this->service()->people()->orderBy('name')->get(['id', 'name', 'email', 'role'])
            : collect();

        return [
            'groups' => MailingService::USER_GROUPS,
            'tariffs' => MailingService::TARIFF_FILTERS,
            'count' => $this->service()->users($this->userGroup, $this->tariff, $this->userIds)->count(),
            'people' => $people->map(fn (User $u) => ['id' => $u->id, 'name' => (string) $u->name, 'email' => (string) $u->email])->all(),
            'chosen' => $people->whereIn('id', $this->userIds)->map(fn (User $u) => ['id' => $u->id, 'name' => (string) $u->name])->values()->all(),
        ];
    }
}
