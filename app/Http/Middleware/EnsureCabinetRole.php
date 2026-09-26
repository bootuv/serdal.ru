<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Пускает в кабинет только свою роль; остальных мягко отправляет в их кабинет (без экрана 403).
 * Использование: ->middleware(EnsureCabinetRole::class . ':student') / ':teacher' / ':admin'.
 */
class EnsureCabinetRole
{
    public const TEACHER_ROLES = [User::ROLE_TUTOR, User::ROLE_ADMIN];

    public function handle(Request $request, Closure $next, string $cabinet): Response
    {
        $role = $request->user()?->role;
        $allowed = match ($cabinet) {
            'student' => $role === User::ROLE_STUDENT,
            'admin' => $role === User::ROLE_ADMIN,
            default => in_array($role, self::TEACHER_ROLES, true),
        };

        return $allowed ? $next($request) : redirect(self::homeFor($request->user()));
    }

    /** Главная кабинета для пользователя. */
    public static function homeFor(?User $user): string
    {
        return match ($user?->role) {
            User::ROLE_STUDENT => route('cabinet.student.home'),
            User::ROLE_TUTOR => \Illuminate\Support\Facades\Route::has('cabinet.teacher.today') ? route('cabinet.teacher.today') : url('/tutor'),
            default => \Illuminate\Support\Facades\Route::has('cabinet.admin.today') ? route('cabinet.admin.today') : url('/admin'),
        };
    }
}
