<?php

namespace App\Filament\App\Resources\MeetingSessionResource\Pages;

use App\Filament\App\Resources\MeetingSessionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMeetingSessions extends ListRecords
{
    protected static string $resource = MeetingSessionResource::class;

    public function mount(): void
    {
        parent::mount();
        $this->syncRunningSessions();
    }

    protected function getHeaderActions(): array
    {
        return [
            // Sessions are auto-created, no manual creation
        ];
    }

    protected function syncRunningSessions(): void
    {
        // Optimization: Run sync in background to avoid page load delay
        // Throttle to run max once every 30 seconds per user
        $cacheKey = 'sync_sessions_app_throttle_' . auth()->id();
        if (!\Illuminate\Support\Facades\Cache::has($cacheKey)) {
            \Illuminate\Support\Facades\Cache::put($cacheKey, true, 30);
            \App\Jobs\SyncMeetingSessions::dispatch(auth()->id());
        }
    }

    protected function applyBBBConfig($room): void
    {
        $owner = $room->user;
        if ($owner && $owner->bbb_url && $owner->bbb_secret) {
            config([
                'bigbluebutton.BBB_SERVER_BASE_URL' => $owner->bbb_url,
                'bigbluebutton.BBB_SECURITY_SALT' => $owner->bbb_secret,
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
    }
}
