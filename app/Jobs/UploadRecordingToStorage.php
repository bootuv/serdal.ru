<?php

namespace App\Jobs;

use App\Models\Recording;
use App\Models\User;
use App\Services\RecordingStorageService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Models\Setting;
use JoisarJignesh\Bigbluebutton\Facades\Bigbluebutton;

class UploadRecordingToStorage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        public Recording $recording,
        public User $teacher
    ) {
    }

    public function handle(RecordingStorageService $storageService): void
    {
        if (!$storageService->isConfigured()) {
            Log::warning('S3 Recording: S3 not configured, skipping upload', [
                'recording_id' => $this->recording->id,
            ]);
            return;
        }

        // Skip non-video format recordings
        $url = $this->recording->url;
        if (!$url || !str_contains($url, '/playback/video/')) {
            Log::info('S3 Recording: Skipping non-video format', [
                'recording_id' => $this->recording->id,
                'url' => $url,
            ]);
            return;
        }

        // Skip if already uploaded to S3
        if ($this->recording->s3_url) {
            Log::info('S3 Recording: Already uploaded', [
                'recording_id' => $this->recording->id,
                's3_url' => $this->recording->s3_url,
            ]);
            return;
        }

        // Build video file URL - strip ALL whitespace/newlines from URL just in case
        $cleanUrl = preg_replace('/\s+/', '', $this->recording->url);
        $videoUrl = rtrim($cleanUrl, '/') . '/video-0.m4v';

        if (empty($videoUrl)) {
            Log::warning('S3 Recording: No video URL', ['recording_id' => $this->recording->id]);
            return;
        }

        // Generate filename: {record_id}.m4v
        $filename = $this->recording->record_id . '.m4v';

        // Upload to S3
        $s3Url = $storageService->uploadToS3($videoUrl, $this->teacher->id, $filename);

        if ($s3Url) {
            $this->recording->update([
                's3_url' => $s3Url,
                's3_uploaded_at' => now(),
            ]);

            Log::info('S3 Recording: Upload successful', [
                'recording_id' => $this->recording->id,
                's3_url' => $s3Url,
            ]);

            // Delete from BBB if enabled
            if (Setting::where('key', 'recording_delete_after_upload')->value('value') === '1') {
                try {
                    $globalUrl = Setting::where('key', 'bbb_url')->value('value');
                    $globalSecret = Setting::where('key', 'bbb_secret')->value('value');
                    if ($globalUrl && $globalSecret) {
                        config([
                            'bigbluebutton.BBB_SERVER_BASE_URL' => $globalUrl,
                            'bigbluebutton.BBB_SECURITY_SALT' => $globalSecret,
                        ]);
                    }

                    Log::info('S3 Recording: Deleting from BBB', [
                        'record_id' => $this->recording->record_id,
                    ]);

                    $deleteResponse = Bigbluebutton::deleteRecordings(['recordID' => $this->recording->record_id]);
                    Log::info('S3 Recording: BBB delete response', ['response' => $deleteResponse]);
                } catch (\Exception $e) {
                    Log::error('S3 Recording: Failed to delete from BBB', [
                        'record_id' => $this->recording->record_id,
                        'message' => $e->getMessage(),
                    ]);
                }
            }
        } else {
            Log::error('S3 Recording: Failed to upload', [
                'recording_id' => $this->recording->id,
            ]);
        }
    }
}
