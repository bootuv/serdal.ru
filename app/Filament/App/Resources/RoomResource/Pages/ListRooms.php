<?php

namespace App\Filament\App\Resources\RoomResource\Pages;

use App\Filament\App\Resources\RoomResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRooms extends ListRecords
{
    protected static string $resource = RoomResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    public function getListeners(): array
    {
        return [
            "echo:rooms,.room.status.updated" => '$refresh',
        ];
    }

    public function mount(): void
    {
        // 1. Configure BBB
        $user = auth()->user();
        if ($user->bbb_url && $user->bbb_secret) {
            config([
                'bigbluebutton.BBB_SERVER_BASE_URL' => $user->bbb_url,
                'bigbluebutton.BBB_SECURITY_SALT' => $user->bbb_secret,
            ]);
        } else {
            $globalUrl = \App\Models\Setting::where('key', 'bbb_url')->value('value');
            $globalSecret = \App\Models\Setting::where('key', 'bbb_secret')->value('value');
            if ($globalUrl && $globalSecret) {
                config([
                    'bigbluebutton.BBB_SERVER_BASE_URL' => $globalUrl,
                    'bigbluebutton.BBB_SECURITY_SALT' => $globalSecret,
                ]);
            }
        }

        // 2. Fetch Running Meetings
        // Bigbluebutton::all() returns a Collection of meetings
        try {
            $response = \JoisarJignesh\Bigbluebutton\Facades\Bigbluebutton::all();
            \Illuminate\Support\Facades\Log::info('BBB Sync Response (App):', ['response' => $response]);
            $meetings = collect($response);

            $runningMeetingIds = [];
            if ($meetings->count() > 0) {
                foreach ($meetings as $meeting) {
                    if (isset($meeting['meetingID'])) {
                        $runningMeetingIds[] = $meeting['meetingID'];
                    }
                }
            }

            // 3. Update Database for THIS USER
            // Set running = true for matches
            \App\Models\Room::where('user_id', $user->id)
                ->whereIn('meeting_id', $runningMeetingIds)
                ->update(['is_running' => true]);

            // Detect rooms that JUST stopped (were running, now not)
            $stoppedRooms = \App\Models\Room::where('user_id', $user->id)
                ->where('is_running', true)
                ->whereNotIn('meeting_id', $runningMeetingIds)
                ->get();

            foreach ($stoppedRooms as $room) {
                // Close session
                \App\Models\MeetingSession::where('room_id', $room->id)
                    ->where('meeting_id', $room->meeting_id)
                    ->where('status', 'running')
                    ->orderByDesc('started_at')
                    ->first()
                        ?->update([
                        'ended_at' => now(),
                        'status' => 'completed',
                    ]);

                $room->update(['is_running' => false]);
            }

        } catch (\Throwable $e) {
            \Filament\Notifications\Notification::make()
                ->title('BBB Sync Error')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }

        parent::mount();
    }
}
