<?php

namespace App\Livewire\Cabinet\Admin;

use App\Livewire\Cabinet\Admin\Concerns\AdminScreen;
use App\Models\Announcement;
use App\Models\User;
use App\Services\AnnouncementService;
use App\Support\HumanDate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Новости для учителей и учеников: опубликованные (со статистикой прочтений), запланированные, черновики.
 * Логика — App\Services\AnnouncementService.
 */
#[Layout('components.layouts.cabinet', ['title' => 'Новости', 'active' => 'news'])]
class News extends Component
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
        $query = Announcement::query()->withCount('reads');
        $query = match ($this->tab) {
            'scheduled' => $query->whereNotNull('published_at')->where('published_at', '>', now())->orderBy('published_at'),
            'drafts' => $query->whereNull('published_at')->latest('updated_at'),
            default => $query->published()->orderByDesc('is_pinned')->orderByDesc('published_at'),
        };

        // Адресатов считаем один раз на роль, а не на каждую новость
        $roles = User::query()->whereIn('role', [User::ROLE_TUTOR, User::ROLE_STUDENT])
            ->where(fn ($q) => $q->where('is_blocked', false)->orWhereNull('is_blocked'))
            ->selectRaw('role, count(*) as n')->groupBy('role')->pluck('n', 'role');
        $audienceSize = fn (Announcement $a) => collect($a->roles())->sum(fn ($role) => (int) ($roles[$role] ?? 0));

        $items = $query->get()->map(fn (Announcement $a) => [
            'id' => $a->id,
            'title' => $a->title,
            'important' => $a->is_important,
            'meta' => implode(' · ', array_filter([
                Announcement::AUDIENCES[$a->audience] ?? null,
                match ($this->tab) {
                    'scheduled' => 'выйдет ' . HumanDate::at($a->published_at),
                    'drafts' => 'изменена ' . HumanDate::day($a->updated_at),
                    default => HumanDate::at($a->published_at),
                },
                $a->is_pinned ? 'закреплена' : null,
                $a->send_mail ? 'с письмом' : null,
            ])),
            'reads' => $this->tab === 'published' ? 'прочитали ' . $a->reads_count . ' из ' . $audienceSize($a) : null,
        ]);

        $published = Announcement::published()->count();

        return view('livewire.cabinet.admin.news', [
            'tabs' => self::TABS,
            'items' => $items,
            'facts' => $published ? plural_ru($published, 'новость опубликована', 'новости опубликованы', 'новостей опубликовано') : null,
            'empty' => match ($this->tab) {
                'scheduled' => 'Запланированных новостей нет — выберите время публикации в новости',
                'drafts' => 'Черновиков нет',
                default => 'Пока ничего не опубликовано — напишите первую новость для учителей или учеников',
            },
        ]);
    }
}
