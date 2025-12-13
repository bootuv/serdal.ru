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

        // Some BBB versions send a single object, some an array wrapped in 'event' or just the array.
        // We'll try to normalize it.
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
            $data = $event['event'] ?? $event; // Sometimes event details are nested in 'event' key again
            $type = $data['type'] ?? ($data['header']['name'] ?? null); // 'type' or header.name

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

    protected function getSession($meetingId)
    {
        return MeetingSession::where('meeting_id', $meetingId)
            ->where('status', 'running')
            ->orderByDesc('started_at')
            ->first();
    }

    protected function handleUserJoined(array $data)
    {
        $meetingId = $data['meetingId'] ?? ($data['core']['body']['meetingId'] ?? null);
        if (!$meetingId)
            return;

        $session = $this->getSession($meetingId);
        if (!$session)
            return;

        $userId = $data['core']['body']['userId'] ?? null;
        $name = $data['core']['body']['name'] ?? 'Unknown';
        $role = $data['core']['body']['role'] ?? 'VIEWER';

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
            // Update existing entry
            $participants[$existingIndex]['join_count'] = ($participants[$existingIndex]['join_count'] ?? 0) + 1;
            $participants[$existingIndex]['last_joined_at'] = now()->toIso8601String();
        } else {
            // Add new entry
            $participants[] = [
                'user_id' => $userId,
                'full_name' => $name,
                'role' => $role,
                'joined_at' => now()->toIso8601String(),
                'join_count' => 1,
                'is_presenter' => false, // Default, updated if we get presenter events
                'has_video' => false,
                'has_joined_voice' => false,
            ];
        }

        $analytics['participants'] = $participants;
        $session->update(['analytics_data' => $analytics]);

        Log::info("BBB Webhook: User $name joined meeting $meetingId");
    }

    protected function handleUserLeft(array $data)
    {
        $meetingId = $data['meetingId'] ?? ($data['core']['body']['meetingId'] ?? null);
        if (!$meetingId)
            return;

        $session = $this->getSession($meetingId);
        if (!$session)
            return;

        $userId = $data['core']['body']['userId'] ?? null;

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
        $meetingId = $data['meetingId'] ?? ($data['core']['body']['meetingId'] ?? null);

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
            ]);
            Log::info("BBB Webhook: Session {$session->id} marked as completed with $participantCount participants.");
        } else {
            Log::info("BBB Webhook: No running session found for meeting $meetingId");
        }
    }
}
