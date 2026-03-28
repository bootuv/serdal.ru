<?php

namespace App\Jobs;

use App\Models\Recording;
use App\Services\RecordingStorageService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;

class MigrateVkRecordingToS3 implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // Timeout raised considerably since downloading chunks from VK can take time
    public $timeout = 7200; // 2 hours Max Execution Time per job
    public $tries = 3;

    public function __construct(public Recording $recording)
    {
    }

    public function handle(RecordingStorageService $storageService): void
    {
        Log::info('MigrateVkToS3: Starting migration for recording', ['id' => $this->recording->id, 'vk_url' => $this->recording->vk_video_url]);

        if (empty($this->recording->vk_video_url)) {
            Log::warning('MigrateVkToS3: No VK URL found, skipping', ['id' => $this->recording->id]);
            return;
        }

        if (!empty($this->recording->s3_url)) {
            Log::info('MigrateVkToS3: S3 URL already exists, skipping', ['id' => $this->recording->id]);
            return;
        }

        if (!$storageService->isConfigured()) {
            throw new \Exception('MigrateVkToS3: Yandex S3 missing configuration');
        }

        // Create isolated temp directory for yt-dlp to avoid conflicts
        $tempDir = storage_path('app/temp/vk_migrations');
        if (!File::exists($tempDir)) {
            File::makeDirectory($tempDir, 0755, true);
        }

        $tempFileName = 'recording_' . $this->recording->id . '_' . time();
        $tempFilePathPattern = $tempDir . '/' . $tempFileName . '.%(ext)s';

        $vkUrl = escapeshellarg($this->recording->vk_video_url);
        $outputPathPattern = escapeshellarg($tempFilePathPattern);

        // Download format explicitly
        $command = "yt-dlp --no-cache-dir -f 'bestvideo[ext=mp4]+bestaudio[ext=m4a]/best[ext=mp4]/best' -o {$outputPathPattern} {$vkUrl}";
        
        Log::info('MigrateVkToS3: Downloading from VK via yt-dlp', ['command' => $command]);
        
        $output = '';
        if (app()->runningInConsole()) {
            echo "\n-> Starting yt-dlp streaming download...\n";
            passthru($command, $resultCode);
            if ($resultCode !== 0) {
                $output = "Command failed with exit code {$resultCode}";
            }
        } else {
            $output = shell_exec($command . " 2>&1");
        }
        
        // Find the actual exported file (since extension might vary e.g. .mp4, .mkv, .webm)
        $downloadedFiles = glob($tempDir . '/' . $tempFileName . '.*');
        
        if (empty($downloadedFiles)) {
            Log::error('MigrateVkToS3: Download failed, no file produced', ['output' => $output]);
            throw new \Exception("Failed to download video from VK via yt-dlp: " . $output);
        }

        $actualFilePath = $downloadedFiles[0];

        if (File::size($actualFilePath) === 0) {
            File::delete($actualFilePath);
            Log::error('MigrateVkToS3: Download failed, file is 0 bytes', ['output' => $output]);
            throw new \Exception("Failed to download video from VK via yt-dlp, empty file");
        }

        Log::info('MigrateVkToS3: Download successful, uploading to S3...', [
            'file' => $actualFilePath,
            'size' => File::size($actualFilePath)
        ]);

        $teacherId = $this->recording->room && $this->recording->room->user ? $this->recording->room->user->id : 'orphaned';
        $s3Path = 'recordings/' . $teacherId . '/' . basename($actualFilePath);

        $s3Url = $storageService->uploadLocalFileToS3($actualFilePath, $s3Path);

        if ($s3Url) {
            $this->recording->update([
                's3_url' => $s3Url,
                's3_uploaded_at' => now(),
            ]);
            Log::info('MigrateVkToS3: Successfully migrated', ['id' => $this->recording->id, 's3_url' => $s3Url]);
        } else {
            Log::error('MigrateVkToS3: Failed to upload to S3', ['id' => $this->recording->id]);
            throw new \Exception("Failed to upload migrated video to S3");
        }

        // Cleanup temp file
        if (File::exists($actualFilePath)) {
            File::delete($actualFilePath);
        }
    }
}
