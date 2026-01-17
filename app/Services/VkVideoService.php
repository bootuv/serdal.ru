<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Models\Setting;

class VkVideoService
{
    protected string $accessToken;
    protected ?int $groupId;
    protected string $apiVersion = '5.199';

    public function __construct()
    {
        $this->accessToken = Setting::where('key', 'vk_access_token')->value('value') ?? '';
        $this->groupId = Setting::where('key', 'vk_group_id')->value('value');
    }

    /**
     * Upload video to VK
     */
    public function uploadVideo(string $videoUrl, string $name, string $description = '', ?int $albumId = null): ?array
    {
        if (empty($this->accessToken)) {
            Log::error('VK Video: access_token not configured');
            return null;
        }

        $tempPath = null;

        try {
            // Step 1: Get upload URL via video.save (WITHOUT link parameter for direct upload)
            $saveParams = [
                'access_token' => $this->accessToken,
                'v' => $this->apiVersion,
                'name' => mb_substr($name, 0, 128),
                'description' => mb_substr($description, 0, 5000),
                'is_private' => 0, // Public
                'wallpost' => 0, // Don't post to wall
            ];

            if ($this->groupId) {
                $saveParams['group_id'] = $this->groupId;
            }

            if ($albumId) {
                $saveParams['album_id'] = $albumId;
            }

            Log::info('VK Video: Calling video.save', ['name' => $name]);

            $response = Http::get('https://api.vk.com/method/video.save', $saveParams);
            $data = $response->json();

            if (isset($data['error'])) {
                Log::error('VK Video: video.save error', ['error' => $data['error']]);
                return null;
            }

            $uploadUrl = $data['response']['upload_url'] ?? null;
            $videoId = $data['response']['video_id'] ?? null;
            $ownerId = $data['response']['owner_id'] ?? null;

            if (!$uploadUrl) {
                Log::error('VK Video: No upload_url in response');
                return null;
            }

            // Step 2: Download the video file locally
            Log::info('VK Video: Downloading video file...', ['url' => $videoUrl]);

            // Create a temp file
            $tempFile = 'vk_upload_' . uniqid() . '.mp4';
            $tempPath = storage_path('app/temp/' . $tempFile);

            // Ensure temp directory exists
            if (!file_exists(dirname($tempPath))) {
                mkdir(dirname($tempPath), 0755, true);
            }

            // Download using sink to save directly to file implies minimal memory usage
            $dlResponse = Http::sink($tempPath)->get($videoUrl);

            if ($dlResponse->failed()) {
                Log::error('VK Video: Failed to download video file', ['status' => $dlResponse->status()]);
                return null;
            }

            Log::info('VK Video: File downloaded, uploading to VK...', ['size' => filesize($tempPath)]);

            // Step 3: Upload file to VK
            $uploadResponse = Http::attach(
                'video_file',
                fopen($tempPath, 'r'),
                'video.mp4'
            )->post($uploadUrl);

            $uploadData = $uploadResponse->json();

            if (isset($uploadData['error']) || isset($uploadData['error_code'])) {
                Log::error('VK Video: Upload failed', ['response' => $uploadData]);
                return null;
            }

            Log::info('VK Video: Upload complete', [
                'video_id' => $videoId,
                'owner_id' => $ownerId,
                'response' => $uploadData
            ]);

            // Build VK video URL
            $vkVideoUrl = "https://vk.com/video{$ownerId}_{$videoId}";

            return [
                'video_id' => $videoId,
                'owner_id' => $ownerId,
                'url' => $vkVideoUrl,
            ];

        } catch (\Exception $e) {
            Log::error('VK Video: Exception', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return null;
        } finally {
            // Cleanup temp file
            if ($tempPath && file_exists($tempPath)) {
                @unlink($tempPath);
            }
        }
    }

    /**
     * Create album (playlist) for teacher
     */
    public function createAlbum(string $title): ?int
    {
        if (empty($this->accessToken)) {
            return null;
        }

        try {
            $params = [
                'access_token' => $this->accessToken,
                'v' => $this->apiVersion,
                'title' => mb_substr($title, 0, 128),
            ];

            if ($this->groupId) {
                $params['group_id'] = $this->groupId;
            }

            $response = Http::get('https://api.vk.com/method/video.addAlbum', $params);
            $data = $response->json();

            if (isset($data['error'])) {
                Log::error('VK Video: video.addAlbum error', ['error' => $data['error']]);
                return null;
            }

            return $data['response']['album_id'] ?? null;

        } catch (\Exception $e) {
            Log::error('VK Video: createAlbum exception', ['message' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Add video to album
     */
    public function addToAlbum(int $videoId, int $ownerId, int $albumId): bool
    {
        if (empty($this->accessToken)) {
            return false;
        }

        try {
            $params = [
                'access_token' => $this->accessToken,
                'v' => $this->apiVersion,
                'owner_id' => $ownerId,
                'video_id' => $videoId,
                'album_ids' => $albumId,
            ];

            if ($this->groupId) {
                $params['target_id'] = -$this->groupId;
            }

            $response = Http::get('https://api.vk.com/method/video.addToAlbum', $params);
            $data = $response->json();

            if (isset($data['error'])) {
                Log::error('VK Video: video.addToAlbum error', ['error' => $data['error']]);
                return false;
            }

            return true;

        } catch (\Exception $e) {
            Log::error('VK Video: addToAlbum exception', ['message' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Check if service is configured
     */
    public function isConfigured(): bool
    {
        return !empty($this->accessToken);
    }
}
