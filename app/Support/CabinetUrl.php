<?php

namespace App\Support;

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
            (bool) preg_match('#^tutor/homework(/\d+)?$#', $path) => self::route('cabinet.teacher.tasks'),
            (bool) preg_match('#^student/homework/(\d+)$#', $path, $m) => self::route('cabinet.student.task', ['homework' => (int) $m[1]]),
            $path === 'student/homework' => self::route('cabinet.student.tasks'),
            (bool) preg_match('#^tutor/rooms/(\d+)$#', $path, $m) => Room::withTrashed()->whereKey((int) $m[1])->where('user_id', $user->id)->exists()
                ? self::route('cabinet.teacher.lesson', ['room' => (int) $m[1]])
                : null,
            in_array($path, ['tutor/rooms', 'tutor/schedule-calendar'], true) => self::route('cabinet.teacher.schedule'),
            (bool) preg_match('#^student/(rooms(/\d+)?|schedule-calendar)$#', $path) => self::route('cabinet.student.schedule'),
            $path === 'student/payment-debts' => self::route('cabinet.student.payments'),
            $path === 'tutor/subscription' => self::route('cabinet.teacher.subscription'),
            $path === 'tutor/referrals' => self::route('cabinet.teacher.referrals'),
            $path === 'tutor/students' => self::route('cabinet.teacher.students'),
            $path === 'tutor/reviews' => self::route('cabinet.teacher.reviews'),
            $path === 'tutor/materials' => self::route('cabinet.teacher.materials'),
            $path === 'student/materials' => self::route('cabinet.student.materials'),
            $path === 'tutor/recordings' => self::route('cabinet.teacher.recordings'),
            $path === 'student/recordings' => self::route('cabinet.student.recordings'),
            default => null,
        };

        return $target ?? $url;
    }

    private static function route(string $name, array $params = []): ?string
    {
        return Route::has($name) ? route($name, $params) : null;
    }
}
