<?php

namespace App\Services;

use App\Jobs\ConvertVideo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Видео в тексте (новости, блог): исходник уходит в очередь, ffmpeg сжимает его в MP4 (H.264, длинная сторона до 1280 — HD)
 * и делает обложку. Размер ролика известен сразу (ffprobe) — редактор рисует заглушку в его пропорциях и показывает
 * процент обработки (progress, его пишет convert по выводу ffmpeg). Адреса известны сразу — редактор вставляет видео, пока оно обрабатывается; опубликовать текст
 * с необработанным видео нельзя (pendingIn / failedIn). GIF превращается в беззвучное зацикленное видео — в разы легче.
 */
class MediaService
{
    public const MAX_SIDE = 1280;

    /** Исходники до конвертации — локально, не на CDN. */
    private const INCOMING = 'media-incoming';

    /**
     * Принять видео (или GIF при $loop) и поставить конвертацию в очередь.
     *
     * @return array{src: string, poster: string, loop: bool, width: ?int, height: ?int}
     */
    public function storeVideo(UploadedFile $file, string $dir, bool $loop = false): array
    {
        $key = Str::lower(Str::random(24));
        $source = $file->storeAs(self::INCOMING, $key . '.' . (strtolower($file->getClientOriginalExtension()) ?: 'bin'), 'local');
        $path = Storage::disk('local')->path($source);

        $video = trim($dir, '/') . '/' . $key . '.mp4';
        $poster = trim($dir, '/') . '/' . $key . '.jpg';
        [$width, $height] = $this->outputSize($this->probe($path));

        Cache::put($this->pendingKey($key), true, now()->addHours(3));
        app(EditorMediaService::class)->register($this->url($video), auth()->user(), [$video, $poster]);
        ConvertVideo::dispatch($path, $video, $poster, $loop);

        return ['src' => $this->url($video), 'poster' => $this->url($poster), 'loop' => $loop, 'width' => $width, 'height' => $height];
    }

    /**
     * Размер и длительность ролика (с учетом поворота — у видео с телефона ширина и высота в файле «лежачие»).
     *
     * @return array{width: ?int, height: ?int, duration: float}
     */
    public function probe(string $path): array
    {
        $result = Process::timeout(60)->run([config('services.ffmpeg.ffprobe', 'ffprobe'), '-v', 'error', '-select_streams', 'v:0',
            '-show_entries', 'stream=width,height:stream_side_data=rotation:stream_tags=rotate:format=duration', '-of', 'json', $path]);
        $data = $result->successful() ? (json_decode($result->output(), true) ?: []) : [];
        $stream = $data['streams'][0] ?? [];
        $width = isset($stream['width']) ? (int) $stream['width'] : null;
        $height = isset($stream['height']) ? (int) $stream['height'] : null;
        $rotation = (int) ($stream['tags']['rotate'] ?? collect($stream['side_data_list'] ?? [])->pluck('rotation')->filter()->first() ?? 0);
        if (abs($rotation) % 180 === 90) {
            [$width, $height] = [$height, $width];
        }

        return ['width' => $width, 'height' => $height, 'duration' => (float) ($data['format']['duration'] ?? 0)];
    }

    /** Размер после сжатия: длинная сторона не больше MAX_SIDE, стороны четные. */
    private function outputSize(array $probe): array
    {
        [$w, $h] = [$probe['width'], $probe['height']];
        if (! $w || ! $h) {
            return [null, null];
        }
        $k = min(1, self::MAX_SIDE / max($w, $h));

        return [(int) (floor($w * $k / 2) * 2), (int) (floor($h * $k / 2) * 2)];
    }

    /** Сжать исходник, выложить MP4 и обложку на CDN. Вызывается из задачи ConvertVideo. */
    public function convert(string $source, string $video, string $poster, bool $loop): void
    {
        $key = pathinfo($video, PATHINFO_FILENAME);
        $work = sys_get_temp_dir() . '/serdal-video-' . $key;
        @mkdir($work, 0775, true);
        $mp4 = $work . '/video.mp4';
        $jpg = $work . '/poster.jpg';

        try {
            $side = self::MAX_SIDE;
            // Длинная сторона — не больше 1280, меньшие не растягиваем; стороны четные (требование H.264)
            $scale = "scale='if(gte(iw,ih),min({$side},iw),-2)':'if(gte(iw,ih),-2,min({$side},ih))',scale=trunc(iw/2)*2:trunc(ih/2)*2";

            // Процент обработки — по времени из вывода ffmpeg (-progress): редактор показывает его на заглушке
            $duration = $this->probe($source)['duration'];
            $progressKey = $this->progressKey($key);
            $last = -1;
            $this->ffmpeg(array_merge(
                ['-i', $source, '-vf', $scale, '-c:v', 'libx264', '-preset', 'veryfast', '-crf', $loop ? '28' : '26', '-pix_fmt', 'yuv420p'],
                $loop ? ['-an'] : ['-c:a', 'aac', '-b:a', '128k', '-ac', '2'],
                ['-movflags', '+faststart', '-progress', 'pipe:1', '-nostats', $mp4],
            ), output: function (string $type, string $out) use ($duration, $progressKey, &$last) {
                if ($duration <= 0 || ! preg_match_all('/out_time_(?:us|ms)=(\d+)/', $out, $m)) {
                    return;
                }
                $percent = (int) min(99, floor((int) end($m[1]) / 1_000_000 / $duration * 100));
                if ($percent > $last) {
                    $last = $percent;
                    Cache::put($progressKey, $percent, now()->addHours(3));
                }
            });

            // Обложка — кадр на первой секунде (у коротких роликов — первый кадр)
            $this->ffmpeg(['-ss', '1', '-i', $mp4, '-frames:v', '1', '-q:v', '4', $jpg], silent: true);
            if (! is_file($jpg) || filesize($jpg) === 0) {
                $this->ffmpeg(['-i', $mp4, '-frames:v', '1', '-q:v', '4', $jpg]);
            }

            // Пока сжимали, видео удалили из редактора — результат не выкладываем
            if (Cache::pull('media:discarded:' . $key)) {
                Cache::forget($this->pendingKey($key));
                Cache::forget($this->progressKey($key));
                @unlink($source);

                return;
            }

            $disk = Storage::disk(HelpCenterService::DISK);
            $disk->put($video, fopen($mp4, 'r'), ['visibility' => 'public', 'ContentType' => 'video/mp4']);
            $disk->put($poster, fopen($jpg, 'r'), ['visibility' => 'public', 'ContentType' => 'image/jpeg']);

            Cache::forget($this->pendingKey($key));
            Cache::forget($this->progressKey($key));
            @unlink($source);
        } finally {
            @unlink($mp4);
            @unlink($jpg);
            @rmdir($work);
        }
    }

    /** Конвертация не удалась окончательно (все попытки задачи). */
    public function markFailed(string $video, string $source): void
    {
        $key = pathinfo($video, PATHINFO_FILENAME);
        Cache::forget($this->pendingKey($key));
        Cache::put($this->failedKey($key), true, now()->addDays(2));
        @unlink($source);
    }

    /** Состояние видео по адресу: ready, pending или failed. */
    public function status(string $url): string
    {
        $key = pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_FILENAME);

        return match (true) {
            Cache::has($this->pendingKey($key)) => 'pending',
            Cache::has($this->failedKey($key)) => 'failed',
            default => 'ready',
        };
    }

    /** Сколько процентов уже обработано (0 — еще ждет в очереди). */
    public function progress(string $url): int
    {
        return (int) Cache::get($this->progressKey(pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_FILENAME)), 0);
    }

    /** В тексте есть видео, которое еще обрабатывается. */
    public function pendingIn(?string $html): bool
    {
        return in_array('pending', $this->statuses($html), true);
    }

    /** В тексте есть видео, которое не удалось обработать. */
    public function failedIn(?string $html): bool
    {
        return in_array('failed', $this->statuses($html), true);
    }

    /** ffmpeg на сервере есть — иначе видео не принимаем. */
    public function available(): bool
    {
        return Process::run([config('services.ffmpeg.path', 'ffmpeg'), '-version'])->successful();
    }

    private function statuses(?string $html): array
    {
        preg_match_all('~<video\b[^>]*\ssrc="([^"]+)"~i', (string) $html, $m);

        return array_map(fn ($url) => $this->status(html_entity_decode($url)), $m[1]);
    }

    private function ffmpeg(array $args, bool $silent = false, ?callable $output = null): void
    {
        $result = Process::timeout(3000)->run(array_merge([config('services.ffmpeg.path', 'ffmpeg'), '-y', '-hide_banner', '-loglevel', 'error'], $args), $output);
        if (! $silent && ! $result->successful()) {
            throw new \RuntimeException('ffmpeg: ' . Str::limit(trim($result->errorOutput()), 500));
        }
    }

    private function url(string $path): string
    {
        return Storage::disk(HelpCenterService::DISK)->url($path);
    }

    private function pendingKey(string $key): string
    {
        return 'media:pending:' . $key;
    }

    private function progressKey(string $key): string
    {
        return 'media:progress:' . $key;
    }

    private function failedKey(string $key): string
    {
        return 'media:failed:' . $key;
    }
}
