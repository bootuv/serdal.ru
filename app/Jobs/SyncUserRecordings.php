<?php

namespace App\Jobs;

use App\Models\Recording;
use App\Models\Room;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use JoisarJignesh\Bigbluebutton\Facades\Bigbluebutton;

class SyncUserRecordings implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 120; // Allow enough time for API and DB ops

    /**
     * Create a new job instance.
     */
    public function __construct(
        public User $user
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Configure BBB based on User custom settings
            if ($this->user->bbb_url && $this->user->bbb_secret) {
                config([
                    'bigbluebutton.BBB_SERVER_BASE_URL' => $this->user->bbb_url,
                    'bigbluebutton.BBB_SECURITY_SALT' => $this->user->bbb_secret,
                ]);
            } else {
                // Fallback to global
                $globalUrl = Setting::where('key', 'bbb_url')->value('value');
                $globalSecret = Setting::where('key', 'bbb_secret')->value('value');
                if ($globalUrl && $globalSecret) {
                    config([
                        'bigbluebutton.BBB_SERVER_BASE_URL' => $globalUrl,
                        'bigbluebutton.BBB_SECURITY_SALT' => $globalSecret,
                    ]);
                }
            }

            // Get User's Room Meeting IDs
            $userRoomIds = Room::where('user_id', $this->user->id)->pluck('meeting_id')->filter()->toArray();

            if (empty($userRoomIds)) {
                return;
            }

            // Get 'published' and 'processing' recordings from BBB.
            // Exclude 'deleted' and 'unpublished' so they don't reappear after being removed.
            $response = Bigbluebutton::getRecordings(['state' => 'published,processing']);
            $recs = collect($response);

            Log::info('SyncUserRecordings: Raw response count', ['count' => $recs->count(), 'user_id' => $this->user->id]);

            // Collect BBB record IDs for cleanup comparison
            $bbbRecordIds = [];

            foreach ($recs as $rec) {
                $r = (array) $rec;
                // Only import if it belongs to one of our rooms
                if (in_array($r['meetingID'], $userRoomIds)) {
                    $isPublished = ($r['published'] === 'true' || $r['published'] === true);
                    $startTime = isset($r['startTime']) ? \Carbon\Carbon::createFromTimestamp($r['startTime'] / 1000) : null;

                    // Filter out "zombie" recordings:
                    // 1. If state is 'deleted' or 'unpublished'
                    // 2. If not published AND older than 24 hours (stuck processing)
                    $state = $r['state'] ?? 'unknown';
                    if (in_array($state, ['deleted', 'unpublished']) || (!$isPublished && (!$startTime || $startTime->lt(now()->subHours(24))))) {
                        continue;
                    }

                    $bbbRecordIds[] = $r['recordID'];

                    // Determine best playback URL (prefer video if mp4 exists)
                    $playbackUrl = $this->getBestPlaybackUrl($r['playback'] ?? []);

                    $recording = Recording::updateOrCreate(
                        ['record_id' => $r['recordID']],
                        [
                            'meeting_id' => $r['meetingID'],
                            'name' => $r['name'],
                            'published' => $isPublished,
                            'start_time' => $startTime,
                            'end_time' => isset($r['endTime']) ? \Carbon\Carbon::createFromTimestamp($r['endTime'] / 1000) : null,
                            'participants' => $r['participants'] ?? 0,
                            'url' => $playbackUrl ? trim($playbackUrl) : null,
                            'raw_data' => $r,
                        ]
                    );

                    // Cleanup placeholder if exists
                    Recording::where('meeting_id', $r['meetingID'])
                        ->where('record_id', 'like', '%-placeholder-%')
                        ->delete();

                    // Dispatch S3 upload if enabled and not yet uploaded
                    $autoUpload = Setting::where('key', 'recording_auto_upload')->value('value') === '1';
                    $isRecent = $recording->start_time && \Carbon\Carbon::parse($recording->start_time)->gt(now()->subHours(2));

                    if ($autoUpload && !$recording->s3_url && !$recording->vk_video_id && $recording->url && $isRecent) {
                        $room = Room::where('meeting_id', $r['meetingID'])->first();
                        if ($room && $room->user) {
                            UploadRecordingToStorage::dispatch($recording, $room->user);
                        }
                    }
                }
            }

            // Delete local recordings that no longer exist on BBB
            // But preserve VK-uploaded ones and recent placeholders
            Recording::whereIn('meeting_id', $userRoomIds)
                ->whereNotIn('record_id', $bbbRecordIds)
                ->whereNull('vk_video_id')
                ->whereNull('s3_url')
                ->where('record_id', 'not like', '%-placeholder-%')
                ->delete();

        } catch (\Throwable $e) {
            Log::error('SyncUserRecordings Error: ' . $e->getMessage(), ['user_id' => $this->user->id]);
            // Re-throw to allow retry? Or just log? 
            // Usually sync jobs can just fail and run again later.
        }
    }

    /**
     * Get the best playback URL from available formats.
     * Prefers video format only if the mp4 file actually exists.
     */
    protected function getBestPlaybackUrl(array $playback): ?string
    {
        $formats = $playback['format'] ?? [];

        // Normalize to array of formats
        if (isset($formats['url'])) {
            // Single format
            $formats = [$formats];
        }

        if (empty($formats)) {
            return null;
        }

        $videoUrl = null;
        $presentationUrl = null;

        foreach ($formats as $format) {
            $type = $format['type'] ?? '';
            $url = $format['url'] ?? null;

            if ($type === 'video' && $url) {
                $videoUrl = $url;
            } elseif ($type === 'presentation' && $url) {
                $presentationUrl = $url;
            }
        }

        // If we have a video URL, verify the mp4 file exists
        if ($videoUrl) {
            $mp4Url = rtrim($videoUrl, '/') . '/video-0.m4v';
            if ($this->urlExists($mp4Url)) {
                return $videoUrl;
            }
            Log::info('SyncUserRecordings: Video format exists but mp4 file not found', ['video_url' => $videoUrl]);
        }

        // Fall back to presentation URL or any available URL
        return $presentationUrl ?? $videoUrl ?? ($formats[0]['url'] ?? null);
    }

    /**
     * Check if a URL exists (returns 200 OK).
     */
    protected function urlExists(string $url): bool
    {
        try {
            $context = stream_context_create([
                'http' => [
                    'method' => 'HEAD',
                    'timeout' => 5,
                    'ignore_errors' => true,
                ],
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ],
            ]);

            $headers = @get_headers($url, 0, $context);
            return $headers && strpos($headers[0], '200') !== false;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
