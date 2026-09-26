<?php

namespace App\Services;

use App\Models\MaterialFolder;
use App\Models\TeacherMaterial;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Материалы, открытые ученику: какие учителя, папки и файлы он видит.
 * Правила доступа — TeacherMaterial::scopeVisibleToStudent.
 */
class StudentMaterialsService
{
    /** ID учителей ученика, у которых есть хотя бы один доступный материал. */
    public function availableTeacherIds(User $student): Collection
    {
        return TeacherMaterial::query()
            ->visibleToStudent($student)
            ->distinct()
            ->pluck('teacher_id')
            ->map(fn ($id) => (int) $id)
            ->values();
    }

    /** Доступные ученику материалы (в рамках учителя, если задан). */
    public function materials(User $student, ?int $teacherId = null): Builder
    {
        return TeacherMaterial::query()
            ->with(['folder', 'teacher'])
            ->visibleToStudent($student)
            ->when($teacherId, fn ($q) => $q->where('teacher_id', $teacherId));
    }

    /** Поиск по названию, описанию и имени файла. */
    public function search(Builder $query, string $term): Builder
    {
        $like = '%' . trim($term) . '%';

        return $query->where(fn ($q) => $q
            ->where('title', 'like', $like)
            ->orWhere('description', 'like', $like)
            ->orWhere('original_name', 'like', $like));
    }

    /**
     * ID папок, видимых ученику: папки с доступными материалами плюс все их предки
     * (папка видна, если где-то в её поддереве есть доступный файл).
     */
    public function visibleFolderIds(User $student, ?int $teacherId = null): Collection
    {
        $withMaterials = TeacherMaterial::query()
            ->visibleToStudent($student)
            ->when($teacherId, fn ($q) => $q->where('teacher_id', $teacherId))
            ->whereNotNull('folder_id')
            ->distinct()
            ->pluck('folder_id');

        if ($withMaterials->isEmpty()) {
            return collect();
        }

        // Дерево папок одним запросом, предков добавляем в памяти
        $parents = MaterialFolder::query()
            ->when($teacherId, fn ($q) => $q->where('teacher_id', $teacherId))
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

    /** Папки, видимые ученику. */
    public function visibleFolders(User $student, ?int $teacherId = null): Builder
    {
        return MaterialFolder::query()
            ->with('teacher')
            ->whereIn('id', $this->visibleFolderIds($student, $teacherId));
    }
}
