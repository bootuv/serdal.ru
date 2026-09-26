<?php

namespace App\Support;

use App\Models\Homework;
use App\Models\MeetingSession;
use App\Models\Room;
use App\Models\User;
use App\Services\MessengerService;
use Illuminate\Support\Facades\Route;

/**
 * Ссылки старого кабинета (Filament: /tutor/…, /student/…) → экраны нового кабинета.
 * Уведомления хранят ссылку в базе на момент отправки, поэтому переводим их при показе.
 * Если нового экрана нет — возвращаем ссылку как есть.
 */
class CabinetUrl
{
    public static function fromLegacy(?string $url, User $user): ?string
    {
        if (! $url) {
            return null;
        }

        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        $target = match (true) {
            in_array($path, ['tutor/messenger', 'student/messenger'], true) => MessengerService::url(
                $user,
                isset($query['room']) ? (int) $query['room'] : null,
                ($query['support'] ?? null) === '1'
            ),
            in_array($path, ['tutor', 'tutor/dashboard'], true) => self::route('cabinet.teacher.today'),
            in_array($path, ['student', 'student/dashboard'], true) => self::route('cabinet.student.home'),
            (bool) preg_match('#^tutor/homework/(\d+)$#', $path, $m) => Homework::whereKey((int) $m[1])->where('teacher_id', $user->id)->exists()
                ? (self::route('cabinet.teacher.task', ['homework' => (int) $m[1]]) ?? self::route('cabinet.teacher.tasks'))
                : self::route('cabinet.teacher.tasks'),
            $path === 'tutor/homework' => self::route('cabinet.teacher.tasks'),
            (bool) preg_match('#^tutor/homework-submissions/(\d+)(/edit)?$#', $path, $m) => self::route('cabinet.teacher.review', ['submission' => (int) $m[1]]),
            $path === 'tutor/homework-submissions' => self::route('cabinet.teacher.tasks'),
            (bool) preg_match('#^tutor/meeting-sessions/(\d+)$#', $path, $m) => ($roomId = MeetingSession::whereKey((int) $m[1])->value('room_id'))
                && Room::withTrashed()->whereKey($roomId)->where('user_id', $user->id)->exists()
                ? self::route('cabinet.teacher.lesson', ['room' => $roomId])
                : null,
            (bool) preg_match('#^tutor/students/(\d+)(/edit)?$#', $path, $m) => ($username = User::whereKey((int) $m[1])->value('username'))
                ? self::route('cabinet.teacher.student', ['student' => $username])
                : self::route('cabinet.teacher.students'),
            in_array($path, ['tutor/edit-profile', 'tutor/lesson-types'], true) => self::route('cabinet.teacher.profile'),
            $path === 'tutor/payments' => self::route('cabinet.teacher.payments') ?? self::route('cabinet.teacher.subscription'),
            $path === 'tutor/onboarding' => self::route('cabinet.teacher.onboarding'),
            $path === 'student/profile' => self::route('cabinet.student.profile'),
            (bool) preg_match('#^tutor/rooms/(\d+)/edit$#', $path, $m) => self::route('cabinet.teacher.lesson', ['room' => (int) $m[1]]),
            (bool) preg_match('#^student/homework/(\d+)$#', $path, $m) => self::route('cabinet.student.task', ['homework' => (int) $m[1]]),
            $path === 'student/homework' => self::route('cabinet.student.tasks'),
            (bool) preg_match('#^tutor/rooms/(\d+)$#', $path, $m) => Room::withTrashed()->whereKey((int) $m[1])->where('user_id', $user->id)->exists()
                ? self::route('cabinet.teacher.lesson', ['room' => (int) $m[1]])
                : null,
            in_array($path, ['tutor/rooms', 'tutor/schedule-calendar'], true) => self::route('cabinet.teacher.schedule'),
            (bool) preg_match('#^student/rooms/(\d+)$#', $path, $m) => self::route('cabinet.student.lesson', ['room' => (int) $m[1]]) ?? self::route('cabinet.student.schedule'),
            (bool) preg_match('#^student/(rooms|schedule-calendar)$#', $path) => self::route('cabinet.student.schedule'),
            $path === 'student/payment-debts' => self::route('cabinet.student.payments'),
            $path === 'tutor/subscription' => self::route('cabinet.teacher.subscription'),
            $path === 'tutor/referrals' => self::route('cabinet.teacher.referrals'),
            $path === 'tutor/students' => self::route('cabinet.teacher.students'),
            $path === 'tutor/reviews' => self::route('cabinet.teacher.reviews'),
            $path === 'tutor/materials' => self::route('cabinet.teacher.materials'),
            $path === 'student/materials' => self::route('cabinet.student.materials'),
            $path === 'tutor/recordings' => self::route('cabinet.teacher.recordings'),
            (bool) preg_match('#^student/recordings(/\d+)?$#', $path) => self::route('cabinet.student.recordings'),
            default => null,
        };

        return $target ?? $url;
    }

    private static function route(string $name, array $params = []): ?string
    {
        return Route::has($name) ? route($name, $params) : null;
    }
}
