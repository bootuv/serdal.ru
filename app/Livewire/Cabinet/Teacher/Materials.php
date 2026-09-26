<?php

namespace App\Livewire\Cabinet\Teacher;

use App\Livewire\Cabinet\Teacher\Concerns\TeacherScreen;
use App\Models\MaterialFolder;
use App\Models\Room;
use App\Models\TeacherMaterial;
use App\Services\StudentMaterialsService;
use App\Services\TeacherMaterialsService;
use App\Support\HumanDate;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/** Материалы учителя: библиотека папок и файлов, доступ ученикам. Макет: «Учитель · Материалы» (docs/design/BRAND.md). */
#[Layout('components.layouts.cabinet', ['title' => 'Материалы', 'active' => 'materials'])]
class Materials extends Component
{
    use TeacherScreen;
    use WithFileUploads;

    private const PAGE = 60;

    /** Открытая папка (null — все материалы). */
    #[Url]
    public ?int $folder = null;

    #[Url(except: '')]
    public string $search = '';

    public int $limit = self::PAGE;

    /** Окно папки: null | new | edit. */
    public ?string $folderModal = null;

    public string $folderName = '';

    /** Куда положить папку ('' — в корень). */
    public string $folderParent = '';

    /** Открытый в окне файл. */
    public ?int $fileId = null;

    public string $fileTitle = '';

    public string $fileDescription = '';

    /** Новый файл вместо текущего (замена при редактировании) и его полное имя из браузера. */
    public $replacement = null;

    public ?string $replacementName = null;

    public string $fileFolder = '';

    public string $fileVisibility = TeacherMaterial::VISIBILITY_ALL;

    public array $fileRooms = [];

    /** Подтверждение удаления: null | file | folder. */
    public ?string $confirm = null;

    /** Окно загрузки. */
    public bool $uploadOpen = false;

    /** @var array<int, TemporaryUploadedFile> */
    public array $uploads = [];

    /** Полные имена файлов из браузера (во временном файле имя может быть укорочено). */
    public array $uploadNames = [];

    public string $uploadFolder = '';

    public string $uploadVisibility = TeacherMaterial::VISIBILITY_ALL;

    public array $uploadRooms = [];

    /** Режим «Выбрать», выбранные файлы и окно действия над ними: null | move | delete. */
    public bool $selecting = false;

    public array $picked = [];

    public ?string $bulk = null;

    public string $bulkFolder = '';

    public function mount(): void
    {
        $this->authorizeTeacher();

        // Чужая папка — как будто её нет
        if ($this->folder !== null && ! $this->service()->folders(auth()->user())->whereKey($this->folder)->exists()) {
            abort(404);
        }
    }

    /* ---------- Навигация ---------- */

    public function openFolder(?int $id = null): void
    {
        if ($id !== null && ! $this->service()->folders(auth()->user())->whereKey($id)->exists()) {
            return;
        }

        $this->folder = $id;
        $this->search = '';
        $this->limit = self::PAGE;
    }

    public function updatedSearch(): void
    {
        $this->limit = self::PAGE;
    }

    public function clearSearch(): void
    {
        $this->search = '';
        $this->limit = self::PAGE;
    }

    public function showMore(): void
    {
        $this->limit += self::PAGE;
    }

    /* ---------- Папки ---------- */

    public function newFolder(): void
    {
        $this->resetErrorBag();
        $this->folderModal = 'new';
        $this->folderName = '';
    }

    public function editFolder(): void
    {
        $folder = $this->currentFolder();
        if (! $folder) {
            return;
        }

        $this->resetErrorBag();
        $this->folderModal = 'edit';
        $this->folderName = $folder->name;
        $this->folderParent = (string) ($folder->parent_id ?? '');
    }

    public function saveFolder(): void
    {
        $this->validate(
            ['folderName' => 'required|string|max:255'],
            ['folderName.required' => 'Введите название папки.'],
        );

        $teacher = auth()->user();
        $name = trim($this->folderName);

        if ($this->folderModal === 'new') {
            $this->service()->createFolder($teacher, $name, $this->folder);
            $this->folderModal = null;
            $this->dispatch('toast', message: 'Папка «' . $name . '» создана');

            return;
        }

        $folder = $this->currentFolder();
        if (! $folder) {
            $this->folderModal = null;

            return;
        }

        $target = $this->folderParent === '' ? null : (int) $this->folderParent;
        if ($this->service()->moveFolder($teacher, $folder, $target) === 'inside') {
            $this->addError('folderParent', 'Нельзя переместить папку внутрь самой себя.');

            return;
        }

        $folder->update(['name' => $name]);
        $this->folderModal = null;
        $this->dispatch('toast', message: 'Папка сохранена');
    }

    public function deleteFolder(): void
    {
        $folder = $this->currentFolder();
        $this->confirm = null;
        $this->folderModal = null;

        if (! $folder) {
            return;
        }

        $parent = $folder->parent_id;
        $this->service()->deleteFolder($folder);
        $this->folder = $parent;
        $this->dispatch('toast', message: 'Папка удалена, файлы остались');
    }

    /** Перетаскивание папки на папку или в хлебные крошки. */
    public function moveFolderTo(int $id, ?int $target = null): void
    {
        $folder = $this->service()->folders(auth()->user())->find($id);
        if (! $folder) {
            return;
        }

        match ($this->service()->moveFolder(auth()->user(), $folder, $target)) {
            'moved' => $this->dispatch('toast', message: 'Папка перемещена в «' . $this->folderTitle($target) . '»'),
            'inside' => $this->dispatch('toast', message: 'Нельзя переместить папку внутрь самой себя'),
            default => null,
        };
    }

    /* ---------- Файлы ---------- */

    public function editFile(int $id): void
    {
        $material = $this->ownMaterial($id);

        $this->resetErrorBag();
        $this->fileId = $material->id;
        $this->fileTitle = $material->title;
        $this->fileDescription = (string) $material->description;
        $this->discardReplacement();
        $this->fileFolder = (string) ($material->folder_id ?? '');
        $this->fileVisibility = $material->visibility;
        $this->fileRooms = $material->rooms()->pluck('rooms.id')->map(fn ($id) => (string) $id)->all();
    }

    public function closeFile(): void
    {
        $this->discardReplacement();
        $this->fileId = null;
        $this->confirm = null;
    }

    /** Новый файл для замены доехал до сервера: тот же лимит, что при загрузке. */
    public function updatedReplacement(): void
    {
        if (! $this->fileId) {
            $this->discardReplacement();

            return;
        }

        try {
            $this->validate(
                ['replacement' => 'file|max:' . TeacherMaterialsService::MAX_FILE_KB],
                ['replacement.max' => 'Файл больше 200 МБ загрузить нельзя.', 'replacement.file' => 'Не удалось передать файл — попробуйте ещё раз.'],
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->discardReplacement();
            $this->addError('replacement', collect($e->errors())->flatten()->first());
        }
    }

    public function replacementFailed(): void
    {
        $this->discardReplacement();
        $this->addError('replacement', 'Не удалось передать файл. Возможно, он слишком большой или прервалась связь — попробуйте ещё раз.');
    }

    public function cancelReplacement(): void
    {
        $this->discardReplacement();
        $this->resetErrorBag('replacement');
    }

    public function saveFile(): void
    {
        $material = $this->ownMaterial((int) $this->fileId);

        $this->validate([
            'fileTitle' => 'required|string|max:255',
            'fileDescription' => 'nullable|string|max:1000',
            'fileVisibility' => 'required|in:' . implode(',', array_keys(TeacherMaterial::getVisibilityOptions())),
            'fileRooms' => 'required_if:fileVisibility,' . TeacherMaterial::VISIBILITY_ROOMS . '|array',
        ], [
            'fileTitle.required' => 'Введите название файла.',
            'fileDescription.max' => 'Сократите описание до 1000 символов.',
            'fileRooms.required_if' => 'Выберите, кому открыть файл.',
        ]);

        $teacher = auth()->user();
        $material->update(['title' => trim($this->fileTitle), 'description' => filled(trim($this->fileDescription)) ? trim($this->fileDescription) : null]);
        $this->service()->setAccess($teacher, $material, $this->fileVisibility, $this->fileRooms);

        $replaced = null;
        if ($this->replacement instanceof TemporaryUploadedFile) {
            $replaced = $this->service()->replaceFile($material, $this->replacement, $this->replacementName);
            $this->discardReplacement();
        }

        $target = $this->fileFolder === '' ? null : (int) $this->fileFolder;
        if ($target !== ($material->folder_id === null ? null : (int) $material->folder_id)) {
            $this->service()->moveMaterials($teacher, [$material->id], $target);
        }

        $this->fileId = null;
        $this->dispatch('toast', message: match ($replaced) {
            true => 'Файл заменён',
            false => 'Сохранено, но новый файл загрузить не удалось — попробуйте ещё раз',
            default => 'Файл сохранён',
        });
    }

    public function deleteFile(): void
    {
        $this->service()->deleteMaterials(auth()->user(), [(int) $this->fileId]);
        $this->fileId = null;
        $this->confirm = null;
        $this->dispatch('toast', message: 'Файл удалён');
    }

    /** Перетаскивание файла на папку или в хлебные крошки. */
    public function moveMaterial(int $id, ?int $target = null): void
    {
        if ($this->service()->moveMaterials(auth()->user(), [$id], $target) > 0) {
            $this->dispatch('toast', message: 'Файл перемещён в «' . $this->folderTitle($target) . '»');
        }
    }

    /** Перетаскивание файла на соседний файл — ручной порядок (при поиске порядок не меняем). */
    public function reorderMaterial(int $id, int $target, bool $before): void
    {
        if (! filled(trim($this->search))) {
            $this->service()->reorderMaterials(auth()->user(), $this->folder, $id, $target, $before);
        }
    }

    /** Перетаскивание папки к краю соседней папки — ручной порядок. */
    public function reorderFolder(int $id, int $target, bool $before): void
    {
        if (! filled(trim($this->search))) {
            $this->service()->reorderFolders(auth()->user(), $this->folder, $id, $target, $before);
        }
    }

    /* ---------- Несколько файлов ---------- */

    public function startSelect(): void
    {
        $this->selecting = true;
        $this->picked = [];
        $this->bulk = null;
    }

    public function cancelSelect(): void
    {
        $this->selecting = false;
        $this->picked = [];
        $this->bulk = null;
    }

    public function toggle(int $id): void
    {
        if (in_array($id, $this->picked, true)) {
            $this->picked = array_values(array_diff($this->picked, [$id]));
        } elseif ($this->service()->materials(auth()->user())->whereKey($id)->exists()) {
            $this->picked[] = $id;
        }
    }

    public function askBulk(string $action): void
    {
        $this->bulk = $this->picked !== [] && in_array($action, ['move', 'delete'], true) ? $action : null;
        $this->bulkFolder = (string) ($this->folder ?? '');
        $this->resetErrorBag('bulkFolder');
    }

    public function moveSelected(): void
    {
        $target = $this->bulkFolder === '' ? null : (int) $this->bulkFolder;
        $moved = $this->service()->moveMaterials(auth()->user(), $this->picked, $target);

        $this->cancelSelect();
        $this->dispatch('toast', message: $moved > 0
            ? 'Перемещено в «' . $this->folderTitle($target) . '»: ' . plural_ru($moved, 'файл', 'файла', 'файлов')
            : 'Не удалось переместить файлы');
    }

    public function deleteSelected(): void
    {
        $deleted = $this->service()->deleteMaterials(auth()->user(), $this->picked);

        $this->cancelSelect();
        $this->dispatch('toast', message: 'Удалено: ' . plural_ru($deleted, 'файл', 'файла', 'файлов'));
    }

    /* ---------- Загрузка ---------- */

    public function openUpload(): void
    {
        $this->resetErrorBag();
        $this->discardUploads();
        $this->uploadOpen = true;
        $this->uploadFolder = (string) ($this->folder ?? '');
        $this->uploadVisibility = TeacherMaterial::VISIBILITY_ALL;
        $this->uploadRooms = [];
    }

    public function cancelUpload(): void
    {
        $this->discardUploads();
        $this->uploadOpen = false;
    }

    /** Файлы доехали до сервера: отбрасываем потерянные, проверяем размер (как в старом кабинете). */
    public function updatedUploads(): void
    {
        if (! $this->uploadOpen) {
            $this->discardUploads();

            return;
        }

        $lost = 0;
        $this->uploads = array_values(array_filter($this->uploads, function ($file) use (&$lost) {
            $ok = $file instanceof TemporaryUploadedFile && $file->getFilename() !== '' && $file->exists();
            $lost += $ok ? 0 : 1;

            return $ok;
        }));

        if ($lost > 0) {
            Log::error('Материалы: временный файл не сохранился на диске сервера', ['user_id' => auth()->id(), 'lost' => $lost]);
            $this->addError('uploads', 'Сервер не смог сохранить ' . plural_ru($lost, 'файл', 'файла', 'файлов') . ' — попробуйте ещё раз.');
        }

        try {
            $this->validate(
                ['uploads.*' => 'file|max:' . TeacherMaterialsService::MAX_FILE_KB],
                ['uploads.*.max' => 'Файл больше 200 МБ загрузить нельзя.'],
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->discardUploads();
            $this->addError('uploads', collect($e->errors())->flatten()->unique()->implode(' '));
        }
    }

    public function uploadFailed(): void
    {
        $this->addError('uploads', 'Не удалось передать файлы. Возможно, файл слишком большой или прервалась связь — попробуйте ещё раз.');
    }

    public function saveUpload(): void
    {
        if (empty($this->uploads)) {
            $this->addError('uploads', 'Выберите файлы или дождитесь, пока они передадутся.');

            return;
        }

        $this->validate([
            'uploadVisibility' => 'required|in:' . implode(',', array_keys(TeacherMaterial::getVisibilityOptions())),
            'uploadRooms' => 'required_if:uploadVisibility,' . TeacherMaterial::VISIBILITY_ROOMS . '|array',
        ], ['uploadRooms.required_if' => 'Выберите, кому открыть файлы.']);

        $teacher = auth()->user();
        $folder = $this->uploadFolder === '' ? null : (int) $this->uploadFolder;
        $created = 0;

        foreach ($this->uploads as $i => $file) {
            $material = $this->service()->storeUpload($teacher, $file, $folder, $this->uploadVisibility, $this->uploadRooms, $this->uploadNames[$i] ?? null);
            $created += $material ? 1 : 0;
        }

        $this->uploads = [];
        $this->uploadNames = [];
        $this->uploadOpen = false;

        $this->dispatch('toast', message: $created > 0
            ? 'Загружено: ' . plural_ru($created, 'файл', 'файла', 'файлов')
            : 'Не удалось загрузить файлы — попробуйте ещё раз');
    }

    /* ---------- Экран ---------- */

    public function render()
    {
        $teacher = auth()->user();
        $service = $this->service();
        $searching = filled(trim($this->search));
        $allFolders = $service->folders($teacher)->orderBy('sort_order')->orderBy('name')->get(['id', 'parent_id', 'name']);
        $paths = $this->paths($allFolders);

        $files = $service->materials($teacher)
            ->with('rooms.participants:id,name')
            ->when($searching,
                fn ($q) => app(StudentMaterialsService::class)->search($q, $this->search),
                fn ($q) => $q->where('folder_id', $this->folder))
            ->orderBy('sort_order')->orderByDesc('created_at')
            ->limit($this->limit + 1)
            ->get();

        $totalFiles = $service->materials($teacher)->count();

        return view('livewire.cabinet.teacher.materials', [
            'sub' => $totalFiles
                ? plural_ru($totalFiles, 'файл', 'файла', 'файлов') . ($allFolders->isNotEmpty() ? ' в ' . plural_ru($allFolders->count(), 'папке', 'папках', 'папках') : '')
                : null,
            'searching' => $searching,
            'crumbs' => $this->crumbs($allFolders, $searching),
            'folders' => $searching ? collect() : $this->folderRows($allFolders),
            'files' => $files->take($this->limit)->map(fn (TeacherMaterial $m) => $this->fileRow($m, $searching ? ($paths[$m->folder_id] ?? null) : null)),
            'hasMore' => $files->count() > $this->limit,
            // Ручной порядок перетаскиванием — внутри папки, не в результатах поиска
            'sortable' => ! $searching && ! $this->selecting,
            'isEmpty' => $totalFiles === 0 && $allFolders->isEmpty(),
            'access' => $service->accessSummary($teacher),
            'current' => $this->folder ? $allFolders->firstWhere('id', $this->folder) : null,
            'folderOptions' => ['' => 'Все материалы'] + $paths->all(),
            'parentOptions' => $this->parentOptions($allFolders, $paths),
            'roomOptions' => ($this->fileId || $this->uploadOpen) ? $this->roomOptions() : collect(),
            'file' => $this->fileId ? $service->materials($teacher)->find($this->fileId) : null,
            'visibilityItems' => [
                TeacherMaterial::VISIBILITY_PRIVATE => 'Только вам',
                TeacherMaterial::VISIBILITY_ROOMS => 'Выбранным',
                TeacherMaterial::VISIBILITY_ALL => 'Всем ученикам',
            ],
        ]);
    }

    /** Цепочка «Все материалы › ЕГЭ-2027 › Теория»; при поиске — «Все материалы › Поиск». */
    private function crumbs(Collection $folders, bool $searching): array
    {
        $crumbs = [['id' => null, 'name' => 'Все материалы']];

        if ($searching) {
            return [...$crumbs, ['id' => null, 'name' => 'Поиск', 'search' => true]];
        }

        $chain = [];
        $byId = $folders->keyBy('id');
        $id = $this->folder;
        while ($id !== null && isset($byId[$id]) && count($chain) < 50) {
            array_unshift($chain, ['id' => $id, 'name' => $byId[$id]->name]);
            $id = $byId[$id]->parent_id;
        }

        return [...$crumbs, ...$chain];
    }

    /** Папки текущего уровня: «2 папки · 3 файла» или «Пусто». */
    private function folderRows(Collection $all): Collection
    {
        $level = $all->where('parent_id', $this->folder);
        if ($level->isEmpty()) {
            return collect();
        }

        $counts = TeacherMaterial::query()
            ->whereIn('folder_id', $level->pluck('id'))
            ->selectRaw('folder_id, count(*) as n')
            ->groupBy('folder_id')
            ->pluck('n', 'folder_id');

        return $level->map(function (MaterialFolder $f) use ($all, $counts) {
            $sub = $all->where('parent_id', $f->id)->count();
            $n = (int) ($counts[$f->id] ?? 0);

            return [
                'id' => $f->id,
                'name' => $f->name,
                'meta' => implode(' · ', array_filter([
                    $sub ? plural_ru($sub, 'папка', 'папки', 'папок') : null,
                    $n ? plural_ru($n, 'файл', 'файла', 'файлов') : null,
                ])) ?: 'Пусто',
            ];
        })->values();
    }

    private function fileRow(TeacherMaterial $m, ?string $path): array
    {
        return [
            'id' => $m->id,
            'title' => $m->title,
            'file' => $m->original_name ?: $m->file_path,
            'access' => implode(' · ', array_filter([$path, $this->service()->accessLabel($m)])),
            'lock' => $m->visibility === TeacherMaterial::VISIBILITY_PRIVATE || ($m->visibility === TeacherMaterial::VISIBILITY_ROOMS && $m->rooms->isEmpty()),
            'size' => $m->file_size > 0 ? str_replace('.', ',', $m->formatted_size) : null,
            'date' => $this->dateLabel($m->created_at),
        ];
    }

    /** Полные пути папок: [id => «ЕГЭ-2027 / Теория»]. */
    private function paths(Collection $folders): Collection
    {
        $byId = $folders->keyBy('id');

        return $folders->mapWithKeys(function (MaterialFolder $f) use ($byId) {
            $names = [];
            $cur = $f;
            while ($cur && count($names) < 50) {
                array_unshift($names, $cur->name);
                $cur = $cur->parent_id ? ($byId[$cur->parent_id] ?? null) : null;
            }

            return [$f->id => implode(' / ', $names)];
        })->sort();
    }

    /** Куда можно переложить открытую папку: всё, кроме неё самой и её поддерева. */
    private function parentOptions(Collection $folders, Collection $paths): array
    {
        $current = $this->folderModal === 'edit' ? $folders->firstWhere('id', $this->folder) : null;
        if (! $current) {
            return [];
        }

        $exclude = $current->descendantIds()->push($current->id);

        return ['' => 'Все материалы'] + $paths->except($exclude->all())->all();
    }

    /** Занятия учителя для выбора доступа: «Алина Смирнова · Английский», «Группа «ЕГЭ-2027»». */
    private function roomOptions(): Collection
    {
        return $this->service()->rooms(auth()->user())->map(function (Room $room) {
            $label = $this->service()->roomLabel($room);

            return [
                'id' => (string) $room->id,
                'label' => str_starts_with($label, 'группа')
                    ? 'Группа «' . $room->name . '»'
                    : $label . ($room->name ? ' · ' . $room->name : ''),
            ];
        })->sortBy('label')->values();
    }

    private function folderTitle(?int $id): string
    {
        return $id ? ($this->service()->folders(auth()->user())->find($id)?->name ?? 'Все материалы') : 'Все материалы';
    }

    private function currentFolder(): ?MaterialFolder
    {
        return $this->folder ? $this->service()->folders(auth()->user())->find($this->folder) : null;
    }

    private function ownMaterial(int $id): TeacherMaterial
    {
        $material = $this->service()->materials(auth()->user())->find($id);
        abort_unless($material, 404);

        return $material;
    }

    private function discardReplacement(): void
    {
        if ($this->replacement instanceof TemporaryUploadedFile) {
            try {
                $this->replacement->delete();
            } catch (\Throwable) {
                // Временный файл мог не долететь — не критично
            }
        }

        $this->replacement = null;
        $this->replacementName = null;
    }

    private function discardUploads(): void
    {
        foreach ($this->uploads as $file) {
            if ($file instanceof TemporaryUploadedFile) {
                try {
                    $file->delete();
                } catch (\Throwable) {
                    // Временный файл мог не долететь — не критично
                }
            }
        }

        $this->uploads = [];
        $this->uploadNames = [];
    }

    private function dateLabel(?Carbon $date): ?string
    {
        if (! $date) {
            return null;
        }

        return abs($date->copy()->startOfDay()->diffInDays(today())) <= 1 ? HumanDate::day($date) : HumanDate::date($date);
    }

    private function service(): TeacherMaterialsService
    {
        return app(TeacherMaterialsService::class);
    }
}
