<?php

namespace App\Console\Commands;

use App\Models\RoomSchedule;
use Illuminate\Console\Command;
use JoisarJignesh\Bigbluebutton\Facades\Bigbluebutton;

class StartScheduledMeetings extends Command
{
    protected $signature = 'meetings:start-scheduled';

    protected $description = 'Start meetings based on active schedules';

    public function handle()
    {
        $now = now();
        $schedules = RoomSchedule::where('is_active', true)
            ->with('room.user')
            ->get();

        $started = 0;

        foreach ($schedules as $schedule) {
            if ($schedule->isActiveAt($now)) {
                $room = $schedule->room;

                // Check if meeting is already running
                if ($room->is_running) {
                    continue;
                }

                try {
                    // Auto-start disabled by user request (2025-12-10)
                    // $this->startMeeting($room);
                    // $this->info("Started meeting for room: {$room->name}");
                    // $started++;
                    $this->info("Skipping auto-start for room: {$room->name} (disabled)");
                } catch (\Exception $e) {
                    $this->error("Failed to start meeting for room {$room->name}: {$e->getMessage()}");
                }
            }
        }

        $this->info("Checked {$schedules->count()} schedules, started {$started} meetings");
        return Command::SUCCESS;
    }

    private function startMeeting($room)
    {
        // Apply BBB config from room owner
        $owner = $room->user;
        if ($owner && $owner->bbb_url && $owner->bbb_secret) {
            config([
                'bigbluebutton.BBB_SERVER_BASE_URL' => $owner->bbb_url,
                'bigbluebutton.BBB_SECURITY_SALT' => $owner->bbb_secret,
            ]);
        } else {
            // Check Global Admin Settings
            $globalUrl = \App\Models\Setting::where('key', 'bbb_url')->value('value');
            $globalSecret = \App\Models\Setting::where('key', 'bbb_secret')->value('value');

            if ($globalUrl && $globalSecret) {
                config([
                    'bigbluebutton.BBB_SERVER_BASE_URL' => $globalUrl,
                    'bigbluebutton.BBB_SECURITY_SALT' => $globalSecret,
                ]);
            }
        }

        // Load global BBB settings
        $globalSettings = [
            'record' => \App\Models\Setting::where('key', 'bbb_record')->value('value') === '1',
            'auto_start_recording' => \App\Models\Setting::where('key', 'bbb_auto_start_recording')->value('value') === '1',
            'allow_start_stop_recording' => \App\Models\Setting::where('key', 'bbb_allow_start_stop_recording')->value('value') !== '0',
            'mute_on_start' => \App\Models\Setting::where('key', 'bbb_mute_on_start')->value('value') === '1',
            'webcams_only_for_moderator' => \App\Models\Setting::where('key', 'bbb_webcams_only_for_moderator')->value('value') === '1',
            'max_participants' => (int) (\App\Models\Setting::where('key', 'bbb_max_participants')->value('value') ?? 0),
            'duration' => (int) (\App\Models\Setting::where('key', 'bbb_duration')->value('value') ?? 0),
            'logout_url' => \App\Models\Setting::where('key', 'bbb_logout_url')->value('value'),
        ];

        $createParams = [
            'meetingID' => $room->meeting_id,
            'meetingName' => $room->name,
            'attendeePW' => $room->attendee_pw,
            'moderatorPW' => $room->moderator_pw,
            'welcome' => $room->welcome_msg ?? '',
            'record' => $globalSettings['record'],
            'autoStartRecording' => $globalSettings['auto_start_recording'],
            'allowStartStopRecording' => $globalSettings['allow_start_stop_recording'],
            'muteOnStart' => $globalSettings['mute_on_start'],
            'webcamsOnlyForModerator' => $globalSettings['webcams_only_for_moderator'],
            'maxParticipants' => $globalSettings['max_participants'],
            'duration' => $globalSettings['duration'],
        ];

        if (!empty($globalSettings['logout_url'])) {
            $createParams['logoutURL'] = $globalSettings['logout_url'];
        }

        // Create meeting
        Bigbluebutton::create($createParams);

        // Create session record
        \App\Models\MeetingSession::create([
            'user_id' => $room->user_id,
            'room_id' => $room->id,
            'meeting_id' => $room->meeting_id,
            'started_at' => now(),
            'status' => 'running',
            'settings_snapshot' => $createParams,
        ]);

        // Update room status
        $room->update(['is_running' => true]);

        \Illuminate\Support\Facades\Log::info('Meeting started automatically by schedule', [
            'room_id' => $room->id,
            'room_name' => $room->name,
        ]);
    }
}
