<?php

namespace App\Services;

use App\Helpers\FileUploadHelper;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Фото профиля. Из одной загрузки получаются три файла рядом:
 *   avatars/{id}/{имя}.webp      — основное, до 640×640 (страница учителя, кабинет);
 *   avatars/{id}/{имя}-256.webp  — для списков (каталог, отзывы, чаты), где фото не больше 128px;
 *   avatars/{id}/{имя}.jpg       — для превью ссылки в соцсетях и мессенджерах: WebP понимают не все.
 * В users.avatar хранится путь к основному файлу, остальные выводятся из него (thumbPath / jpgPath).
 * Старые фото (JPG/PNG до перехода на WebP) остаются одним файлом — их переводит avatars:webp.
 */
class AvatarService
{
    public const SIZE = 640;

    public const THUMB_SIZE = 256;

    private const WEBP_QUALITY = 82;

    private const JPEG_QUALITY = 85;

    private const DISK = 's3';

    /**
     * Значение поля «фото» из формы в путь для users.avatar: загруженный файл — сохраняем,
     * путь — оставляем, null — фото нет. Массив (так приходит из некоторых форм) — берём первый элемент.
     */
    public function resolve(mixed $value, int|string|null $userId = null): ?string
    {
        if (is_array($value)) {
            $value = reset($value) ?: null;
        }

        return match (true) {
            $value instanceof UploadedFile => $this->store($value, $userId),
            is_string($value) && $value !== '' => $value,
            default => null,
        };
    }

    /** Сохранить загруженное фото. Возвращает путь к основному файлу. */
    public function store(UploadedFile|TemporaryUploadedFile $file, int|string|null $userId = null): ?string
    {
        $directory = 'avatars/' . ($userId ?? auth()->id() ?? 'system');

        try {
            $path = $this->storeContents($file->get(), $directory);
            if ($file instanceof TemporaryUploadedFile) {
                $file->delete();
            }

            return $path;
        } catch (\Throwable $e) {
            // Нет поддержки WebP в GD или битый файл — сохраняем по-старому, в исходном формате
            Log::warning('AvatarService: WebP не получился, сохраняем как есть — ' . $e->getMessage());

            return $file instanceof TemporaryUploadedFile
                ? FileUploadHelper::processAndStoreFile($file, 'avatars', self::SIZE, self::SIZE)
                : $file->storePublicly($directory, self::DISK);
        }
    }

    /** Три файла из содержимого картинки. Бросает исключение, если картинку не прочитать или WebP не собрать. */
    public function storeContents(string $contents, string $directory): string
    {
        $base = trim($directory, '/') . '/' . Str::lower(Str::random(16));

        $main = (string) Image::read($contents)->scaleDown(self::SIZE, self::SIZE)->toWebp(self::WEBP_QUALITY);
        $thumb = (string) Image::read($contents)->scaleDown(self::THUMB_SIZE, self::THUMB_SIZE)->toWebp(self::WEBP_QUALITY);
        $jpeg = (string) Image::read($contents)->scaleDown(self::SIZE, self::SIZE)->toJpeg(self::JPEG_QUALITY);

        $disk = Storage::disk(self::DISK);
        $disk->put($base . '-256.webp', $thumb, 'public');
        $disk->put($base . '.jpg', $jpeg, 'public');
        // Основной файл — последним: если что-то упало раньше, в профиль ничего не попадёт
        $disk->put($base . '.webp', $main, 'public');

        return $base . '.webp';
    }

    /** Уменьшенная копия для списков; у старых фото её нет — тогда основной файл. */
    public static function thumbPath(string $path): string
    {
        return self::isWebp($path) ? substr($path, 0, -5) . '-256.webp' : $path;
    }

    /** Копия в JPG для превью ссылок; у старых фото основной файл и так JPG/PNG. */
    public static function jpgPath(string $path): string
    {
        return self::isWebp($path) ? substr($path, 0, -5) . '.jpg' : $path;
    }

    public static function isWebp(string $path): bool
    {
        return str_ends_with(strtolower($path), '.webp');
    }

    public static function url(string $path): string
    {
        return Storage::disk(self::DISK)->url($path);
    }

    /** Удалить фото со всеми копиями. */
    public function delete(?string $path): void
    {
        if (! $path) {
            return;
        }

        try {
            Storage::disk(self::DISK)->delete(array_values(array_unique([$path, self::thumbPath($path), self::jpgPath($path)])));
        } catch (\Throwable $e) {
            Log::error('AvatarService: не удалось удалить фото — ' . $e->getMessage());
        }
    }
}
