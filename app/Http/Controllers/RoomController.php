<?php

namespace App\Http\Controllers;

use App\Models\Room;
use Illuminate\Http\Request;
use JoisarJignesh\Bigbluebutton\Facades\Bigbluebutton;

class RoomController extends Controller
{
    public function start(Room $room)
    {
        if ($room->user_id !== auth()->id()) {
            abort(403);
        }

        // Apply Custom BBB Settings if available
        $user = auth()->user();
        if ($user->bbb_url && $user->bbb_secret) {
            config([
                'bigbluebutton.BBB_SERVER_BASE_URL' => $user->bbb_url,
                'bigbluebutton.BBB_SECURITY_SALT' => $user->bbb_secret,
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

        // Check if meeting is running
        if (!Bigbluebutton::isMeetingRunning(['meetingID' => $room->meeting_id])) {

            // Prepare presentations (will be used on production only)
            $presentationFiles = [];
            if ($room->presentations) {
                $presentationFiles = $room->presentations;
            }

            // Create meeting parameters
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
                'welcome' => $room->welcome ?? '',

                // Apply global settings
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
            } else {
                $createParams['logoutURL'] = route('filament.app.resources.rooms.index');
            }

            // Only upload presentations if not running on localhost
            // On production with a public domain, presentations will be uploaded automatically
            $appUrl = config('app.url');
            $isLocalhost = str_contains($appUrl, '127.0.0.1') || str_contains($appUrl, 'localhost');

            if (!$isLocalhost) {
                // Use URL method for production servers
                $presentationUrls = [];

                // Always add default welcome presentation first
                $defaultPresentation = storage_path('app/public/defaults/welcome.png');
                if (file_exists($defaultPresentation)) {
                    $presentationUrls[] = [
                        'link' => url('storage/defaults/welcome.png'),
                        'fileName' => 'welcome.png',
                    ];
                }

                // Add user-uploaded presentations
                if (!empty($presentationFiles)) {
                    foreach ($room->presentations as $path) {
                        $fullPath = storage_path('app/public/' . $path);
                        if (file_exists($fullPath)) {
                            $presentationUrls[] = [
                                'link' => url('storage/' . $path),
                                'fileName' => basename($path),
                            ];
                        }
                    }
                }

                if (!empty($presentationUrls)) {
                    $createParams['presentation'] = $presentationUrls;
                }

                \Illuminate\Support\Facades\Log::info('Creating BBB meeting with presentations', [
                    'meetingID' => $room->meeting_id,
                    'presentation_count' => count($presentationUrls),
                ]);
            } elseif ($isLocalhost) {
                \Illuminate\Support\Facades\Log::info('Skipping presentation upload on localhost', [
                    'meetingID' => $room->meeting_id,
                    'message' => 'Presentations will be uploaded automatically on production server',
                ]);
            }

            $response = Bigbluebutton::create($createParams);
            $internalMeetingId = $response['internalMeetingID'] ?? null;

            \App\Models\MeetingSession::create([
                'user_id' => auth()->id(),
                'room_id' => $room->id,
                'meeting_id' => $room->meeting_id,
                'internal_meeting_id' => $internalMeetingId,
                'started_at' => now(),
                'status' => 'running',
                'settings_snapshot' => $createParams,
            ]);

            $room->update(['is_running' => true]);
            \App\Events\RoomStatusUpdated::dispatch();

            // Register Webhook for Analytics
            try {
                $webhookUrl = route('api.bbb.webhook');

                // If localhost, we might need a tunnel URL or just log a warning
                $appUrl = config('app.url');
                if (str_contains($appUrl, '127.0.0.1') || str_contains($appUrl, 'localhost')) {
                    \Illuminate\Support\Facades\Log::warning('BBB Webhook: Skipping registration on localhost.', ['url' => $webhookUrl]);
                } else {
                    Bigbluebutton::hooksCreate([
                        'meetingID' => $room->meeting_id,
                        'callbackURL' => $webhookUrl,
                        'getRaw' => true, // We want all events
                    ]);
                    \Illuminate\Support\Facades\Log::info('BBB Webhook: Registered successfully.', ['url' => $webhookUrl]);
                }
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('BBB Webhook: Failed to register.', ['error' => $e->getMessage()]);
            }
        }

        return redirect()->to(
            Bigbluebutton::join([
                'meetingID' => $room->meeting_id,
                'userName' => auth()->user()->name,
                'password' => $room->moderator_pw, // Owner is moderator
                'userID' => (string) auth()->id(),
                'avatarURL' => auth()->user()->avatar ? asset('storage/' . auth()->user()->avatar) : null,
            ])
        );
    }

    public function join(Room $room)
    {
        // Ideally checking running state, or letting BBB handle "meeting not found"
        // But for better UX, check if running

        // Apply Custom BBB Settings if available (from Room owner)
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

        if (!Bigbluebutton::isMeetingRunning(['meetingID' => $room->meeting_id])) {
            return back()->with('error', 'Meeting is not running.');
        }

        $password = $room->user_id === auth()->id() ? $room->moderator_pw : $room->attendee_pw;

        return redirect()->to(
            Bigbluebutton::join([
                'meetingID' => $room->meeting_id,
                'userName' => auth()->user()->name,
                'password' => $password,
                'userID' => (string) auth()->id(),
                'avatarURL' => auth()->user()->avatar ? asset('storage/' . auth()->user()->avatar) : null,
            ])
        );
    }

    public function stop(Room $room)
    {
        // Check Authorization (Room Owner or Admin)
        if ($room->user_id !== auth()->id() && !auth()->user()->hasRole('admin')) {
            abort(403);
        }

        // Apply Custom BBB Settings if available
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

        // Capture participant count and analytics before closing (includes moderator)
        $participantCount = 0;
        $analyticsData = null;

        \Illuminate\Support\Facades\Log::info('Attempting to capture analytics for meeting: ' . $room->meeting_id);

        try {
            $info = Bigbluebutton::getMeetingInfo(['meetingID' => $room->meeting_id]);

            \Illuminate\Support\Facades\Log::info('BBB getMeetingInfo response', [
                'meeting_id' => $room->meeting_id,
                'info_type' => gettype($info),
                'info_data' => $info,
            ]);

            if ($info && isset($info['participantCount'])) {
                // BBB's participantCount already includes all users (moderators + attendees)
                $participantCount = (int) $info['participantCount'];

                \Illuminate\Support\Facades\Log::info('Analytics captured successfully', [
                    'participant_count' => $participantCount,
                    'has_attendees' => isset($info['attendees']),
                ]);

                // Store detailed analytics
                $analyticsData = [
                    'meeting_name' => $info['meetingName'] ?? $room->name,
                    'create_time' => isset($info['createTime']) ? (int) $info['createTime'] : null,
                    'voice_participant_count' => $info['voiceParticipantCount'] ?? 0,
                    'video_count' => $info['videoCount'] ?? 0,
                    'moderator_count' => $info['moderatorCount'] ?? 0,
                    'attendee_count' => $info['attendeeCount'] ?? 0,
                    'listener_count' => $info['listenerCount'] ?? 0,
                    'participant_count' => $participantCount,
                    'metadata' => $info['metadata'] ?? [],
                    'participants' => [],
                ];

                // Extract participant details if available
                if (isset($info['attendees']) && is_array($info['attendees'])) {
                    foreach ($info['attendees'] as $attendee) {
                        $analyticsData['participants'][] = [
                            'user_id' => $attendee['userID'] ?? null,
                            'full_name' => $attendee['fullName'] ?? 'Unknown',
                            'role' => $attendee['role'] ?? 'VIEWER',
                            'is_presenter' => $attendee['isPresenter'] ?? false,
                            'is_listening_only' => $attendee['isListeningOnly'] ?? false,
                            'has_joined_voice' => $attendee['hasJoinedVoice'] ?? false,
                            'has_video' => $attendee['hasVideo'] ?? false,
                        ];
                    }
                }
            } else {
                \Illuminate\Support\Facades\Log::warning('No participant data in BBB response', [
                    'meeting_id' => $room->meeting_id,
                    'info' => $info,
                ]);
            }
        } catch (\Exception $e) {
            // Ignore error if meeting already closed or unreachable
            \Illuminate\Support\Facades\Log::warning('Failed to capture analytics: ' . $e->getMessage(), [
                'meeting_id' => $room->meeting_id,
                'exception' => get_class($e),
            ]);
        }

        Bigbluebutton::close([
            'meetingID' => $room->meeting_id,
            'moderatorPW' => $room->moderator_pw,
        ]);

        $room->update(['is_running' => false]);
        \App\Events\RoomStatusUpdated::dispatch();

        \App\Models\MeetingSession::where('room_id', $room->id)
            ->where('meeting_id', $room->meeting_id)
            ->where('status', 'running')
            ->orderByDesc('started_at')
            ->first()
                ?->update([
                'ended_at' => now(),
                'status' => 'completed',
                'participant_count' => max($participantCount, 1), // At least the creator
                'analytics_data' => $analyticsData,
            ]);

        return back()->with('success', 'Meeting stopped successfully.');
    }
}
