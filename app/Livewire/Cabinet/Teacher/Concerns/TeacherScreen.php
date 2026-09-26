<?php

namespace App\Livewire\Cabinet\Teacher\Concerns;

use App\Models\User;

/**
 * Общий доступ к экранам кабинета учителя: роль (tutor, mentor; admin — как в старой панели)
 * и заполненный профиль (иначе — онбординг, как CheckUserProfileCompleted).
 * Использование: `use TeacherScreen;` и вызов `$this->authorizeTeacher()` в начале mount().
 */
trait TeacherScreen
{
    protected function authorizeTeacher(bool $requireProfile = true): User
    {
        $user = auth()->user();
        abort_unless($user && in_array($user->role, [User::ROLE_TUTOR, User::ROLE_MENTOR, User::ROLE_ADMIN], true), 403);

        if ($requireProfile && $user->role === User::ROLE_TUTOR && ! $user->is_profile_completed) {
            $this->redirect(\Illuminate\Support\Facades\Route::has('cabinet.teacher.onboarding')
                ? route('cabinet.teacher.onboarding')
                : route('filament.app.pages.onboarding'));
        }

        return $user;
    }
}
