<?php

namespace App\Jobs;

use App\Models\Recording;
use App\Models\User;
use App\Services\VkVideoService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class UploadRecordingToVk implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        public Recording $recording,
        public User $teacher
    ) {
    }

    public function handle(VkVideoService $vkService): void
    {
        if (!$vkService->isConfigured()) {
            Log::warning('VK Video: Service not configured, skipping upload', [
                'recording_id' => $this->recording->id,
            ]);
            return;
        }

        // Skip if already uploaded
        if ($this->recording->vk_video_id) {
            Log::info('VK Video: Recording already uploaded', [
                'recording_id' => $this->recording->id,
                'vk_video_id' => $this->recording->vk_video_id,
            ]);
            return;
        }

        // Get video URL from recording
        $videoUrl = $this->recording->url;

        // Fix BBB URL to point to actual file if it's the video format
        if ($videoUrl && str_contains($videoUrl, '/playback/video/')) {
            $videoUrl = rtrim($videoUrl, '/') . '/video-0.m4v';
        }
        if (empty($videoUrl)) {
            Log::warning('VK Video: No video URL for recording', [
                'recording_id' => $this->recording->id,
            ]);
            return;
        }

        // Get or create album for teacher
        $albumId = $this->teacher->vk_album_id;
        if (!$albumId) {
            $albumTitle = $this->teacher->name ?: ($this->teacher->first_name . ' ' . $this->teacher->last_name);
            $albumId = $vkService->createAlbum($albumTitle);

            if ($albumId) {
                $this->teacher->update(['vk_album_id' => $albumId]);
                Log::info('VK Video: Created album for teacher', [
                    'teacher_id' => $this->teacher->id,
                    'album_id' => $albumId,
                    'title' => $albumTitle,
                ]);
            }
        }

        // Upload video
        $videoName = $this->recording->name ?: 'Запись урока ' . $this->recording->start_time?->format('d.m.Y H:i');
        $description = "Учитель: {$this->teacher->name}\nДата: " . ($this->recording->start_time?->format('d.m.Y H:i') ?? 'Неизвестно');

        $result = $vkService->uploadVideo($videoUrl, $videoName, $description, $albumId);

        if ($result) {
            $this->recording->update([
                'vk_video_id' => $result['video_id'],
                'vk_video_url' => $result['url'],
                'vk_uploaded_at' => now(),
            ]);

            Log::info('VK Video: Recording uploaded successfully', [
                'recording_id' => $this->recording->id,
                'vk_video_id' => $result['video_id'],
                'vk_url' => $result['url'],
            ]);
        } else {
            Log::error('VK Video: Failed to upload recording', [
                'recording_id' => $this->recording->id,
            ]);
        }
    }
}
