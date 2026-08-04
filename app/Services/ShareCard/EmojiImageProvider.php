<?php

namespace App\Services\ShareCard;

use Illuminate\Support\Facades\Http;

/**
 * Отдаёт PNG-картинку эмодзи (Twemoji), потому что GD не умеет рисовать
 * цветные эмодзи-шрифты. Картинки ищутся в репозитории, затем в локальном
 * кэше, и только потом скачиваются с CDN — после чего кэшируются на диск.
 */
class EmojiImageProvider
{
    private const CDN = 'https://cdn.jsdelivr.net/gh/jdecked/twemoji@15.1.0/assets/72x72/';

    /** @var array<string, string|null> */
    private array $memo = [];

    // CDN не отвечает — не тратим таймаут на остальные эмодзи этого отзыва
    private bool $unreachable = false;

    /**
     * Байты PNG для эмодзи-последовательности или null, если картинки нет.
     */
    public function bytes(string $sequence): ?string
    {
        if (array_key_exists($sequence, $this->memo)) {
            return $this->memo[$sequence];
        }

        foreach ($this->fileNames($sequence) as $name) {
            $bytes = $this->fromDisk($name) ?? $this->download($name);

            if ($bytes !== null) {
                return $this->memo[$sequence] = $bytes;
            }
        }

        return $this->memo[$sequence] = null;
    }

    /**
     * Имена файлов Twemoji: сначала вся последовательность, затем — базовый
     * символ (для редких склеек через ZWJ и тонов кожи картинки может не быть).
     *
     * @return list<string>
     */
    private function fileNames(string $sequence): array
    {
        $points = [];

        foreach (preg_split('//u', $sequence, -1, PREG_SPLIT_NO_EMPTY) as $char) {
            $point = mb_ord($char, 'UTF-8');

            // Twemoji не включает селекторы вариаций в имена файлов
            if ($point === false || $point === 0xFE0F || $point === 0xFE0E) {
                continue;
            }

            $points[] = strtolower(dechex($point));
        }

        if ($points === []) {
            return [];
        }

        $names = [implode('-', $points)];

        if (count($points) > 1) {
            $names[] = $points[0];
        }

        return $names;
    }

    private function fromDisk(string $name): ?string
    {
        foreach ([resource_path('emoji/' . $name . '.png'), $this->cachePath($name)] as $path) {
            if (is_file($path)) {
                return file_get_contents($path) ?: null;
            }
        }

        return null;
    }

    private function download(string $name): ?string
    {
        if ($this->unreachable || ! preg_match('/^[0-9a-f]+(-[0-9a-f]+)*$/', $name)) {
            return null;
        }

        try {
            $response = Http::timeout(3)->get(self::CDN . $name . '.png');
        } catch (\Throwable) {
            $this->unreachable = true;

            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $bytes = $response->body();

        if ($bytes === '' || ! str_starts_with($bytes, "\x89PNG")) {
            return null;
        }

        $this->cache($name, $bytes);

        return $bytes;
    }

    private function cache(string $name, string $bytes): void
    {
        $path = $this->cachePath($name);

        if (! is_dir(dirname($path))) {
            @mkdir(dirname($path), 0775, true);
        }

        @file_put_contents($path, $bytes);
    }

    private function cachePath(string $name): string
    {
        return storage_path('app/emoji/' . $name . '.png');
    }
}
