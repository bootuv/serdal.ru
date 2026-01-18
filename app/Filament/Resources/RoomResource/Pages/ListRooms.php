<?php

namespace App\Filament\Resources\RoomResource\Pages;

use App\Filament\Resources\RoomResource;
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
            "echo:rooms,room.status.updated" => '$refresh',
            "echo:rooms,RoomStatusUpdated" => '$refresh',
        ];
    }

    public function mount(): void
    {
        // 1. Configure Global BBB Settings
        $globalUrl = \App\Models\Setting::where('key', 'bbb_url')->value('value');
        $globalSecret = \App\Models\Setting::where('key', 'bbb_secret')->value('value');
        if ($globalUrl && $globalSecret) {
            config([
                'bigbluebutton.BBB_SERVER_BASE_URL' => $globalUrl,
                'bigbluebutton.BBB_SECURITY_SALT' => $globalSecret,
            ]);
        }

        // 2. Fetch Running Meetings from GLOBAL server
        try {
            \Illuminate\Support\Facades\Log::info('BBB Sync Config URL (Admin): ' . config('bigbluebutton.BBB_SERVER_BASE_URL'));
            $response = \JoisarJignesh\Bigbluebutton\Facades\Bigbluebutton::all();
            \Illuminate\Support\Facades\Log::info('BBB Sync Response (Admin):', ['response' => $response]);
            $meetings = collect($response); // Ensure collection

            $runningMeetingIds = [];
            if ($meetings->count() > 0) {
                foreach ($meetings as $meeting) {
                    // Wrapper might return array of array or array of objects, usually array from XmlToArray
                    $m = (array) $meeting;
                    if (isset($m['meetingID'])) {
                        $runningMeetingIds[] = $m['meetingID'];
                    }
                }
            }

            // 3. Update Database for Users on Default Server
            // bbb_url is NULL OR empty OR matches global URL
            $targetUserIds = \App\Models\User::query()
                ->whereNull('bbb_url')
                ->orWhere('bbb_url', '')
                ->when($globalUrl, function ($q) use ($globalUrl) {
                    $q->orWhere('bbb_url', $globalUrl);
                })
                ->pluck('id');

            // running = true
            \App\Models\Room::whereIn('user_id', $targetUserIds)
                ->whereIn('meeting_id', $runningMeetingIds)
                ->update(['is_running' => true]);

            // running = false
            \App\Models\Room::whereIn('user_id', $targetUserIds)
                ->whereNotIn('meeting_id', $runningMeetingIds)
                ->update(['is_running' => false]);

        } catch (\Throwable $e) {
            \Filament\Notifications\Notification::make()
                ->title('Admin Sync Error')
                ->body($e->getMessage())
                ->warning()
                ->send();
        }

        parent::mount();
    }
}
