<?php

namespace App\Livewire\Cabinet\Admin;

use App\Livewire\Cabinet\Admin\Concerns\AdminScreen;
use App\Models\HelpArticle;
use App\Models\HelpCategory;
use App\Services\HelpCenterService;
use App\Support\HelpIcons;
use App\Support\HumanDate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * База знаний: категории группами со статьями, порядок перетаскиванием, черновики.
 * Макет: AdminHelp. Логика — App\Services\HelpCenterService.
 */
#[Layout('components.layouts.cabinet', ['title' => 'База знаний', 'active' => 'help'])]
class Help extends Component
{
    use AdminScreen;

    /** Вкладка: students | tutors (как слаги публичной справки). */
    #[Url(except: 'students')]
    public string $tab = 'students';

    #[Url(except: '')]
    public string $q = '';

    // Окно категории
    public bool $catOpen = false;
    public ?int $catId = null;
    public string $catAudience = HelpCategory::AUDIENCE_STUDENT;
    public string $catName = '';
    public string $catDescription = '';
    public ?string $catIcon = HelpIcons::DEFAULT;
    public bool $catShow = true;

    // Удаление категории
    public ?int $deleteId = null;

    public function mount(): void
    {
        $this->authorizeAdmin();

        if (! in_array($this->tab, HelpCategory::AUDIENCE_SLUGS, true)) {
            $this->tab = 'students';
        }
    }

    private function audience(): string
    {
        return HelpCategory::audienceFromSlug($this->tab) ?? HelpCategory::AUDIENCE_STUDENT;
    }

    private function service(): HelpCenterService
    {
        return app(HelpCenterService::class);
    }

    public function updatedTab(): void
    {
        if (! in_array($this->tab, HelpCategory::AUDIENCE_SLUGS, true)) {
            $this->tab = 'students';
        }
    }

    public function clearSearch(): void
    {
        $this->q = '';
    }

    /* ---------- Категории ---------- */

    public function newCategory(): void
    {
        $this->resetValidation();
        $this->catId = null;
        $this->catAudience = $this->audience();
        $this->catName = '';
        $this->catDescription = '';
        $this->catIcon = HelpIcons::DEFAULT;
        $this->catShow = true;
        $this->catOpen = true;
    }

    public function editCategory(int $id): void
    {
        $category = HelpCategory::findOrFail($id);

        $this->resetValidation();
        $this->catId = $category->id;
        $this->catAudience = $category->audience;
        $this->catName = $category->name;
        $this->catDescription = (string) $category->description;
        $this->catIcon = $category->icon;
        $this->catShow = (bool) $category->is_published;
        $this->catOpen = true;
    }

    public function pickIcon(string $key): void
    {
        if (HelpIcons::isKey($key)) {
            $this->catIcon = $key;
        }
    }

    public function closeCategory(): void
    {
        $this->catOpen = false;
        $this->resetValidation();
    }

    public function saveCategory(): void
    {
        $this->validate([
            'catAudience' => ['required', Rule::in(array_keys(HelpCategory::AUDIENCES))],
            'catName' => ['required', 'string', 'max:255'],
            'catDescription' => ['nullable', 'string', 'max:1000'],
        ], ['catName.required' => 'Введите название']);

        $category = $this->catId ? HelpCategory::findOrFail($this->catId) : null;
        $category = $this->service()->saveCategory($category, [
            'audience' => $this->catAudience,
            'name' => $this->catName,
            'description' => $this->catDescription,
            'icon' => $this->catIcon,
            'is_published' => $this->catShow,
        ]);

        $this->catOpen = false;
        $this->tab = HelpCategory::AUDIENCE_SLUGS[$category->audience];
        if (! $this->catId) {
            $this->q = '';
        }
        $this->dispatch('toast', message: $this->catId ? 'Категория сохранена' : 'Категория создана');
    }

    public function toggleCategory(int $id): void
    {
        $category = HelpCategory::findOrFail($id);
        $category->update(['is_published' => ! $category->is_published]);

        $this->dispatch('toast', message: $category->is_published ? 'Категория снова на сайте' : 'Категория скрыта с сайта');
    }

    public function askDeleteCategory(int $id): void
    {
        $this->deleteId = HelpCategory::findOrFail($id)->id;
    }

    public function closeDelete(): void
    {
        $this->deleteId = null;
    }

    /** «Скройте категорию с сайта» вместо удаления. */
    public function hideInstead(): void
    {
        if ($category = HelpCategory::find($this->deleteId)) {
            $category->update(['is_published' => false]);
            $this->dispatch('toast', message: 'Категория скрыта с сайта');
        }
        $this->deleteId = null;
    }

    public function deleteCategory(): void
    {
        if ($category = HelpCategory::find($this->deleteId)) {
            $this->service()->deleteCategory($category);
            $this->dispatch('toast', message: 'Категория удалена');
        }
        $this->deleteId = null;
    }

    /* ---------- Порядок ---------- */

    public function moveCategory(int $id, int $target, bool $before): void
    {
        $category = HelpCategory::find($id);
        $targetCategory = HelpCategory::find($target);
        if (! $category || ! $targetCategory || filled(trim($this->q))) {
            return;
        }

        $this->service()->moveCategory($category, $targetCategory, $before);
        $this->dispatch('toast', message: 'Порядок сохранён');
    }

    public function moveArticle(int $id, int $target, bool $before): void
    {
        $article = HelpArticle::find($id);
        $targetArticle = HelpArticle::with('category')->find($target);
        if (! $article || ! $targetArticle || filled(trim($this->q))) {
            return;
        }

        $this->service()->moveArticle($article, $targetArticle, $before);
        $this->dispatch('toast', message: 'Порядок сохранён');
    }

    public function moveArticleToCategory(int $id, int $category): void
    {
        $article = HelpArticle::find($id);
        $target = HelpCategory::find($category);
        if (! $article || ! $target || filled(trim($this->q))) {
            return;
        }

        $this->service()->moveArticleToCategory($article, $target);
        $this->dispatch('toast', message: 'Порядок сохранён');
    }

    /* ---------- Черновики ---------- */

    public function publish(int $id): void
    {
        $this->service()->setPublished(HelpArticle::findOrFail($id), true);
        $this->dispatch('toast', message: 'Статья опубликована');
    }

    public function render()
    {
        $q = mb_strtolower(trim($this->q));
        $all = HelpCategory::withCount('articles')->get();

        $groups = $this->service()->categories($this->audience())
            ->map(function (HelpCategory $c) use ($q) {
                $articles = $c->articles->filter(fn (HelpArticle $a) => $q === '' || str_contains(mb_strtolower($a->title), $q));

                return [
                    'id' => $c->id,
                    'name' => $c->name,
                    'icon' => $c->icon,
                    'hidden' => ! $c->is_published,
                    'count' => $c->articles->count() ? plural_ru($c->articles->count(), 'статья', 'статьи', 'статей') : 'нет статей',
                    'articles' => $articles->map(fn (HelpArticle $a) => [
                        'id' => $a->id,
                        'title' => $a->title,
                        'video' => $a->has_video,
                        'draft' => ! $a->is_published,
                        'views' => number_format($a->views_count, 0, ',', ' ') . ' ' . plural_ru($a->views_count, 'просмотр', 'просмотра', 'просмотров', false),
                    ])->values()->all(),
                ];
            })
            ->filter(fn ($g) => $q === '' || $g['articles'] !== [])
            ->values();

        $drafts = HelpArticle::with('category')->where('is_published', false)->latest('updated_at')->get()
            ->map(fn (HelpArticle $a) => [
                'id' => $a->id,
                'title' => $a->title,
                'meta' => implode(' · ', array_filter([
                    $this->audienceLabel($a->category?->audience),
                    $a->category?->name,
                    $a->updated_at ? 'изменена ' . $this->changed($a->updated_at) : null,
                ])),
            ]);

        $total = (int) $all->sum('articles_count');
        $deleting = $this->deleteId ? $all->firstWhere('id', $this->deleteId) : null;

        return view('livewire.cabinet.admin.help', [
            'facts' => plural_ru($total, 'статья', 'статьи', 'статей') . ' в ' . plural_ru($all->count(), 'категории', 'категориях', 'категориях'),
            'tabs' => ['students' => 'Для учеников', 'tutors' => 'Для учителей'],
            'groups' => $groups,
            'searching' => $q !== '',
            'drafts' => $drafts,
            'icons' => HelpIcons::ICONS,
            'audiences' => [HelpCategory::AUDIENCE_STUDENT => 'Для учеников', HelpCategory::AUDIENCE_TUTOR => 'Для учителей'],
            'catCount' => $this->catOpen && $this->catId ? (int) ($all->firstWhere('id', $this->catId)?->articles_count) : 0,
            'deleting' => $deleting,
        ]);
    }

    private function audienceLabel(?string $audience): ?string
    {
        return match ($audience) {
            HelpCategory::AUDIENCE_STUDENT => 'Для учеников',
            HelpCategory::AUDIENCE_TUTOR => 'Для учителей',
            default => null,
        };
    }

    /** «сегодня», «вчера», «20 сентября». */
    private function changed($date): string
    {
        $day = HumanDate::day($date);

        return in_array($day, ['сегодня', 'вчера'], true) ? $day : HumanDate::date($date);
    }
}
