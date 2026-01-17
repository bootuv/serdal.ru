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

            // Get All Recordings
            $userRoomIds = \App\Models\Room::where('user_id', auth()->id())->pluck('meeting_id')->filter()->toArray();

            if (!empty($userRoomIds)) {
                $response = \JoisarJignesh\Bigbluebutton\Facades\Bigbluebutton::getRecordings(['state' => 'any']);
                $recs = collect($response);

                \Log::info('BBB Sync: Raw response count', ['count' => $recs->count(), 'user_room_ids' => $userRoomIds]);

                // Collect BBB record IDs for cleanup comparison
                $bbbRecordIds = [];

                foreach ($recs as $rec) {
                    $r = (array) $rec;
                    // Only import if it belongs to one of our rooms
                    if (in_array($r['meetingID'], $userRoomIds)) {
                        $bbbRecordIds[] = $r['recordID'];
                        \Log::info('BBB Sync: Found recording', ['recordID' => $r['recordID'], 'meetingID' => $r['meetingID']]);

                        \App\Models\Recording::updateOrCreate(
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
                    }
                }

                \Log::info('BBB Sync: IDs from BBB', ['bbbRecordIds' => $bbbRecordIds]);

                // Delete local recordings that no longer exist on BBB
                $toDelete = \App\Models\Recording::whereIn('meeting_id', $userRoomIds)
                    ->whereNotIn('record_id', $bbbRecordIds)
                    ->pluck('record_id');

                \Log::info('BBB Sync: Recordings to delete', ['toDelete' => $toDelete->toArray()]);

                \App\Models\Recording::whereIn('meeting_id', $userRoomIds)
                    ->whereNotIn('record_id', $bbbRecordIds)
                    ->delete();
            }
        } catch (\Throwable $e) {
            // Silent fail or log
            \Illuminate\Support\Facades\Log::error('Recording Sync Error: ' . $e->getMessage());
        }

        parent::mount();
    }
}
