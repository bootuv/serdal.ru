<?php

namespace App\Filament\App\Resources\RecordingResource\Pages;

use App\Filament\App\Resources\RecordingResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRecordings extends ListRecords
{
    protected static string $resource = RecordingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // No manual create action
        ];
    }

    public function getListeners(): array
    {
        return [
            "echo:recordings,.recording.updated" => '$refresh',
            "echo:recordings,recording.updated" => '$refresh',
            "echo:recordings,RecordingUpdated" => '$refresh',
        ];
    }

    public function mount(): void
    {
        try {
            // Configure BBB based on User custom settings
            $user = auth()->user();
            if ($user->bbb_url && $user->bbb_secret) {
                config([
                    'bigbluebutton.BBB_SERVER_BASE_URL' => $user->bbb_url,
                    'bigbluebutton.BBB_SECURITY_SALT' => $user->bbb_secret,
                ]);
            } else {
                // Fallback to global
                $globalUrl = \App\Models\Setting::where('key', 'bbb_url')->value('value');
                $globalSecret = \App\Models\Setting::where('key', 'bbb_secret')->value('value');
                if ($globalUrl && $globalSecret) {
                    config([
                        'bigbluebutton.BBB_SERVER_BASE_URL' => $globalUrl,
                        'bigbluebutton.BBB_SECURITY_SALT' => $globalSecret,
                    ]);
                }
            }

            // Get All Recordings (including processing state - like Greenlight)
            $userRoomIds = \App\Models\Room::where('user_id', auth()->id())->pluck('meeting_id')->filter()->toArray();

            if (!empty($userRoomIds)) {
                // Get both published and processing recordings
                $response = \JoisarJignesh\Bigbluebutton\Facades\Bigbluebutton::getRecordings(['state' => 'any']);
                $recs = collect($response);

                \Log::info('BBB Sync: Raw response count', ['count' => $recs->count(), 'user_room_ids' => $userRoomIds]);

                // Collect BBB record IDs for cleanup comparison
                $bbbRecordIds = [];

                foreach ($recs as $rec) {
                    $r = (array) $rec;
                    // Only import if it belongs to one of our rooms
                    if (in_array($r['meetingID'], $userRoomIds)) {
                        $isPublished = $r['published'] === 'true' || $r['published'] === true;
                        $startTime = isset($r['startTime']) ? \Carbon\Carbon::createFromTimestamp($r['startTime'] / 1000) : null;

                        // Filter out "zombie" recordings:
                        // 1. If state is 'deleted' (already deleted on BBB but still returned by API)
                        // 2. If not published AND older than 24 hours (stuck processing)
                        $state = $r['state'] ?? 'unknown';
                        if ($state === 'deleted' || (!$isPublished && (!$startTime || $startTime->lt(now()->subHours(24))))) {
                            continue;
                        }

                        $bbbRecordIds[] = $r['recordID'];
                        \Log::info('BBB Sync: Found recording', ['recordID' => $r['recordID'], 'meetingID' => $r['meetingID']]);

                        $recording = \App\Models\Recording::updateOrCreate(
                            ['record_id' => $r['recordID']],
                            [
                                'meeting_id' => $r['meetingID'],
                                'name' => $r['name'],
                                'published' => $r['published'] === 'true' || $r['published'] === true,
                                'start_time' => isset($r['startTime']) ? \Carbon\Carbon::createFromTimestamp($r['startTime'] / 1000) : null,
                                'end_time' => isset($r['endTime']) ? \Carbon\Carbon::createFromTimestamp($r['endTime'] / 1000) : null,
                                'participants' => $r['participants'] ?? 0,
                                'url' => isset($r['playback']['format']['url']) ? $r['playback']['format']['url'] : (isset($r['playback']['format'][0]['url']) ? $r['playback']['format'][0]['url'] : null),
                                'raw_data' => $r,
                            ]
                        );

                        // Dispatch VK upload if enabled and not yet uploaded
                        // ONLY for new recordings (start_time within last 24 hours)
                        // This prevents re-uploading old legacy recordings on every page refresh
                        $vkAutoUpload = \App\Models\Setting::where('key', 'vk_auto_upload')->value('value') === '1';

                        $isRecent = $recording->start_time && \Carbon\Carbon::parse($recording->start_time)->gt(now()->subHours(2));

                        if ($vkAutoUpload && !$recording->vk_video_id && $recording->url && $isRecent) {
                            // Check if job is not already dispatched (basic check)
                            // Ideally use a 'status' column, but for now just rely on creation time
                            $room = \App\Models\Room::where('meeting_id', $r['meetingID'])->first();
                            if ($room && $room->user) {
                                \App\Jobs\UploadRecordingToVk::dispatch($recording, $room->user);
                                \Log::info('VK Upload: Dispatched job', ['recording_id' => $recording->id, 'teacher' => $room->user->name]);
                            }
                        }
                    }
                }

                \Log::info('BBB Sync: IDs from BBB', ['bbbRecordIds' => $bbbRecordIds]);

                // Delete local recordings that no longer exist on BBB
                // BUT keep recordings that were already uploaded to VK (intentionally deleted from BBB)
                // AND keep placeholder recordings (recently created, not yet on BBB)
                $toDelete = \App\Models\Recording::whereIn('meeting_id', $userRoomIds)
                    ->whereNotIn('record_id', $bbbRecordIds)
                    ->whereNull('vk_video_id') // Don't delete if already uploaded to VK
                    ->where('record_id', 'not like', '%-placeholder-%') // Don't delete placeholders
                    ->pluck('record_id');

                \Log::info('BBB Sync: Recordings to delete', ['toDelete' => $toDelete->toArray()]);

                \App\Models\Recording::whereIn('meeting_id', $userRoomIds)
                    ->whereNotIn('record_id', $bbbRecordIds)
                    ->whereNull('vk_video_id')
                    ->where('record_id', 'not like', '%-placeholder-%')
                    ->delete();
            }
        } catch (\Throwable $e) {
            // Silent fail or log
            \Illuminate\Support\Facades\Log::error('Recording Sync Error: ' . $e->getMessage());
        }

        parent::mount();
    }
}
