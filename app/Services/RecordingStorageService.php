<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class RecordingStorageService
{
    /**
     * Download video from BBB and upload to S3.
     *
     * @return string|null  Public S3 URL on success, null on failure
     */
    public function uploadToS3(string $videoUrl, int $teacherId, string $filename): ?string
    {
        $tempPath = null;

        try {
            // Step 1: Download video from BBB
            Log::info('S3 Recording: Downloading video...', ['url' => $videoUrl]);

            $tempFile = 'recording_' . uniqid() . '.m4v';
            $tempPath = storage_path('app/temp/' . $tempFile);

            if (!file_exists(dirname($tempPath))) {
                mkdir(dirname($tempPath), 0755, true);
            }

            $dlResponse = Http::withoutVerifying()->timeout(1800)->connectTimeout(30)->sink($tempPath)->get($videoUrl);

            if ($dlResponse->failed()) {
                Log::error('S3 Recording: Download failed', ['status' => $dlResponse->status()]);
                return null;
            }

            clearstatcache(true, $tempPath);
            $fileSize = filesize($tempPath);
            $expectedSize = (int) $dlResponse->header('Content-Length');

            // Оборванное скачивание нельзя выгружать: после выгрузки оригинал удаляется с BBB
            if (!$fileSize || ($expectedSize > 0 && $fileSize !== $expectedSize)) {
                Log::error('S3 Recording: Downloaded file is incomplete', [
                    'size' => $fileSize,
                    'expected' => $expectedSize,
                ]);
                return null;
            }

            Log::info('S3 Recording: File downloaded', ['size' => $fileSize]);

            // Step 2: Upload to S3
            $s3Path = "recordings/{$teacherId}/{$filename}";
            $startedAt = microtime(true);

            // Диск с throw=true: иначе put() молча вернёт false и причина ошибки потеряется
            $stream = fopen($tempPath, 'r');
            try {
                $uploaded = $this->throwingDisk()->put($s3Path, $stream, 'public');
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }

            if (!$uploaded) {
                Log::error('S3 Recording: Failed to upload to S3', ['path' => $s3Path]);
                return null;
            }

            $seconds = max(microtime(true) - $startedAt, 0.001);
            $speed = round($fileSize / 1048576 / $seconds, 2);

            // Медленный канал — первопричина зависших выгрузок, поэтому скорость пишем в лог
            Log::log($speed < 2 ? 'warning' : 'info', 'S3 Recording: Upload speed', [
                'seconds' => round($seconds, 1),
                'mb_per_sec' => $speed,
            ]);

            // Step 3: Get public URL
            $s3Url = rtrim(config('filesystems.disks.s3.url', ''), '/');
            $root = config('filesystems.disks.s3.root');
            if ($root) {
                $s3Url .= '/' . trim($root, '/');
            }
            $s3Url .= '/' . ltrim($s3Path, '/');

            Log::info('S3 Recording: Uploaded successfully', [
                'path' => $s3Path,
                'url' => $s3Url,
                'size' => $fileSize,
            ]);

            return $s3Url;

        } catch (\Exception $e) {
            Log::error('S3 Recording: Exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return null;
        } finally {
            if ($tempPath && file_exists($tempPath)) {
                @unlink($tempPath);
            }
        }
    }

    /**
     * Upload a local file directly to S3
     */
    public function uploadLocalFileToS3(string $localFile, string $s3Path): ?string
    {
        try {
            if (!$this->isConfigured()) {
                return null;
            }

            Log::info('S3 Local Upload: Starting', ['size' => filesize($localFile), 'path' => $s3Path]);

            $uploaded = Storage::disk('s3')->put(
                $s3Path,
                fopen($localFile, 'r'),
                'public'
            );

            if (!$uploaded) {
                Log::error('S3 Local Upload: Failed', ['path' => $s3Path]);
                return null;
            }

            // Get public URL
            $s3Url = rtrim(config('filesystems.disks.s3.url', ''), '/');
            $root = config('filesystems.disks.s3.root');
            if ($root) {
                $s3Url .= '/' . trim($root, '/');
            }
            $s3Url .= '/' . ltrim($s3Path, '/');

            Log::info('S3 Local Upload: Success', ['url' => $s3Url]);
            return $s3Url;

        } catch (\Exception $e) {
            Log::error('S3 Local Upload: Exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return null;
        }
    }

    /**
     * Delete recording from S3
     */
    public function deleteFromS3(string $s3Url): bool
    {
        try {
            $path = $this->pathFromUrl($s3Url);

            if (Storage::disk('s3')->exists($path)) {
                Storage::disk('s3')->delete($path);
                Log::info('S3 Recording: Deleted', ['path' => $path]);
                return true;
            }

            return false;
        } catch (\Exception $e) {
            Log::error('S3 Recording: Delete failed', ['message' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Путь файла на S3-диске (относительно root) по его публичному URL
     */
    public function pathFromUrl(string $s3Url): string
    {
        $baseUrl = rtrim(config('filesystems.disks.s3.url', ''), '/');
        $root = config('filesystems.disks.s3.root');
        if ($root) {
            $baseUrl .= '/' . trim($root, '/');
        }
        $baseUrl .= '/';

        return ltrim(str_replace($baseUrl, '', $s3Url), '/');
    }

    /**
     * Отменяет незавершённые multipart-загрузки старше $olderThanHours часов.
     * Они остаются, когда воркер убит посреди выгрузки, и занимают оплачиваемое место.
     *
     * @return int  Количество отменённых загрузок
     */
    public function abortStaleMultipartUploads(int $olderThanHours = 24): int
    {
        $client = Storage::disk('s3')->getClient();
        $bucket = config('filesystems.disks.s3.bucket');
        $prefix = trim((string) config('filesystems.disks.s3.root'), '/');
        $prefix = ($prefix !== '' ? $prefix . '/' : '') . 'recordings/';
        $cutoff = now()->subHours($olderThanHours);
        $aborted = 0;

        $pages = $client->getPaginator('ListMultipartUploads', [
            'Bucket' => $bucket,
            'Prefix' => $prefix,
        ]);

        foreach ($pages as $page) {
            foreach ($page['Uploads'] ?? [] as $upload) {
                if ($cutoff->lt($upload['Initiated'])) {
                    continue;
                }

                try {
                    $client->abortMultipartUpload([
                        'Bucket' => $bucket,
                        'Key' => $upload['Key'],
                        'UploadId' => $upload['UploadId'],
                    ]);
                    $aborted++;
                } catch (\Throwable $e) {
                    Log::warning('S3 Recording: Failed to abort multipart upload', [
                        'key' => $upload['Key'],
                        'message' => $e->getMessage(),
                    ]);
                }
            }
        }

        return $aborted;
    }

    /**
     * Удаляет временные файлы записей, брошенные убитыми воркерами.
     *
     * @return int  Количество удалённых файлов
     */
    public function cleanupStaleTempFiles(int $olderThanHours = 6): int
    {
        $deleted = 0;
        $cutoff = now()->subHours($olderThanHours)->getTimestamp();

        foreach (glob(storage_path('app/temp/recording_*.m4v')) ?: [] as $file) {
            if (filemtime($file) < $cutoff && @unlink($file)) {
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * S3-диск, который бросает исключения вместо молчаливого false.
     */
    protected function throwingDisk(): \Illuminate\Contracts\Filesystem\Filesystem
    {
        return Storage::build(array_merge(config('filesystems.disks.s3'), ['throw' => true]));
    }

    /**
     * Check if S3 disk is configured
     */
    public function isConfigured(): bool
    {
        try {
            $key = config('filesystems.disks.s3.key');
            $bucket = config('filesystems.disks.s3.bucket');
            return !empty($key) && !empty($bucket);
        } catch (\Exception $e) {
            return false;
        }
    }
}
