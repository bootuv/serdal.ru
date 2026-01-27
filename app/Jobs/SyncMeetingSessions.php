<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\MeetingSession;
use JoisarJignesh\Bigbluebutton\Facades\Bigbluebutton;
use Illuminate\Support\Facades\Log;

class SyncMeetingSessions implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public ?int $userId = null)
    {
    }

    public function handle(): void
    {
        $query = MeetingSession::where('status', 'running');

        if ($this->userId) {
            $query->where('user_id', $this->userId);
        }

        $runningSessions = $query->get();

        foreach ($runningSessions as $session) {
            try {
                // Apply BBB config
                $this->applyBBBConfig($session->room);

                $isRunning = Bigbluebutton::isMeetingRunning([
                    'meetingID' => $session->meeting_id
                ]);

                if (!$isRunning) {
                    $session->update([
                        'status' => 'completed',
                        'ended_at' => now(),
                        'pricing_snapshot' => $session->capturePricingSnapshot(),
                    ]);

                    if ($session->room) {
                        $session->room->update(['is_running' => false]);
                    }

                    Log::info('Session auto-completed via async sync', [
                        'session_id' => $session->id,
                        'meeting_id' => $session->meeting_id,
                    ]);
                }
            } catch (\Exception $e) {
                Log::warning('Async BBB sync failed for session', [
                    'session_id' => $session->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    protected function applyBBBConfig($room): void
    {
        if (!$room)
            return;

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
