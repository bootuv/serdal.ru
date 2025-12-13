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
            }
        }

        return response()->json(['status' => 'ok']);
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
        $userId = null;
        $name = 'Unknown';
        $role = 'VIEWER';

        // New Format
        if (isset($data['data']['attributes']['user'])) {
            $userAttr = $data['data']['attributes']['user'];
            $userId = $userAttr['internal-user-id'] ?? $userAttr['external-user-id'] ?? null; // Use internal if available as it is unique per join? No, analytics usually tracks unique people. Let's use internal-user-id as it matches user-left.
            $name = $userAttr['name'] ?? 'Unknown';
            $role = $userAttr['role'] ?? 'VIEWER';
        } else {
            // Old Format
            $userId = $data['core']['body']['userId'] ?? null;
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
        $userId = null;
        if (isset($data['data']['attributes']['user'])) {
            $userId = $data['data']['attributes']['user']['internal-user-id'] ?? null;
        } else {
            $userId = $data['core']['body']['userId'] ?? null;
        }

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

            $session->update([
                'ended_at' => now(),
                'status' => 'completed',
                'participant_count' => $participantCount,
                'analytics_data' => $analytics,
                'internal_meeting_id' => $session->internal_meeting_id ?? ($data['data']['attributes']['meeting']['internal-meeting-id'] ?? null),
            ]);
            Log::info("BBB Webhook: Session {$session->id} marked as completed with $participantCount participants.");
        } else {
            Log::info("BBB Webhook: No running session found for meeting $meetingId");
        }
    }
}
