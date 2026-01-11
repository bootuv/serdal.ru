<?php

namespace App\Filament\Resources\MeetingSessionResource\Pages;

use App\Filament\Resources\MeetingSessionResource;
use Filament\Resources\Pages\ListRecords;

class ListMeetingSessions extends ListRecords
{
    protected static string $resource = MeetingSessionResource::class;

    public function mount(): void
    {
        parent::mount();
        $this->syncRunningSessions();
    }

    protected function syncRunningSessions(): void
    {
        $runningSessions = \App\Models\MeetingSession::where('status', 'running')->get();

        foreach ($runningSessions as $session) {
            try {
                // Apply BBB config from room owner
                $this->applyBBBConfig($session->room);

                // Check if meeting is still running
                $isRunning = \JoisarJignesh\Bigbluebutton\Facades\Bigbluebutton::isMeetingRunning([
                    'meetingID' => $session->meeting_id
                ]);

                if (!$isRunning) {
                    // Meeting ended, update session with pricing snapshot
                    $session->update([
                        'status' => 'completed',
                        'ended_at' => now(),
                        'pricing_snapshot' => $session->capturePricingSnapshot(),
                    ]);

                    // Also update room status
                    $session->room->update(['is_running' => false]);

                    \Illuminate\Support\Facades\Log::info('Session auto-completed via sync', [
                        'session_id' => $session->id,
                        'meeting_id' => $session->meeting_id,
                    ]);
                }
            } catch (\Exception $e) {
                // Log error but continue with other sessions
                \Illuminate\Support\Facades\Log::warning('BBB sync failed for session', [
                    'session_id' => $session->id,
                    'error' => $e->getMessage(),
                ]);
            }
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
