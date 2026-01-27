<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\User;
use App\Models\Room;
use App\Events\RoomStatusUpdated;
use Illuminate\Support\Facades\Log;

class SyncUserBbbStatus implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The user to sync.
     */
    protected $userId;

    /**
     * Create a new job instance.
     */
    public function __construct(int $userId)
    {
        $this->userId = $userId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $user = User::find($this->userId);
        if (!$user)
            return;

        $start = microtime(true);
        Log::info("[Job] SyncUserBbbStatus STARTED for user {$this->userId}");

        // 1. Configure BBB
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
        try {
            $meetings = \JoisarJignesh\Bigbluebutton\Facades\Bigbluebutton::getMeetings();

            Log::info("[Job] SyncUserBbbStatus Raw meetings count: " . $meetings->count());
            if ($meetings->count() > 0) {
                Log::info("[Job] First meeting structure: " . json_encode($meetings->first()));
            }

            $runningMeetingIds = [];
            if ($meetings->count() > 0) {
                foreach ($meetings as $meeting) {
                    $m = (array) $meeting;
                    if (isset($m['meetingID'])) {
                        $runningMeetingIds[] = $m['meetingID'];
                    }
                }
            }

            // 3. Update Database
            // Set running = true for matches
            $updatedRunning = Room::where('user_id', $user->id)
                ->whereIn('meeting_id', $runningMeetingIds)
                ->where('is_running', false) // Only update if changed
                ->update(['is_running' => true]);

            // Detect rooms that stopped
            $stoppedRooms = Room::where('user_id', $user->id)
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

            // Fire event if anything changed
            if ($updatedRunning > 0 || $updatedStopped > 0) {
                event(new RoomStatusUpdated());
                Log::info("[Job] Fired RoomStatusUpdated event");
            }

            Log::info("[Job] SyncUserBbbStatus FINISHED in " . (microtime(true) - $start) . "s. Running: $updatedRunning, Stopped: $updatedStopped");

        } catch (\Throwable $e) {
            Log::error("[Job] BBB Sync Error: " . $e->getMessage());
        }
    }
}
