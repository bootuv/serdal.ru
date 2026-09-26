<?php

namespace App\Http\Controllers;

use App\Models\MeetingSession;
use App\Models\User;
use Illuminate\Http\Request;

class SessionLogoutController extends Controller
{
    /**
     * Handle BBB session logout redirect based on user role.
     * BBB only allows one logoutUrl per meeting, so we use this controller
     * to redirect users to the appropriate page based on their role.
     */
    public function __invoke(MeetingSession $session, Request $request)
    {
        $user = auth()->user();
        $room = $session->room;

        if (!$user) {
            // Not logged in - redirect to home
            return redirect('/');
        }

        // Ученик — в новый кабинет: страница занятия (если есть) или расписание
        if ($user->role === User::ROLE_STUDENT) {
            return redirect(\Illuminate\Support\Facades\Route::has('cabinet.student.lesson') && $room
                ? route('cabinet.student.lesson', $room)
                : route('cabinet.student.schedule'));
        }

        if ($user->isAdmin()) {
            // Admins go to admin panel session view
            return redirect()->route('filament.admin.resources.meeting-sessions.view', $session);
        }

        // Учитель — на страницу занятия нового кабинета: там итоги прошедшего занятия
        return $room
            ? redirect()->route('cabinet.teacher.lesson', $room)
            : redirect()->route('cabinet.teacher.schedule');
    }
}
