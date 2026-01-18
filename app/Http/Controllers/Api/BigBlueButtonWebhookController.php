<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MeetingSession;
use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BigBlueButtonWebhookController extends Controller
{
    public function __invoke(Request $request)
    {
        Log::info('BBB Webhook: Incoming Request', [
            'ip' => $request->ip(),
            'headers' => $request->headers->all(),
            'content' => $request->getContent()
        ]);

        $payload = $request->input('event');

        if (is_string($payload)) {
            $payload = json_decode($payload, true);
        }

        // If the top level input is the array of events
        if (!$payload && $request->isJson()) {
            $payload = $request->json()->all();
            if (isset($payload['event'])) {
                $payload = $payload['event'];
            }
        }

        if (empty($payload)) {
            Log::warning('BBB Webhook: No payload found', ['content' => $request->getContent()]);
            return response()->json(['message' => 'No payload'], 400);
        }

        // Normalize to array of events
        if (isset($payload[0]) && is_array($payload[0])) {
            $events = $payload;
        } else {
            $events = [$payload];
        }

        foreach ($events as $event) {
            $data = $event['event'] ?? $event;

            // Determine Event Type
            $type = null;
            if (isset($data['data']['id'])) {
                $type = $data['data']['id']; // New format: data.id
            } elseif (isset($data['type'])) {
                $type = $data['type'];
            } elseif (isset($data['header']['name'])) {
                $type = $data['header']['name'];
            }

            Log::info("BBB Webhook: Detected event type: " . ($type ?? 'unknown'), ['data' => $data]);

            if ($type === 'meeting-ended') {
                $this->handleMeetingEnded($data);
            } elseif ($type === 'user-joined') {
                $this->handleUserJoined($data);
            } elseif ($type === 'user-left') {
                $this->handleUserLeft($data);
            } elseif ($type === 'user-audio-voice-enabled' || $type === 'user-audio-voice-disabled') {
                $this->handleUserAudio($data, $type);
            } elseif ($type === 'user-cam-broadcast-started' || $type === 'user-cam-broadcast-stopped') {
                $this->handleUserCam($data, $type);
            } elseif ($type === 'chat-group-message-sent') {
                $this->handleChatMessage($data);
            } elseif ($type === 'user-emoji-changed') { // or user-reaction-emoji-changed depending on version, generic catch?
                // Note: 'user-emoji-changed' is standard.
                $this->handleUserEmoji($data);
            } elseif ($type === 'user-raise-hand-changed') {
                $this->handleUserRaiseHand($data);
            } elseif ($type === 'poll-started' || $type === 'poll-stopped') {
                $this->handlePoll($data, $type);
            } elseif ($type === 'publish_ended') {
                $this->handlePublishEnded($data);
            }
        }

        return response()->json(['status' => 'ok']);
    }

    /**
     * Handle recording published event - triggers VK upload
     */
    protected function handlePublishEnded(array $data)
    {
        $payload = $data['payload'] ?? $data;

        $recordId = $payload['record_id'] ?? null;
        $meetingId = $payload['external_meeting_id'] ?? null;
        $playback = $payload['playback'] ?? [];
        $metadata = $payload['metadata'] ?? [];

        if (!$recordId || !$meetingId) {
            Log::warning('BBB Webhook (publish_ended): Missing record_id or meeting_id', ['data' => $data]);
            return;
        }

        $videoUrl = $playback['link'] ?? null;
        $duration = $playback['duration'] ?? 0;
        $meetingName = $metadata['meetingName'] ?? 'Запись урока';
        $startTime = isset($payload['start_time']) ? \Carbon\Carbon::createFromTimestampMs($payload['start_time']) : now();
        $endTime = isset($payload['end_time']) ? \Carbon\Carbon::createFromTimestampMs($payload['end_time']) : now();

        Log::info('BBB Webhook (publish_ended): Processing recording', [
            'record_id' => $recordId,
            'meeting_id' => $meetingId,
            'video_url' => $videoUrl
        ]);

        // Find the room to get the teacher
        $room = \App\Models\Room::where('meeting_id', $meetingId)->first();

        if (!$room) {
            Log::warning('BBB Webhook (publish_ended): Room not found', ['meeting_id' => $meetingId]);
            return;
        }

        // Create or update the recording in our database
        $recording = \App\Models\Recording::updateOrCreate(
            ['record_id' => $recordId],
            [
                'room_id' => $room->id,
                'name' => $meetingName,
                'url' => $videoUrl,
                'duration' => (int) ($duration / 1000), // Convert ms to seconds
                'start_time' => $startTime,
                'end_time' => $endTime,
                'is_published' => true,
            ]
        );

        Log::info('BBB Webhook (publish_ended): Recording saved', ['recording_id' => $recording->id]);

        // Broadcast recording update
        \App\Events\RecordingUpdated::dispatch($recording);

        // Check if VK auto-upload is enabled
        $vkAutoUpload = \App\Models\Setting::where('key', 'vk_auto_upload')->value('value') === '1';

        if ($vkAutoUpload && !$recording->vk_video_id && $recording->url) {
            $teacher = $room->user; // Room owner is the teacher

            if ($teacher) {
                \App\Jobs\UploadRecordingToVk::dispatch($recording, $teacher);
                Log::info('BBB Webhook (publish_ended): VK upload job dispatched', [
                    'recording_id' => $recording->id,
                    'teacher' => $teacher->name
                ]);
            }
        }
    }

    protected function getMeetingId(array $data)
    {
        // Try new format first (data.attributes.meeting.external-meeting-id)
        if (isset($data['data']['attributes']['meeting']['external-meeting-id'])) {
            return $data['data']['attributes']['meeting']['external-meeting-id'];
        }

        // Fallbacks
        return $data['meetingId'] ?? ($data['core']['body']['meetingId'] ?? null);
    }

    protected function getSession($meetingId)
    {
        return MeetingSession::where('meeting_id', $meetingId)
            ->where('status', 'running')
            ->orderByDesc('started_at')
            ->first();
    }

    protected function getParticipantId(array $data)
    {
        // Try new format first (data.attributes.user)
        if (isset($data['data']['attributes']['user'])) {
            $u = $data['data']['attributes']['user'];
            return $u['external-user-id'] ?? $u['internal-user-id'] ?? null;
        }

        // Chat message sender
        if (isset($data['data']['attributes']['chat-message']['sender'])) {
            $u = $data['data']['attributes']['chat-message']['sender'];
            return $u['external-user-id'] ?? $u['internal-user-id'] ?? null;
        }

        // Old Format / Fallbacks
        $body = $data['core']['body'] ?? [];
        // Legacy: userId is often internal, extId might be in another field or same
        return $body['extId'] ?? $body['userId'] ?? ($body['sender']['userId'] ?? null);
    }

    protected function handleUserJoined(array $data)
    {
        $meetingId = $this->getMeetingId($data);
        if (!$meetingId) {
            Log::warning("BBB Webhook (user-joined): Could not extract meeting ID");
            return;
        }

        $session = $this->getSession($meetingId);
        if (!$session) {
            Log::info("BBB Webhook (user-joined): No running session found for $meetingId");
            return;
        }

        // Extract User Info
        $userId = $this->getParticipantId($data);
        $name = 'Unknown';
        $role = 'VIEWER';

        // New Format
        if (isset($data['data']['attributes']['user'])) {
            $userAttr = $data['data']['attributes']['user'];
            $name = $userAttr['name'] ?? 'Unknown';
            $role = $userAttr['role'] ?? 'VIEWER';
        } else {
            // Old Format
            $name = $data['core']['body']['name'] ?? 'Unknown';
            $role = $data['core']['body']['role'] ?? 'VIEWER';
        }

        if (!$userId)
            return;

        $analytics = $session->analytics_data ?? [];
        $participants = $analytics['participants'] ?? [];

        // Check if user already exists
        $existingIndex = null;
        foreach ($participants as $index => $p) {
            if (($p['user_id'] ?? '') === $userId) {
                $existingIndex = $index;
                break;
            }
        }

        if ($existingIndex !== null) {
            $participants[$existingIndex]['join_count'] = ($participants[$existingIndex]['join_count'] ?? 0) + 1;
            $participants[$existingIndex]['last_joined_at'] = now()->toIso8601String();
            // Update role if changed
            $participants[$existingIndex]['role'] = $role;
            $participants[$existingIndex]['full_name'] = $name;
        } else {
            $participants[] = [
                'user_id' => $userId,
                'full_name' => $name,
                'role' => $role,
                'joined_at' => now()->toIso8601String(),
                'join_count' => 1,
                'is_presenter' => false,
                'has_video' => false,
                'has_joined_voice' => false,
            ];
        }

        $analytics['participants'] = $participants;
        $session->update(['analytics_data' => $analytics]);
        Log::info("BBB Webhook: User $name joined meeting $meetingId");
        \App\Events\RoomStatusUpdated::dispatch();
    }

    protected function handleUserLeft(array $data)
    {
        $meetingId = $this->getMeetingId($data);
        if (!$meetingId)
            return;

        $session = $this->getSession($meetingId);
        if (!$session)
            return;

        // Extract User ID
        $userId = $this->getParticipantId($data);

        if (!$userId)
            return;

        $analytics = $session->analytics_data ?? [];
        $participants = $analytics['participants'] ?? [];

        foreach ($participants as &$p) {
            if (($p['user_id'] ?? '') === $userId) {
                $p['left_at'] = now()->toIso8601String();
                break;
            }
        }

        $analytics['participants'] = $participants;
        $session->update(['analytics_data' => $analytics]);
        Log::info("BBB Webhook: User left meeting $meetingId");
    }

    protected function handleMeetingEnded(array $data)
    {
        $meetingId = $this->getMeetingId($data);

        if (!$meetingId) {
            Log::warning('BBB Webhook: meeting-ended event missing meetingId', ['data' => $data]);
            return;
        }

        Log::info("BBB Webhook: Processing meeting-ended for $meetingId");

        // Find the room
        $room = Room::where('meeting_id', $meetingId)->first();

        if ($room) {
            $room->update(['is_running' => false]);
            Log::info("BBB Webhook: Room {$room->id} marked as not running.");
            \App\Events\RoomStatusUpdated::dispatch();
        }

        // Find and close the session
        $session = $this->getSession($meetingId);

        if ($session) {
            $analytics = $session->analytics_data ?? [];
            $participants = $analytics['participants'] ?? [];

            // Calculate final counts
            $participantCount = count($participants);
            $moderatorCount = count(array_filter($participants, fn($p) => ($p['role'] ?? '') === 'MODERATOR'));

            $analytics = array_merge($analytics, [
                'participant_count' => $participantCount,
                'moderator_count' => $moderatorCount,
                'meeting_name' => $room->name ?? 'Unknown',
            ]);

            // Get internal meeting ID for recording lookup
            $internalMeetingId = $session->internal_meeting_id ?? ($data['data']['attributes']['meeting']['internal-meeting-id'] ?? null);

            $session->update([
                'ended_at' => now(),
                'status' => 'completed',
                'participant_count' => $participantCount,
                'analytics_data' => $analytics,
                'internal_meeting_id' => $internalMeetingId,
                'pricing_snapshot' => $session->capturePricingSnapshot(),
            ]);
            Log::info("BBB Webhook: Session {$session->id} marked as completed with $participantCount participants.");
        } else {
            Log::info("BBB Webhook: No running session found for meeting $meetingId");
        }

        // Create placeholder recording regardless of session status
        // We'll create it with empty URL - it will be updated when publish_ended comes
        if ($room) {
            $internalMeetingId = $data['data']['attributes']['meeting']['internal-meeting-id'] ?? null;

            // Check if recording already exists for this meeting (within last 30 minutes)
            $existingRecording = \App\Models\Recording::where('meeting_id', $meetingId)
                ->where('start_time', '>=', now()->subMinutes(30))
                ->first();

            if (!$existingRecording && $internalMeetingId) {
                \App\Models\Recording::create([
                    'meeting_id' => $meetingId,
                    'record_id' => $internalMeetingId . '-placeholder-' . time(),
                    'name' => $room->name ?? 'Запись урока',
                    'published' => false,
                    'start_time' => now()->subMinutes(5), // Approximate
                    'end_time' => now(),
                    'participants' => 0,
                    'url' => null, // Will be filled when publish_ended comes
                ]);
                Log::info("BBB Webhook: Created placeholder recording for {$meetingId}");
            }
        }
    }

    protected function handleUserAudio(array $data, string $type)
    {
        $this->updateParticipantStat($data, function (&$participant) use ($type, $data) {
            $isEnabled = $type === 'user-audio-voice-enabled';

            if ($isEnabled) {
                $participant['audio_started_at'] = now()->toIso8601String();
                $participant['has_joined_voice'] = true;
            } else {
                if (!empty($participant['audio_started_at'])) {
                    $start = \Carbon\Carbon::parse($participant['audio_started_at']);
                    $duration = now()->diffInSeconds($start);
                    $participant['talking_time'] = ($participant['talking_time'] ?? 0) + $duration;
                    unset($participant['audio_started_at']);
                }
            }
        });
    }

    protected function handleUserCam(array $data, string $type)
    {
        $this->updateParticipantStat($data, function (&$participant) use ($type) {
            $isStarted = $type === 'user-cam-broadcast-started';
            if ($isStarted) {
                $participant['cam_started_at'] = now()->toIso8601String();
                $participant['has_video'] = true;
            } else {
                if (!empty($participant['cam_started_at'])) {
                    $start = \Carbon\Carbon::parse($participant['cam_started_at']);
                    $duration = now()->diffInSeconds($start);
                    $participant['webcam_time'] = ($participant['webcam_time'] ?? 0) + $duration;
                    unset($participant['cam_started_at']);
                }
            }
        });
    }

    protected function handleChatMessage(array $data)
    {
        $this->updateParticipantStat($data, function (&$participant) {
            $participant['message_count'] = ($participant['message_count'] ?? 0) + 1;
        });
    }

    protected function handleUserEmoji(array $data)
    {
        $this->updateParticipantStat($data, function (&$participant) {
            $participant['emoji_count'] = ($participant['emoji_count'] ?? 0) + 1;
        });
    }

    protected function handleUserRaiseHand(array $data)
    {
        $this->updateParticipantStat($data, function (&$participant) {
            $isRaised = false;
            if (isset($data['data']['attributes']['user']['raise-hand'])) {
                $isRaised = $data['data']['attributes']['user']['raise-hand'];
            } elseif (isset($data['core']['body']['raiseHand'])) {
                $isRaised = $data['core']['body']['raiseHand'];
            }

            if ($isRaised) {
                $participant['raise_hand_count'] = ($participant['raise_hand_count'] ?? 0) + 1;
            }
        });
    }


    protected function handlePoll(array $data, string $type)
    {
        $meetingId = $this->getMeetingId($data);
        if (!$meetingId)
            return;
        $session = $this->getSession($meetingId);
        if (!$session)
            return;

        $analytics = $session->analytics_data ?? [];

        if ($type === 'poll-started') {
            $analytics['poll_count'] = ($analytics['poll_count'] ?? 0) + 1;
            // Log timeline
            $analytics['timeline'] = array_merge($analytics['timeline'] ?? [], [
                [
                    'timestamp' => now()->toIso8601String(),
                    'type' => 'poll',
                    'description' => 'Golosovanie nachalos'
                ]
            ]);
        }

        $session->update(['analytics_data' => $analytics]);
    }

    protected function logTimeline($session, $type, $userId, $description)
    {
        $analytics = $session->analytics_data ?? [];
        $timeline = $analytics['timeline'] ?? [];
        $timeline[] = [
            'timestamp' => now()->toIso8601String(),
            'type' => $type,
            'user_id' => $userId,
            'description' => $description
        ];
        $analytics['timeline'] = $timeline;
        $session->update(['analytics_data' => $analytics]);
    }

    protected function updateParticipantStat(array $data, callable $callback)
    {
        $meetingId = $this->getMeetingId($data);
        if (!$meetingId)
            return;

        $session = $this->getSession($meetingId);
        if (!$session)
            return;

        // Extract User ID
        $userId = $this->getParticipantId($data);

        if (!$userId)
            return;

        $analytics = $session->analytics_data ?? [];
        $participants = $analytics['participants'] ?? [];

        $found = false;
        foreach ($participants as &$p) {
            if (($p['user_id'] ?? '') === $userId) {
                $callback($p);
                $found = true;
                break;
            }
        }

        if ($found) {
            $analytics['participants'] = $participants;
            $session->update(['analytics_data' => $analytics]);
        }
    }
}
