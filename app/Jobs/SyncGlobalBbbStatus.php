<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Room;
use App\Events\RoomStatusUpdated;
use Illuminate\Support\Facades\Log;

class SyncGlobalBbbStatus implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $start = microtime(true);
        Log::info("[Job] SyncGlobalBbbStatus STARTED");

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
            $response = \JoisarJignesh\Bigbluebutton\Facades\Bigbluebutton::all();
            $meetings = collect($response);

            $runningMeetingIds = [];
            if ($meetings->count() > 0) {
                foreach ($meetings as $meeting) {
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
            $updatedRunning = Room::whereIn('user_id', $targetUserIds)
                ->whereIn('meeting_id', $runningMeetingIds)
                ->where('is_running', false)
                ->update(['is_running' => true]);

            // running = false
            // Detect rooms that stopped
            $stoppedRooms = Room::whereIn('user_id', $targetUserIds)
                ->where('is_running', true)
                ->whereNotIn('meeting_id', $runningMeetingIds)
                ->get();

            $updatedStopped = 0;
            foreach ($stoppedRooms as $room) {
                // Close session
                $session = \App\Models\MeetingSession::where('room_id', $room->id)
                    ->where('meeting_id', $room->meeting_id)
                    ->where('status', 'running')
                    ->orderByDesc('started_at')
                    ->first();

                if ($session) {
                    $session->update([
                        'ended_at' => now(),
                        'status' => 'completed',
                        'pricing_snapshot' => $session->capturePricingSnapshot(),
                    ]);
                }

                $room->update(['is_running' => false]);
                $updatedStopped++;
            }

            if ($updatedRunning > 0 || $updatedStopped > 0) {
                event(new RoomStatusUpdated());
                Log::info("[Job] Fired RoomStatusUpdated event (Global)");
            }

            Log::info("[Job] SyncGlobalBbbStatus FINISHED in " . (microtime(true) - $start) . "s. Running: $updatedRunning, Stopped: $updatedStopped");

        } catch (\Throwable $e) {
            Log::error("[Job] SyncGlobalBbbStatus Error: " . $e->getMessage());
        }
    }
}
