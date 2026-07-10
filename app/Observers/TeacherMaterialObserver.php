<?php

namespace App\Observers;

use App\Jobs\GenerateMaterialThumbnail;
use App\Models\TeacherMaterial;
use Illuminate\Support\Facades\Storage;

class TeacherMaterialObserver
{
    /**
     * Handle the TeacherMaterial "saving" event.
     *
     * Fills file metadata (mime type, size) whenever the file changes.
     */
    public function saving(TeacherMaterial $material): void
    {
        if (! $material->isDirty('file_path') || empty($material->file_path)) {
            return;
        }

        try {
            $disk = Storage::disk('s3');
            $material->file_size = $disk->size($material->file_path) ?: null;
            $material->mime_type = $disk->mimeType($material->file_path) ?: null;
        } catch (\Throwable $e) {
            \Log::warning("TeacherMaterial: failed to read file metadata for {$material->file_path}: " . $e->getMessage());
        }
    }

    /**
     * Handle the TeacherMaterial "updating" event.
     *
     * When the file is replaced, remove the old file and its thumbnail from
     * the CDN before the new path is persisted.
     */
    public function updating(TeacherMaterial $material): void
    {
        if (! $material->isDirty('file_path')) {
            return;
        }

        foreach ([$material->getOriginal('file_path'), $material->getOriginal('thumbnail_path')] as $path) {
            if (is_string($path) && $path !== '' && Storage::disk('s3')->exists($path)) {
                Storage::disk('s3')->delete($path);
            }
        }

        $material->thumbnail_path = null;
    }

    /**
     * Handle the TeacherMaterial "created" event.
     *
     * При загрузке через каталог миниатюра создаётся синхронно и приходит
     * уже готовой; очередь — страховка для остальных путей создания.
     */
    public function created(TeacherMaterial $material): void
    {
        if (empty($material->thumbnail_path)) {
            GenerateMaterialThumbnail::dispatch($material)->delay(now()->addSeconds(3));
        }
    }

    /**
     * Handle the TeacherMaterial "updated" event.
     */
    public function updated(TeacherMaterial $material): void
    {
        if ($material->wasChanged('file_path')) {
            GenerateMaterialThumbnail::dispatch($material)->delay(now()->addSeconds(3));
        }
    }

    /**
     * Handle the TeacherMaterial "deleting" event.
     *
     * Removes the file and its thumbnail from the CDN (s3). material_room
     * pivot rows are removed via FK cascade.
     */
    public function deleting(TeacherMaterial $material): void
    {
        foreach ([$material->file_path, $material->thumbnail_path] as $path) {
            if (is_string($path) && $path !== '' && Storage::disk('s3')->exists($path)) {
                Storage::disk('s3')->delete($path);
            }
        }
    }
}
