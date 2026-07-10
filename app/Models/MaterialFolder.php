<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class MaterialFolder extends Model
{
    protected $fillable = [
        'teacher_id',
        'parent_id',
        'name',
        'sort_order',
    ];

    /**
     * Учитель — владелец папки
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    /**
     * Родительская папка
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * Вложенные папки
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * Материалы в папке
     */
    public function materials(): HasMany
    {
        return $this->hasMany(TeacherMaterial::class, 'folder_id');
    }

    /**
     * Цепочка предков: от корня до родителя этой папки
     */
    public function ancestors(): Collection
    {
        $ancestors = collect();
        $current = $this->parent;

        while ($current) {
            $ancestors->prepend($current);
            $current = $current->parent;
        }

        return $ancestors;
    }

    /**
     * ID всех вложенных папок (всё поддерево, без самой папки)
     */
    public function descendantIds(): Collection
    {
        // Все папки учителя одним запросом, обход дерева в памяти
        $childrenByParent = [];

        foreach (self::where('teacher_id', $this->teacher_id)->pluck('parent_id', 'id') as $id => $parentId) {
            $childrenByParent[$parentId ?? 0][] = $id;
        }

        $ids = collect();
        $queue = [$this->id];

        while ($queue) {
            $parentId = array_shift($queue);

            foreach ($childrenByParent[$parentId] ?? [] as $childId) {
                $ids->push($childId);
                $queue[] = $childId;
            }
        }

        return $ids;
    }
}
