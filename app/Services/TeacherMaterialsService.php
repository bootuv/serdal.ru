<?php

namespace App\Services;

use App\Helpers\FileUploadHelper;
use App\Jobs\GenerateMaterialThumbnail;
use App\Models\MaterialFolder;
use App\Models\Room;
use App\Models\TeacherMaterial;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Материалы учителя: папки, загрузка, доступ, перемещение и удаление.
 * Используется старым (Filament, MaterialResource) и новым кабинетом учителя.
 * Что из этого видит ученик — TeacherMaterial::scopeVisibleToStudent (StudentMaterialsService).
 */
class TeacherMaterialsService
{
    /** Максимальный размер одного файла, КБ (как у временных загрузок Livewire). */
    public const MAX_FILE_KB = 204800;

    /** Папки учителя. */
    public function folders(User $teacher): Builder
    {
        return MaterialFolder::query()->where('teacher_id', $teacher->id);
    }

    /** Материалы учителя. */
    public function materials(User $teacher): Builder
    {
        return TeacherMaterial::query()->where('teacher_id', $teacher->id)->with('folder');
    }

    /** Занятия учителя, которым можно открыть материал. */
    public function rooms(User $teacher): Collection
    {
        return Room::query()
            ->where('user_id', $teacher->id)
            ->with('participants:id,name')
            ->orderBy('name')
            ->get();
    }

    public function createFolder(User $teacher, string $name, ?int $parentId = null): MaterialFolder
    {
        if ($parentId !== null && ! $this->folders($teacher)->whereKey($parentId)->exists()) {
            $parentId = null;
        }

        return MaterialFolder::create([
            'teacher_id' => $teacher->id,
            'parent_id' => $parentId,
            'name' => $name,
        ]);
    }

    /** Удаление папки: вложенные папки и файлы поднимаются к её родителю. */
    public function deleteFolder(MaterialFolder $folder): void
    {
        $folder->children()->update(['parent_id' => $folder->parent_id]);
        $folder->materials()->update(['folder_id' => $folder->parent_id]);

        $folder->delete();
    }

    /**
     * Переместить файлы в папку (null — в корень). Только свои файлы и своя папка.
     *
     * @return int сколько файлов перемещено
     */
    public function moveMaterials(User $teacher, array $ids, ?int $targetFolderId): int
    {
        if ($targetFolderId !== null && ! $this->folders($teacher)->whereKey($targetFolderId)->exists()) {
            return 0;
        }

        return $this->materials($teacher)
            ->whereKey($ids)
            ->update(['folder_id' => $targetFolderId, 'sort_order' => 0]);
    }

    /**
     * Переместить папку в другую (null — в корень).
     *
     * @return string moved — перемещена; same — уже там или цель не найдена; inside — цель внутри самой папки
     */
    public function moveFolder(User $teacher, MaterialFolder $folder, ?int $targetFolderId): string
    {
        if ((int) $folder->teacher_id !== (int) $teacher->id || $targetFolderId === (int) $folder->id || $targetFolderId === ($folder->parent_id === null ? null : (int) $folder->parent_id)) {
            return 'same';
        }

        if ($targetFolderId !== null) {
            if (! $this->folders($teacher)->whereKey($targetFolderId)->exists()) {
                return 'same';
            }

            // Нельзя перемещать папку внутрь её собственного поддерева
            if ($folder->descendantIds()->contains($targetFolderId)) {
                return 'inside';
            }
        }

        $folder->update(['parent_id' => $targetFolderId]);

        return 'moved';
    }

    /**
     * Сохранить загруженный файл: сжатие изображений, S3 (teacher-materials/{id}), миниатюра, доступ.
     * $clientName — полное имя файла из браузера (во временном файле оно может быть укорочено).
     */
    public function storeUpload(User $teacher, TemporaryUploadedFile $file, ?int $folderId, string $visibility, array $roomIds = [], ?string $clientName = null): ?TeacherMaterial
    {
        $originalName = $file->getClientOriginalName();

        if ($clientName && str_starts_with($clientName, pathinfo($originalName, PATHINFO_FILENAME))) {
            $originalName = $clientName;
        }

        if ($folderId !== null && ! $this->folders($teacher)->whereKey($folderId)->exists()) {
            $folderId = null;
        }

        $path = FileUploadHelper::processAndStoreFile($file, 'teacher-materials');

        if (! $path) {
            return null;
        }

        $material = TeacherMaterial::create([
            'teacher_id' => $teacher->id,
            'folder_id' => $folderId,
            'title' => pathinfo($originalName, PATHINFO_FILENAME) ?: $originalName,
            'file_path' => $path,
            'original_name' => $originalName,
            'visibility' => $visibility,
            // Для изображений миниатюра создаётся сразу — сетка не грузит полноразмер
            'thumbnail_path' => GenerateMaterialThumbnail::generateFromPath($path),
        ]);

        if ($visibility === TeacherMaterial::VISIBILITY_ROOMS) {
            $this->syncRooms($teacher, $material, $roomIds);
        }

        return $material;
    }

    /** Кому открыт файл: только учителю, выбранным занятиям или всем ученикам. */
    public function setAccess(User $teacher, TeacherMaterial $material, string $visibility, array $roomIds = []): void
    {
        $material->update(['visibility' => $visibility]);

        $this->syncRooms($teacher, $material, $visibility === TeacherMaterial::VISIBILITY_ROOMS ? $roomIds : []);
    }

    /** Удалить файлы (по одному, чтобы observer убрал файл и миниатюру с S3). */
    public function deleteMaterials(User $teacher, array $ids): int
    {
        $materials = $this->materials($teacher)->whereKey($ids)->get();
        $materials->each->delete();

        return $materials->count();
    }

    /** Занятие для людей: индивидуальное — имя ученика, групповое — «группа «ЕГЭ-2027»». */
    public function roomLabel(Room $room): string
    {
        $student = $room->participants->first();

        if ($room->type !== 'group' && $room->participants->count() === 1 && $student) {
            return $student->name;
        }

        return 'группа «' . $room->name . '»';
    }

    /** «Только вам», «Видно всем ученикам», «Видно: Алина Смирнова, группа «ЕГЭ-2027»». */
    public function accessLabel(TeacherMaterial $material): string
    {
        return match ($material->visibility) {
            TeacherMaterial::VISIBILITY_ALL => 'Видно всем ученикам',
            TeacherMaterial::VISIBILITY_ROOMS => $material->rooms->isEmpty()
                ? 'Только вам'
                : 'Видно: ' . $material->rooms->map(fn (Room $r) => $this->roomLabel($r))->unique()->join(', '),
            default => 'Только вам',
        };
    }

    /**
     * «Кому открыто»: сколько файлов видит каждый ученик/группа, всем ученикам и только учителю.
     *
     * @return Collection<int, array{kind:string, name:string, count:int, user:?User}>
     */
    public function accessSummary(User $teacher): Collection
    {
        $materials = TeacherMaterial::query()
            ->where('teacher_id', $teacher->id)
            ->with('rooms.participants:id,name')
            ->get(['id', 'visibility']);

        $rows = [];
        $add = function (string $key, array $row) use (&$rows) {
            $rows[$key] ??= $row + ['count' => 0];
            $rows[$key]['count']++;
        };

        foreach ($materials as $m) {
            if ($m->visibility === TeacherMaterial::VISIBILITY_ALL) {
                $add('all', ['kind' => 'all', 'name' => 'Всем ученикам', 'user' => null, 'rank' => 2]);
                continue;
            }

            if ($m->visibility !== TeacherMaterial::VISIBILITY_ROOMS || $m->rooms->isEmpty()) {
                $add('private', ['kind' => 'private', 'name' => 'Только вам', 'user' => null, 'rank' => 3]);
                continue;
            }

            // Один файл считаем у человека/группы один раз, даже если открыт нескольким его занятиям
            $keys = [];
            foreach ($m->rooms as $room) {
                $student = $room->participants->first();
                if ($room->type !== 'group' && $room->participants->count() === 1 && $student) {
                    $keys['u' . $student->id] = ['kind' => 'person', 'name' => $student->name, 'user' => $student, 'rank' => 0];
                } else {
                    $keys['r' . $room->id] = ['kind' => 'group', 'name' => 'Группа «' . $room->name . '»', 'user' => null, 'rank' => 1];
                }
            }
            foreach ($keys as $key => $row) {
                $add($key, $row);
            }
        }

        return collect($rows)
            ->sortBy([['rank', 'asc'], ['count', 'desc'], ['name', 'asc']])
            ->values();
    }

    private function syncRooms(User $teacher, TeacherMaterial $material, array $roomIds): void
    {
        $own = Room::query()->where('user_id', $teacher->id)->whereKey($roomIds)->pluck('id');

        $material->rooms()->sync($own);
    }
}
