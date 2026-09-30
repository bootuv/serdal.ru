<?php

namespace App\Services;

use App\Models\Room;
use App\Notifications\RoomFullRefused;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use JoisarJignesh\Bigbluebutton\Facades\Bigbluebutton;

/**
 * Места в классе. Лимит участников (тариф учителя или общая настройка) при создании занятия передаётся
 * серверу BBB как maxParticipants и считает всех, вместе с учителем. Когда места заняты, BBB отказывает
 * во входе своей служебной страницей, поэтому перед входом проверяем сами (getMeetingInfo), а отказ
 * BBB (errorRedirectUrl) ловим как подстраховку. Не пустили — показываем экран «В классе нет свободных мест»
 * и сообщаем учителю.
 */
class RoomCapacityService
{
    /** Повторные попытки того же человека не шлют учителю новое уведомление этот срок. */
    private const NOTIFY_EVERY_MINUTES = 10;

    /** Ключ сессии: вход не удался, мест нет (значение — лимит участников). */
    public const SESSION_KEY = 'room_full';

    /**
     * Лимит участников, если класс заполнен и человека с этим userID в нём ещё нет (переподключение пускаем).
     * null — места есть, лимита нет или сервер не ответил (тогда решает сам BBB).
     * BBB уже должен быть настроен на сервер владельца комнаты.
     */
    public function fullLimit(Room $room, string $userId): ?int
    {
        try {
            $info = Bigbluebutton::getMeetingInfo(['meetingID' => $room->meeting_id]);
        } catch (\Throwable $e) {
            Log::warning('BBB getMeetingInfo failed before join', ['room_id' => $room->id, 'error' => $e->getMessage()]);

            return null;
        }

        $max = (int) ($info['maxUsers'] ?? 0);
        if ($max <= 0 || (int) ($info['participantCount'] ?? 0) < $max) {
            return null;
        }

        // attendees.attendee — массив (несколько) или объект (один)
        $attendees = $info['attendees']['attendee'] ?? [];
        if (isset($attendees['userID'])) {
            $attendees = [$attendees];
        }
        foreach ((array) $attendees as $attendee) {
            if ((string) ($attendee['userID'] ?? '') === $userId) {
                return null;
            }
        }

        return $max;
    }

    /** Лимит участников занятия, записанный при запуске (для случая, когда отказал сам BBB). */
    public function limitOf(Room $room): ?int
    {
        $session = \App\Models\MeetingSession::where('room_id', $room->id)
            ->where('meeting_id', $room->meeting_id)
            ->latest('id')
            ->first();

        $max = (int) ($session?->settings_snapshot['maxParticipants'] ?? 0);

        return $max > 0 ? $max : null;
    }

    /** Человека не пустили: сообщаем учителю (не чаще раза в NOTIFY_EVERY_MINUTES на человека). */
    public function refused(Room $room, string $userId, string $name, ?int $max): void
    {
        $teacher = $room->user;
        if (! $teacher) {
            return;
        }

        $key = 'room-full:' . $room->id . ':' . $userId;
        if (! Cache::add($key, true, now()->addMinutes(self::NOTIFY_EVERY_MINUTES))) {
            return;
        }

        $tariff = $teacher->activeSubscription()?->tariff;

        $teacher->notify(new RoomFullRefused(
            room: $room,
            name: $name,
            max: $max,
            tariffName: $tariff && $max && (int) $tariff->max_participants === $max ? $tariff->name : null,
        ));
    }
}
