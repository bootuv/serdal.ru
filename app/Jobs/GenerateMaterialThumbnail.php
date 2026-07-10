<?php

namespace App\Jobs;

use App\Models\TeacherMaterial;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;

class GenerateMaterialThumbnail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const THUMB_WIDTH = 640;
    private const THUMB_HEIGHT = 400;
    private const QUALITY = 75;

    public function __construct(
        public TeacherMaterial $material
    ) {
    }

    public function handle(): void
    {
        if (! empty($this->material->thumbnail_path)) {
            return;
        }

        $thumbPath = self::generateFromPath($this->material->file_path);

        if ($thumbPath) {
            // Без событий, чтобы не зациклить observer
            $this->material->updateQuietly(['thumbnail_path' => $thumbPath]);
        }
    }

    /**
     * Сгенерировать миниатюру для изображения на S3.
     * Возвращает путь миниатюры или null (не изображение / ошибка).
     * Используется и синхронно при загрузке, и из очереди.
     */
    public static function generateFromPath(?string $path): ?string
    {
        if (empty($path)) {
            return null;
        }

        // Миниатюры генерируем только для изображений (кроме gif)
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (! in_array($extension, ['jpg', 'jpeg', 'png', 'webp'])) {
            return null;
        }

        try {
            if (! Storage::disk('s3')->exists($path)) {
                return null;
            }

            $image = Image::read(Storage::disk('s3')->get($path));
            $image->coverDown(self::THUMB_WIDTH, self::THUMB_HEIGHT);

            $thumbPath = preg_replace('/\.(' . $extension . ')$/i', '', $path) . '_thumb.jpg';
            $encoded = $image->encodeByExtension('jpg', quality: self::QUALITY);

            Storage::disk('s3')->put($thumbPath, (string) $encoded, 'public');

            return $thumbPath;
        } catch (\Exception $e) {
            \Log::error("GenerateMaterialThumbnail: failed for {$path}: " . $e->getMessage());

            return null;
        }
    }
}
