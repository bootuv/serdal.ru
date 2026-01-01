<?php

namespace App\Policies;

use App\Models\MeetingSession;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class MeetingSessionPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin()
            || $user->role === User::ROLE_TUTOR
            || $user->role === User::ROLE_MENTOR;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, MeetingSession $meetingSession): bool
    {
        \Illuminate\Support\Facades\Log::info('MeetingSessionPolicy view check', [
            'user_id' => $user->id,
            'session_user_id' => $meetingSession->user_id,
            'is_admin' => $user->isAdmin(),
        ]);

        if ($user->isAdmin()) {
            return true;
        }

        return $meetingSession->user_id === $user->id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true; // Allow creation via app logic
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, MeetingSession $meetingSession): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $meetingSession->user_id === $user->id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, MeetingSession $meetingSession): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $meetingSession->user_id === $user->id;
    }
    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, MeetingSession $meetingSession): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, MeetingSession $meetingSession): bool
    {
        return false;
    }
}
