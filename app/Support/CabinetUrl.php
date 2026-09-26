<?php

namespace App\Support;

use App\Models\Homework;
use App\Models\MeetingSession;
use App\Models\Room;
use App\Models\User;
use App\Services\MessengerService;
use Illuminate\Support\Facades\Route;

/**
 * Ссылки удалённых Filament-панелей (/tutor/…, /student/…, /admin/…) → экраны кабинетов.
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

        if ($path === 'admin' || str_starts_with($path, 'admin/')) {
            return $user->isAdmin() ? (self::fromAdmin($path, $query) ?? $url) : $url;
        }

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
            $path === 'tutor/homework/create' => self::route('cabinet.teacher.task-new'),
            (bool) preg_match('#^tutor/homework/(\d+)/edit$#', $path, $m) => self::route('cabinet.teacher.task-new', ['edit' => (int) $m[1]]),
            (bool) preg_match('#^tutor/materials(/\d+/edit)?$#', $path) => self::route('cabinet.teacher.materials'),
            (bool) preg_match('#^tutor/prices(/create|/\d+/edit)?$#', $path) => self::route('cabinet.teacher.profile', ['tab' => 'prices']),
            in_array($path, ['tutor/rooms/create', 'tutor/meeting-sessions'], true) => self::route('cabinet.teacher.schedule'),
            $path === 'tutor/integrations' => self::route('cabinet.teacher.profile'),
            (bool) preg_match('#^tutor/recordings/\d+$#', $path) => self::route('cabinet.teacher.recordings'),
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

    /**
     * Адреса старой Filament-админки (/admin/…) → экраны новой админки (/cabinet/admin/…).
     * Нужен для ссылок в сохранённых уведомлениях и для закладок (RedirectOldAdmin).
     */
    public static function fromAdmin(string $path, array $query = []): ?string
    {
        $path = trim($path, '/');

        return match (true) {
            $path === 'admin' => self::route('cabinet.admin.today'),
            $path === 'admin/admin-messenger' => self::route('cabinet.admin.support', isset($query['chat']) ? ['chat' => (int) $query['chat']] : []),
            str_starts_with($path, 'admin/teacher-applications') => self::route('cabinet.admin.applications'),
            str_starts_with($path, 'admin/reviews') => self::route('cabinet.admin.reviews'),
            (bool) preg_match('#^admin/meeting-sessions/(\d+)$#', $path, $m) => MeetingSession::whereKey((int) $m[1])->exists()
                ? self::route('cabinet.admin.session', ['session' => (int) $m[1]])
                : self::route('cabinet.admin.lessons', ['tab' => 'deletions']),
            $path === 'admin/meeting-sessions' => self::route('cabinet.admin.lessons', ['tab' => 'sessions']),
            (bool) preg_match('#^admin/rooms/(\d+)(/edit)?$#', $path, $m) => Room::withTrashed()->whereKey((int) $m[1])->exists()
                ? self::route('cabinet.admin.lesson', ['room' => (int) $m[1]])
                : self::route('cabinet.admin.lessons'),
            in_array($path, ['admin/rooms', 'admin/rooms/create', 'admin/schedule-calendar'], true) => self::route('cabinet.admin.lessons'),
            str_starts_with($path, 'admin/recordings') => self::route('cabinet.admin.lessons', ['tab' => 'recordings']),
            (bool) preg_match('#^admin/users/(\d+)/edit$#', $path, $m) => User::whereKey((int) $m[1])->exists()
                ? self::route('cabinet.admin.user', ['user' => (int) $m[1]])
                : self::route('cabinet.admin.users'),
            str_starts_with($path, 'admin/users') => self::route('cabinet.admin.users'),
            $path === 'admin/subscription-payments' => self::route('cabinet.admin.payments'),
            str_starts_with($path, 'admin/subscriptions') => self::route('cabinet.admin.payments', ['tab' => 'subscriptions']),
            (bool) preg_match('#^admin/tariffs/(\d+)/edit$#', $path, $m) => self::route('cabinet.admin.tariff', ['tariff' => (int) $m[1]]),
            $path === 'admin/tariffs/create' => self::route('cabinet.admin.tariff', ['tariff' => 'new']),
            $path === 'admin/tariffs' => self::route('cabinet.admin.tariffs'),
            $path === 'admin/referral-rewards' => self::route('cabinet.admin.referrals'),
            (bool) preg_match('#^admin/help-articles/(\d+)/edit$#', $path, $m) => self::route('cabinet.admin.help-article', ['article' => (int) $m[1]]),
            $path === 'admin/help-articles/create' => self::route('cabinet.admin.help-article', ['article' => 'new']),
            str_starts_with($path, 'admin/help-') => self::route('cabinet.admin.help'),
            str_starts_with($path, 'admin/subjects') => self::route('cabinet.admin.settings', ['tab' => 'dictionaries']),
            str_starts_with($path, 'admin/directs') => self::route('cabinet.admin.settings', ['tab' => 'dictionaries', 'dict' => 'directs']),
            $path === 'admin/settings' => self::route('cabinet.admin.settings'),
            default => self::route('cabinet.admin.today'),
        };
    }

    private static function route(string $name, array $params = []): ?string
    {
        return Route::has($name) ? route($name, $params) : null;
    }
}
