<?php

namespace App\Filament\Student\Resources\MaterialResource\Pages;

use App\Filament\Student\Resources\MaterialResource;
use App\Models\MaterialFolder;
use App\Models\TeacherMaterial;
use App\Models\User;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;

class ListMaterials extends Page
{
    protected static string $resource = MaterialResource::class;

    protected static string $view = 'filament.student.pages.materials-browser';

    /** Выбранный учитель (null = корень; актуально при нескольких учителях) */
    #[Url]
    public ?int $teacher = null;

    /** Текущая открытая папка (null = не открыта) */
    #[Url]
    public ?int $folder = null;

    #[Url(except: '')]
    public string $search = '';

    public int $limit = 60;

    public function getTitle(): string|Htmlable
    {
        return 'Материалы';
    }

    public function mount(): void
    {
        // ID учителей, у которых есть доступные материалы
        $teacherIds = $this->availableTeacherIds();

        if ($teacherIds->count() === 1) {
            // Один учитель — уровень учителей не нужен, сразу его материалы
            $this->teacher = $teacherIds->first();
        } elseif ($this->teacher !== null && ! $teacherIds->contains($this->teacher)) {
            // Чужой/несуществующий учитель в URL
            $this->teacher = null;
        }

        if ($this->folder !== null && ! $this->visibleFolders()->whereKey($this->folder)->exists()) {
            $this->folder = null;
        }
    }

    public function updatedSearch(): void
    {
        $this->limit = 60;
    }

    /**
     * ID учителей текущего ученика, у которых есть хотя бы один доступный материал
     */
    protected function availableTeacherIds(): Collection
    {
        return TeacherMaterial::query()
            ->visibleToStudent(auth()->user())
            ->distinct()
            ->pluck('teacher_id');
    }

    /**
     * Материалы, доступные текущему ученику (в рамках выбранного учителя)
     */
    protected function materialQuery()
    {
        return TeacherMaterial::query()
            ->with(['folder', 'teacher'])
            ->visibleToStudent(auth()->user())
            ->when($this->teacher, fn ($q) => $q->where('teacher_id', $this->teacher));
    }

    /**
     * ID папок, видимых ученику: папки с доступными материалами плюс все их предки
     * (папка видна, если где-то в её поддереве есть доступный файл)
     */
    protected function visibleFolderIds(): Collection
    {
        $withMaterials = TeacherMaterial::query()
            ->visibleToStudent(auth()->user())
            ->when($this->teacher, fn ($q) => $q->where('teacher_id', $this->teacher))
            ->whereNotNull('folder_id')
            ->distinct()
            ->pluck('folder_id');

        if ($withMaterials->isEmpty()) {
            return collect();
        }

        // Дерево папок одним запросом, предков добавляем в памяти
        $parents = MaterialFolder::query()
            ->when($this->teacher, fn ($q) => $q->where('teacher_id', $this->teacher))
            ->pluck('parent_id', 'id');

        $ids = collect();

        foreach ($withMaterials as $id) {
            while ($id !== null && ! $ids->contains($id)) {
                $ids->push($id);
                $id = $parents[$id] ?? null;
            }
        }

        return $ids;
    }

    /**
     * Папки, видимые ученику
     */
    protected function visibleFolders()
    {
        return MaterialFolder::query()
            ->with('teacher')
            ->whereIn('id', $this->visibleFolderIds());
    }

    /**
     * Нужен ли уровень «учителя» (материалы от нескольких учителей)
     */
    public function getHasTeacherLevelProperty(): bool
    {
        return $this->availableTeacherIds()->count() > 1;
    }

    public function getActiveTeacherProperty(): ?User
    {
        return $this->teacher ? User::find($this->teacher) : null;
    }

    public function getActiveFolderProperty(): ?MaterialFolder
    {
        return $this->folder ? $this->visibleFolders()->find($this->folder) : null;
    }

    /**
     * Хлебные крошки: цепочка от корня до открытой папки
     */
    public function getBreadcrumbFoldersProperty(): Collection
    {
        $active = $this->activeFolder;

        return $active ? $active->ancestors()->push($active) : collect();
    }

    /**
     * Плитки учителей (корень при нескольких учителях)
     */
    public function getTeachersProperty(): Collection
    {
        if (! $this->hasTeacherLevel || $this->teacher !== null || filled(trim($this->search))) {
            return collect();
        }

        $counts = TeacherMaterial::query()
            ->visibleToStudent(auth()->user())
            ->selectRaw('teacher_id, count(*) as materials_count')
            ->groupBy('teacher_id')
            ->pluck('materials_count', 'teacher_id');

        return User::whereIn('id', $counts->keys())
            ->orderBy('name')
            ->get()
            ->each(fn (User $t) => $t->setAttribute('materials_count', $counts[$t->id] ?? 0));
    }

    public function getFoldersProperty(): Collection
    {
        // Папки видны только внутри учителя и вне поиска
        if ($this->teacher === null || filled(trim($this->search))) {
            return collect();
        }

        return $this->visibleFolders()
            ->where('parent_id', $this->folder)
            ->withCount(['materials' => fn ($q) => $q->visibleToStudent(auth()->user())])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function getMaterialsProperty(): Collection
    {
        // В корне при нескольких учителях файлы не показываем (кроме поиска)
        if ($this->hasTeacherLevel && $this->teacher === null && blank(trim($this->search))) {
            return collect();
        }

        // Порядок как у учителя: ручная сортировка, новые — первыми
        $query = $this->materialQuery()->orderBy('sort_order')->orderByDesc('created_at');

        if (filled(trim($this->search))) {
            $term = '%' . trim($this->search) . '%';
            $query->where(fn ($q) => $q
                ->where('title', 'like', $term)
                ->orWhere('description', 'like', $term)
                ->orWhere('original_name', 'like', $term));
        } elseif ($this->folder !== null) {
            $query->where('folder_id', $this->folder);
        } else {
            $query->whereNull('folder_id');
        }

        return $query->limit($this->limit + 1)->get();
    }

    public function openTeacher(int $id): void
    {
        if ($this->availableTeacherIds()->contains($id)) {
            $this->teacher = $id;
            $this->folder = null;
            $this->search = '';
            $this->limit = 60;
        }
    }

    public function openFolder(int $id): void
    {
        $folder = $this->visibleFolders()->find($id);

        if ($folder) {
            $this->teacher = $folder->teacher_id;
            $this->folder = $folder->id;
            $this->search = '';
            $this->limit = 60;
        }
    }

    /**
     * Шаг назад: из папки — к родительской папке/учителю, от учителя — к списку учителей
     */
    public function goBack(): void
    {
        if ($this->folder !== null) {
            $this->folder = $this->activeFolder?->parent_id;
        } elseif ($this->hasTeacherLevel) {
            $this->teacher = null;
        }

        $this->limit = 60;
    }

    /** В самый корень */
    public function goHome(): void
    {
        $this->folder = null;

        if ($this->hasTeacherLevel) {
            $this->teacher = null;
        }

        $this->limit = 60;
    }

    public function showMore(): void
    {
        $this->limit += 60;
    }
}
