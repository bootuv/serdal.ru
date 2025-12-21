<?php

namespace App\Observers;

use App\Jobs\DeleteScheduleFromGoogleCalendar;
use App\Jobs\SyncScheduleToGoogleCalendar;
use App\Models\RoomSchedule;

class RoomScheduleObserver
{
    /**
     * Handle the RoomSchedule "created" event.
     */
    public function created(RoomSchedule $roomSchedule): void
    {
        $this->syncToGoogleCalendar($roomSchedule);
    }

    /**
     * Handle the RoomSchedule "updated" event.
     */
    public function updated(RoomSchedule $roomSchedule): void
    {
        // Prevent infinite loop: don't sync if only google_event_id changed
        if ($roomSchedule->isDirty('google_event_id') && count($roomSchedule->getDirty()) === 1) {
            return;
        }

        $this->syncToGoogleCalendar($roomSchedule);
    }

    /**
     * Handle the RoomSchedule "deleting" event (before delete).
     * Must use deleting instead of deleted to access relationships.
     */
    public function deleting(RoomSchedule $roomSchedule): void
    {
        // Only proceed if there's a Google event to delete
        if (!$roomSchedule->google_event_id) {
            return;
        }

        // Load room with user and participants before deletion
        $roomSchedule->load(['room.user', 'room.participants']);

        $this->deleteFromGoogleCalendar($roomSchedule);
    }

    /**
     * Delete schedule from Google Calendar for all connected users
     */
    private function deleteFromGoogleCalendar(RoomSchedule $roomSchedule): void
    {
        $room = $roomSchedule->room;

        if (!$room) {
            return;
        }

        // Delete for room owner (teacher)
        $teacher = $room->user;
        if ($teacher && $teacher->google_access_token) {
            DeleteScheduleFromGoogleCalendar::dispatch(
                $roomSchedule->google_event_id,
                $teacher->id,
                $roomSchedule->id
            );
        }

        // Delete for all students assigned to this room
        $participants = $room->participants;

        if ($participants && (is_array($participants) || $participants instanceof \Illuminate\Support\Collection)) {
            foreach ($participants as $participant) {
                if ($participant instanceof \App\Models\User) {
                    $student = $participant;
                } else {
                    $student = \App\Models\User::find($participant);
                }

                if ($student && $student->google_access_token) {
                    DeleteScheduleFromGoogleCalendar::dispatch(
                        $roomSchedule->google_event_id,
                        $student->id,
                        $roomSchedule->id
                    );
                }
            }
        }
    }

    /**
     * Sync schedule to Google Calendar for all connected users
     */
    private function syncToGoogleCalendar(RoomSchedule $roomSchedule): void
    {
        if (!$roomSchedule->is_active) {
            return;
        }

        $room = $roomSchedule->room;

        if (!$room) {
            return;
        }

        // Sync for room owner (teacher)
        $teacher = $room->user;
        if ($teacher && $teacher->google_access_token) {
            SyncScheduleToGoogleCalendar::dispatch($roomSchedule, $teacher->id);
        }

        // Sync for all students assigned to this room
        $participants = $room->participants;

        if ($participants && (is_array($participants) || $participants instanceof \Illuminate\Support\Collection)) {
            foreach ($participants as $participant) {
                if ($participant instanceof \App\Models\User) {
                    $student = $participant;
                } else {
                    $student = \App\Models\User::find($participant);
                }

                if ($student && $student->google_access_token) {
                    SyncScheduleToGoogleCalendar::dispatch($roomSchedule, $student->id);
                }
            }
        }
    }
}
