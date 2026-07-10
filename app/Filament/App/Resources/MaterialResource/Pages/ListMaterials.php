<?php

namespace App\Filament\App\Resources\MaterialResource\Pages;

use App\Filament\App\Resources\MaterialResource;
use App\Helpers\FileUploadHelper;
use App\Models\MaterialFolder;
use App\Models\TeacherMaterial;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Livewire\WithFileUploads;

class ListMaterials extends Page
{
    use WithFileUploads;

    protected static string $resource = MaterialResource::class;

    protected static string $view = 'filament.app.pages.materials-explorer';

    /** Текущая открытая папка (null = корень) */
    #[Url]
    public ?int $folder = null;

    #[Url(except: '')]
    public string $search = '';

    /** Сколько файлов показывать (кнопка «Показать ещё») */
    public int $limit = 60;

    /** Выбранные файлы (массовые операции) */
    public array $selected = [];

    /** Файлы, ожидающие загрузки (из системного окна выбора или drag&drop) */
    public array $pendingFiles = [];

    /** Метаданные выбранных файлов (имя, размер) — известны сразу, до передачи на сервер */
    public array $uploadingMeta = [];

    public function getTitle(): string|Htmlable
    {
        return 'Материалы';
    }

    public function mount(): void
    {
        // Не даём открыть чужую папку через URL
        if ($this->folder !== null && ! $this->folderQuery()->whereKey($this->folder)->exists()) {
            $this->folder = null;
        }
    }

    public function updatedSearch(): void
    {
        $this->limit = 60;
    }

    /**
     * Передача файлов на сервер не удалась (лимиты сервера, обрыв сети) —
     * показываем причину вместо молчаливого сбоя
     */
    public function notifyUploadError(): void
    {
        Notification::make()
            ->title('Не удалось передать файлы')
            ->body('Возможно, файл слишком большой или соединение прервалось. Попробуйте ещё раз или загрузите файлы поменьше.')
            ->danger()
            ->persistent()
            ->send();
    }

    /**
     * Файлы выбраны — сразу открываем модалку настроек,
     * пока файлы ещё передаются на сервер в фоне
     */
    public function openUploadSettings(array $files): void
    {
        $this->uploadingMeta = collect($files)
            ->map(fn ($f) => [
                'name' => (string) ($f['name'] ?? 'файл'),
                'size' => (int) ($f['size'] ?? 0),
            ])
            ->take(100)
            ->all();

        if ($this->uploadingMeta) {
            $this->mountAction('uploadSettings');
        }
    }

    /**
     * Загрузка отменена (модалка закрыта без отправки) —
     * удаляем уже переданные временные файлы с сервера
     */
    public function discardPendingUpload(): void
    {
        foreach ($this->pendingFiles as $file) {
            if ($file instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile) {
                try {
                    $file->delete();
                } catch (\Throwable $e) {
                    // Временный файл мог не долететь — не критично
                }
            }
        }

        $this->pendingFiles = [];
        $this->uploadingMeta = [];
    }

    /**
     * Файлы доехали до сервера — валидируем размер
     */
    public function updatedPendingFiles(): void
    {
        // Окно уже закрыто (отмена) — файлы больше не нужны, чистим сразу.
        // Покрывает гонку: передача завершилась в момент отмены.
        if (empty($this->uploadingMeta)) {
            $this->discardPendingUpload();

            return;
        }

        try {
            $this->validate(
                ['pendingFiles.*' => 'file|max:204800'],
                ['pendingFiles.*.max' => 'Файл больше 200 МБ не может быть загружен.'],
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->pendingFiles = [];
            $this->uploadingMeta = [];

            // Закрываем модалку настроек — загружать нечего
            $this->dispatch('close-modal', id: "{$this->getId()}-action");

            Notification::make()
                ->title('Не удалось загрузить')
                ->body(collect($e->errors())->flatten()->unique()->implode(' '))
                ->danger()
                ->send();
        }
    }

    /**
     * Промежуточное окно настроек загрузки выбранных файлов
     */
    public function uploadSettingsAction(): Actions\Action
    {
        return Actions\Action::make('uploadSettings')
            ->label('Загрузка файлов')
            ->modalHeading(fn () => 'Загрузка файлов (' . count($this->uploadingMeta) . ' шт.)')
            ->modalSubmitActionLabel('Загрузить')
            ->modalWidth('lg')
            ->fillForm(fn () => [
                'folder_id' => $this->folder,
                'visibility' => TeacherMaterial::VISIBILITY_ALL,
            ])
            ->form([
                Forms\Components\Placeholder::make('files')
                    ->label('Файлы')
                    ->content(fn () => new \Illuminate\Support\HtmlString(
                        '<ul class="space-y-1 text-sm">' .
                        collect($this->uploadingMeta)->map(function ($meta) {
                            $name = e($meta['name']);
                            $size = $meta['size'] > 0 ? \Illuminate\Support\Number::fileSize($meta['size'], precision: 1) : '';

                            return "<li class=\"flex items-center justify-between gap-3\"><span class=\"truncate\">{$name}</span><span class=\"shrink-0 text-gray-400\">{$size}</span></li>";
                        })->implode('') .
                        '</ul>'
                    )),

                // Прогресс передачи файлов на сервер
                Forms\Components\View::make('filament.app.partials.material-upload-progress'),

                Forms\Components\Select::make('folder_id')
                    ->label('Папка')
                    ->options(fn () => $this->folderQuery()->orderBy('name')->pluck('name', 'id'))
                    ->placeholder('Без папки (корень каталога)'),

                Forms\Components\Radio::make('visibility')
                    ->label('Видимость')
                    ->options(TeacherMaterial::getVisibilityOptions())
                    ->required()
                    ->live(),

                Forms\Components\Select::make('rooms')
                    ->label('Занятия (группы)')
                    ->options(fn () => \App\Models\Room::where('user_id', auth()->id())->orderBy('name')->pluck('name', 'id'))
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->required()
                    ->visible(fn (Forms\Get $get) => $get('visibility') === TeacherMaterial::VISIBILITY_ROOMS),
            ])
            ->action(function (array $data, Actions\Action $action) {
                // Файлы ещё передаются на сервер — не даём отправить форму раньше времени
                if (empty($this->pendingFiles)) {
                    Notification::make()
                        ->title('Файлы ещё передаются')
                        ->body('Дождитесь окончания передачи — прогресс показан в окне.')
                        ->warning()
                        ->send();

                    $action->halt();
                }

                $created = 0;

                foreach ($this->pendingFiles as $file) {
                    $originalName = $file->getClientOriginalName();

                    // Сжимает изображения, кладёт на S3 в teacher-materials/{id}, удаляет temp
                    $path = FileUploadHelper::processAndStoreFile($file, 'teacher-materials');

                    if (! $path) {
                        continue;
                    }

                    $material = TeacherMaterial::create([
                        'teacher_id' => auth()->id(),
                        'folder_id' => $data['folder_id'] ?? null,
                        'title' => pathinfo($originalName, PATHINFO_FILENAME) ?: $originalName,
                        'file_path' => $path,
                        'original_name' => $originalName,
                        'visibility' => $data['visibility'],
                        // Для изображений миниатюра создаётся сразу — сетка не грузит полноразмер
                        'thumbnail_path' => \App\Jobs\GenerateMaterialThumbnail::generateFromPath($path),
                    ]);

                    if ($data['visibility'] === TeacherMaterial::VISIBILITY_ROOMS) {
                        $material->rooms()->sync($data['rooms'] ?? []);
                    }

                    $created++;
                }

                $this->pendingFiles = [];
                $this->uploadingMeta = [];

                if ($created > 0) {
                    Notification::make()
                        ->title('Загружено файлов: ' . $created)
                        ->success()
                        ->send();
                }
            });
    }

    protected function folderQuery()
    {
        return MaterialFolder::where('teacher_id', auth()->id());
    }

    protected function materialQuery()
    {
        return TeacherMaterial::where('teacher_id', auth()->id())->with('folder');
    }

    public function getActiveFolderProperty(): ?MaterialFolder
    {
        return $this->folder ? $this->folderQuery()->find($this->folder) : null;
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
     * Подпапки текущей папки (при поиске — скрываем)
     */
    public function getFoldersProperty(): Collection
    {
        if (filled(trim($this->search))) {
            return collect();
        }

        return $this->folderQuery()
            ->where('parent_id', $this->folder)
            ->withCount('materials')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function getMaterialsProperty(): Collection
    {
        // Ручной порядок (перетаскивание), новые файлы (sort_order=0) — первыми
        $query = $this->materialQuery()->orderBy('sort_order')->orderByDesc('created_at');

        if (filled(trim($this->search))) {
            // Поиск — по всему каталогу, независимо от папки
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

    public function openFolder(int $id): void
    {
        if ($this->folderQuery()->whereKey($id)->exists()) {
            $this->folder = $id;
            $this->search = '';
            $this->limit = 60;
            $this->selected = [];
        }
    }

    public function closeFolder(): void
    {
        $this->folder = null;
        $this->limit = 60;
        $this->selected = [];
    }

    public function toggleSelect(int $id): void
    {
        $this->selected = in_array($id, $this->selected)
            ? array_values(array_diff($this->selected, [$id]))
            : [...$this->selected, $id];
    }

    public function selectAllVisible(): void
    {
        $this->selected = $this->materials->take($this->limit)->pluck('id')->all();
    }

    public function clearSelection(): void
    {
        $this->selected = [];
    }

    public function showMore(): void
    {
        $this->limit += 60;
    }

    /**
     * Переместить файл в папку (null — в корень).
     * Если файл входит в выделение — перемещаем всё выделенное.
     */
    public function moveMaterial(int $materialId, ?int $targetFolderId): void
    {
        if ($targetFolderId !== null && ! $this->folderQuery()->whereKey($targetFolderId)->exists()) {
            return;
        }

        $ids = in_array($materialId, $this->selected) ? $this->selected : [$materialId];

        $moved = $this->materialQuery()
            ->whereKey($ids)
            ->update(['folder_id' => $targetFolderId, 'sort_order' => 0]);

        $this->selected = [];

        if ($moved > 0) {
            $target = $targetFolderId ? $this->folderQuery()->find($targetFolderId)?->name : null;

            Notification::make()
                ->title($moved === 1 ? 'Файл перемещён' : "Перемещено файлов: {$moved}")
                ->body($target ? "В папку «{$target}»" : 'В корень каталога')
                ->success()
                ->send();
        }
    }

    /**
     * Переместить папку в другую папку (null — в корень),
     * запрещая перемещение в саму себя и в собственное поддерево
     */
    public function moveFolder(int $folderId, ?int $targetFolderId): void
    {
        $folder = $this->folderQuery()->find($folderId);

        if (! $folder || $targetFolderId === $folderId || $targetFolderId === $folder->parent_id) {
            return;
        }

        if ($targetFolderId !== null) {
            if (! $this->folderQuery()->whereKey($targetFolderId)->exists()) {
                return;
            }

            // Нельзя перемещать папку внутрь её собственного поддерева
            if ($folder->descendantIds()->contains($targetFolderId)) {
                Notification::make()
                    ->title('Нельзя переместить папку внутрь самой себя')
                    ->warning()
                    ->send();

                return;
            }
        }

        $folder->update(['parent_id' => $targetFolderId]);

        $target = $targetFolderId ? $this->folderQuery()->find($targetFolderId)?->name : null;

        Notification::make()
            ->title('Папка перемещена')
            ->body($target ? "В папку «{$target}»" : 'В корень каталога')
            ->success()
            ->send();
    }

    /**
     * Изменить порядок: вставить перетаскиваемый файл до/после целевого
     */
    public function reorderMaterials(int $draggedId, int $targetId, bool $before): void
    {
        if ($draggedId === $targetId || filled(trim($this->search))) {
            return;
        }

        $ids = $this->materials->take($this->limit)->pluck('id');

        if (! $ids->contains($draggedId) || ! $ids->contains($targetId)) {
            return;
        }

        $ordered = $ids->reject(fn ($id) => $id === $draggedId)->values();
        $targetPosition = $ordered->search($targetId);
        $insertAt = $before ? $targetPosition : $targetPosition + 1;
        $ordered->splice($insertAt, 0, [$draggedId]);

        foreach ($ordered->values() as $position => $id) {
            $this->materialQuery()->whereKey($id)->update(['sort_order' => $position + 1]);
        }
    }

    /**
     * Изменить порядок папок: вставить перетаскиваемую до/после целевой
     */
    public function reorderFolders(int $draggedId, int $targetId, bool $before): void
    {
        if ($draggedId === $targetId || filled(trim($this->search))) {
            return;
        }

        $ids = $this->folders->pluck('id');

        if (! $ids->contains($draggedId) || ! $ids->contains($targetId)) {
            return;
        }

        $ordered = $ids->reject(fn ($id) => $id === $draggedId)->values();
        $targetPosition = $ordered->search($targetId);
        $insertAt = $before ? $targetPosition : $targetPosition + 1;
        $ordered->splice($insertAt, 0, [$draggedId]);

        foreach ($ordered->values() as $position => $id) {
            $this->folderQuery()->whereKey($id)->update(['sort_order' => $position + 1]);
        }
    }

    /**
     * Файл отпущен на файле: создать папку с обоими файлами
     * (плюс всё выделенное, если перетаскиваемый входит в выделение)
     */
    public function createFolderFromFilesAction(): Actions\Action
    {
        return Actions\Action::make('createFolderFromFiles')
            ->modalHeading('Новая папка из файлов')
            ->modalWidth('sm')
            ->modalSubmitActionLabel('Создать')
            ->fillForm(['name' => 'Новая папка'])
            ->form([
                Forms\Components\TextInput::make('name')
                    ->label('Название папки')
                    ->required()
                    ->maxLength(255)
                    ->autofocus(),
            ])
            ->action(function (array $data, array $arguments) {
                $draggedId = (int) ($arguments['dragged'] ?? 0);
                $targetId = (int) ($arguments['target'] ?? 0);

                $ids = collect(in_array($draggedId, $this->selected) ? [...$this->selected, $targetId] : [$draggedId, $targetId])
                    ->unique()
                    ->values();

                // Только свои файлы
                $materials = $this->materialQuery()->whereKey($ids)->get();

                if ($materials->count() < 2) {
                    return;
                }

                $folder = MaterialFolder::create([
                    'teacher_id' => auth()->id(),
                    'parent_id' => $this->folder,
                    'name' => $data['name'],
                ]);

                $this->materialQuery()
                    ->whereKey($materials->pluck('id'))
                    ->update(['folder_id' => $folder->id, 'sort_order' => 0]);

                $this->selected = [];

                Notification::make()
                    ->title("Папка «{$folder->name}» создана")
                    ->body('Файлов внутри: ' . $materials->count())
                    ->success()
                    ->send();
            });
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('newFolder')
                ->label('Новая папка')
                ->icon('heroicon-o-folder-plus')
                ->color('gray')
                ->modalWidth('sm')
                ->form([
                    Forms\Components\TextInput::make('name')
                        ->label('Название папки')
                        ->required()
                        ->maxLength(255),
                ])
                ->action(function (array $data) {
                    MaterialFolder::create([
                        'teacher_id' => auth()->id(),
                        'parent_id' => $this->folder,
                        'name' => $data['name'],
                    ]);

                    Notification::make()->title('Папка создана')->success()->send();
                }),

            Actions\Action::make('upload')
                ->label('Загрузить файлы')
                ->icon('heroicon-m-arrow-up-tray')
                ->alpineClickHandler("document.getElementById('materials-file-picker').click()"),
        ];
    }

    public function renameFolderAction(): Actions\Action
    {
        return Actions\Action::make('renameFolder')
            ->label('Переименовать')
            ->modalHeading('Переименовать папку')
            ->modalWidth('sm')
            ->fillForm(fn (array $arguments) => [
                'name' => $this->folderQuery()->find($arguments['folder'])?->name,
            ])
            ->form([
                Forms\Components\TextInput::make('name')
                    ->label('Название папки')
                    ->required()
                    ->maxLength(255),
            ])
            ->action(function (array $data, array $arguments) {
                $this->folderQuery()->whereKey($arguments['folder'])->update(['name' => $data['name']]);

                Notification::make()->title('Папка переименована')->success()->send();
            });
    }

    public function deleteFolderAction(): Actions\Action
    {
        return Actions\Action::make('deleteFolder')
            ->requiresConfirmation()
            ->modalHeading('Удалить папку?')
            ->modalDescription('Содержимое не удалится — вложенные папки и файлы переместятся на уровень выше.')
            ->modalSubmitActionLabel('Удалить')
            ->color('danger')
            ->action(function (array $arguments) {
                $folder = $this->folderQuery()->find($arguments['folder']);

                if (! $folder) {
                    return;
                }

                // Поднимаем содержимое к родителю удаляемой папки
                $folder->children()->update(['parent_id' => $folder->parent_id]);
                $folder->materials()->update(['folder_id' => $folder->parent_id]);

                $folder->delete();

                Notification::make()->title('Папка удалена')->success()->send();
            });
    }

    public function deleteSelectedAction(): Actions\Action
    {
        return Actions\Action::make('deleteSelected')
            ->requiresConfirmation()
            ->modalHeading(fn () => 'Удалить выбранные файлы (' . count($this->selected) . ' шт.)?')
            ->modalDescription('Файлы будут удалены безвозвратно, включая копии на CDN.')
            ->modalSubmitActionLabel('Удалить')
            ->color('danger')
            ->action(function () {
                // Удаляем по одному через delete() модели, чтобы observer
                // почистил файл и миниатюру на S3 для каждого материала
                $materials = $this->materialQuery()->whereKey($this->selected)->get();
                $materials->each->delete();

                $this->selected = [];

                Notification::make()
                    ->title('Удалено файлов: ' . $materials->count())
                    ->success()
                    ->send();
            });
    }

    public function deleteMaterialAction(): Actions\Action
    {
        return Actions\Action::make('deleteMaterial')
            ->requiresConfirmation()
            ->modalHeading('Удалить файл?')
            ->modalDescription('Файл будет удалён безвозвратно, включая копию на CDN.')
            ->modalSubmitActionLabel('Удалить')
            ->color('danger')
            ->action(function (array $arguments) {
                // Через delete() модели, чтобы observer почистил файлы на S3
                $this->materialQuery()->whereKey($arguments['material'])->first()?->delete();

                $this->selected = array_values(array_diff($this->selected, [(int) $arguments['material']]));

                Notification::make()->title('Файл удалён')->success()->send();
            });
    }
}
