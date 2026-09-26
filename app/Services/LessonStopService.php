<?php

namespace App\Services;

use App\Models\MeetingSession;
use App\Models\Room;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\LessonStoppedByAdmin;
use Illuminate\Support\Facades\Log;
use JoisarJignesh\Bigbluebutton\Facades\Bigbluebutton;

/**
 * Завершение идущего занятия: снимок участников с сервера видеосвязи, закрытие класса,
 * отметка «занятие не идёт» и завершение проведённого занятия (MeetingSession).
 * Используют RoomController::stop (учитель, старые кабинеты) и новая админка.
 */
class LessonStopService
{
    /**
     * Завершить занятие. Ошибки сервера видеосвязи (класс уже закрыт, сервер недоступен) не мешают
     * отметить занятие завершённым.
     */
    public function stop(Room $room): ?MeetingSession
    {
        self::configureServer($room->user);

        [$participantCount, $analyticsData] = $this->captureAnalytics($room);

        try {
            Bigbluebutton::close([
                'meetingID' => $room->meeting_id,
                'moderatorPW' => $room->moderator_pw,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Завершение занятия: сервер видеосвязи не закрыл класс', ['room_id' => $room->id, 'error' => $e->getMessage()]);
        }

        $room->update(['is_running' => false]);
        \App\Events\RoomStatusUpdated::dispatch();

        $session = $this->runningSession($room);

        if ($session) {
            $session->update([
                'ended_at' => now(),
                'status' => 'completed',
                'participant_count' => max($participantCount, 1), // хотя бы учитель
                'analytics_data' => $analyticsData ?? $session->analytics_data,
                'pricing_snapshot' => $session->capturePricingSnapshot(),
            ]);
        }

        return $session;
    }

    /** Администратор завершает чужое занятие: учитель получает уведомление. */
    public function stopByAdmin(Room $room, User $admin): ?MeetingSession
    {
        $session = $this->stop($room);

        $room->user?->notify(new LessonStoppedByAdmin($room, $session));

        Log::info('Администратор завершил занятие', ['room_id' => $room->id, 'admin_id' => $admin->id, 'session_id' => $session?->id]);

        return $session;
    }

    /** Идущее проведённое занятие этого класса. */
    public function runningSession(Room $room): ?MeetingSession
    {
        return MeetingSession::where('room_id', $room->id)
            ->where('meeting_id', $room->meeting_id)
            ->where('status', 'running')
            ->orderByDesc('started_at')
            ->first();
    }

    /** Сервер видеосвязи: свой у учителя, если задан, иначе общий из настроек. */
    public static function configureServer(?User $owner): void
    {
        if ($owner && $owner->bbb_url && $owner->bbb_secret) {
            config([
                'bigbluebutton.BBB_SERVER_BASE_URL' => $owner->bbb_url,
                'bigbluebutton.BBB_SECURITY_SALT' => $owner->bbb_secret,
            ]);

            return;
        }

        $globalUrl = Setting::where('key', 'bbb_url')->value('value');
        $globalSecret = Setting::where('key', 'bbb_secret')->value('value');

        if ($globalUrl && $globalSecret) {
            config([
                'bigbluebutton.BBB_SERVER_BASE_URL' => $globalUrl,
                'bigbluebutton.BBB_SECURITY_SALT' => $globalSecret,
            ]);
        }
    }

    /**
     * Число участников и сведения о них перед закрытием класса (getMeetingInfo).
     *
     * @return array{0:int, 1:?array}
     */
    private function captureAnalytics(Room $room): array
    {
        $participantCount = 0;
        $analyticsData = null;

        try {
            $info = Bigbluebutton::getMeetingInfo(['meetingID' => $room->meeting_id]);

            if ($info && isset($info['participantCount'])) {
                // participantCount уже включает всех (ведущих и слушателей)
                $participantCount = (int) $info['participantCount'];

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

                // attendees.attendee — массив (несколько) или объект (один)
                $attendeesRaw = $info['attendees']['attendee'] ?? $info['attendees'] ?? null;

                if ($attendeesRaw) {
                    if (isset($attendeesRaw['userID'])) {
                        $attendeesRaw = [$attendeesRaw];
                    }

                    $sessionStart = $this->runningSession($room)?->started_at ?? now();

                    foreach ($attendeesRaw as $attendee) {
                        $analyticsData['participants'][] = [
                            'user_id' => $attendee['userID'] ?? null,
                            'full_name' => $attendee['fullName'] ?? 'Unknown',
                            'role' => $attendee['role'] ?? 'VIEWER',
                            'is_presenter' => filter_var($attendee['isPresenter'] ?? false, FILTER_VALIDATE_BOOLEAN),
                            'is_listening_only' => filter_var($attendee['isListeningOnly'] ?? false, FILTER_VALIDATE_BOOLEAN),
                            'has_joined_voice' => filter_var($attendee['hasJoinedVoice'] ?? false, FILTER_VALIDATE_BOOLEAN),
                            'has_video' => filter_var($attendee['hasVideo'] ?? false, FILTER_VALIDATE_BOOLEAN),
                            'joined_at' => $sessionStart->toIso8601String(),
                            'left_at' => now()->toIso8601String(),
                        ];
                    }
                }
            } else {
                Log::warning('No participant data in BBB response', ['meeting_id' => $room->meeting_id]);
            }
        } catch (\Throwable $e) {
            // Класс уже закрыт или сервер недоступен
            Log::warning('Failed to capture analytics: ' . $e->getMessage(), ['meeting_id' => $room->meeting_id]);
        }

        return [$participantCount, $analyticsData];
    }
}
