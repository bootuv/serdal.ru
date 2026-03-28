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

            $dlResponse = Http::withoutVerifying()->timeout(600)->connectTimeout(30)->sink($tempPath)->get($videoUrl);

            if ($dlResponse->failed()) {
                Log::error('S3 Recording: Download failed', ['status' => $dlResponse->status()]);
                return null;
            }

            $fileSize = filesize($tempPath);
            Log::info('S3 Recording: File downloaded', ['size' => $fileSize]);

            // Step 2: Upload to S3
            $s3Path = "recordings/{$teacherId}/{$filename}";

            $uploaded = Storage::disk('s3')->put(
                $s3Path,
                fopen($tempPath, 'r'),
                'public'
            );

            if (!$uploaded) {
                Log::error('S3 Recording: Failed to upload to S3', ['path' => $s3Path]);
                return null;
            }

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
     * Delete recording from S3
     */
    public function deleteFromS3(string $s3Url): bool
    {
        try {
            // Extract S3 path from URL taking root into account
            $baseUrl = rtrim(config('filesystems.disks.s3.url', ''), '/');
            $root = config('filesystems.disks.s3.root');
            if ($root) {
                $baseUrl .= '/' . trim($root, '/');
            }
            $baseUrl .= '/';

            $path = str_replace($baseUrl, '', $s3Url);
            $path = ltrim($path, '/');

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
