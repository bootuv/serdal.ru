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

        // Determine redirect based on user role
        if ($user->role === User::ROLE_STUDENT) {
            // Students go back to their room view
            return redirect()->route('filament.student.resources.rooms.view', $room);
        }

        if ($user->isAdmin()) {
            // Admins go to admin panel session view
            return redirect()->route('filament.admin.resources.meeting-sessions.view', $session);
        }

        // Teachers (tutors/mentors) go to tutor panel session view
        return redirect()->route('filament.app.resources.meeting-sessions.view', $session);
    }
}
