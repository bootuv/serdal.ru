<?php

namespace App\Observers;

use App\Jobs\DeleteScheduleFromGoogleCalendar;
use App\Jobs\SyncScheduleToGoogleCalendar;
use App\Models\RoomSchedule;
use Illuminate\Support\Facades\Log;

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
            Log::info('Skipping sync - only google_event_id changed', [
                'schedule_id' => $roomSchedule->id,
            ]);
            return;
        }

        $this->syncToGoogleCalendar($roomSchedule);
    }

    /**
     * Handle the RoomSchedule "deleted" event.
     */
    public function deleted(RoomSchedule $roomSchedule): void
    {
        // Only proceed if there's a Google event to delete
        if (!$roomSchedule->google_event_id) {
            Log::info('Schedule deleted, no Google event to remove', [
                'schedule_id' => $roomSchedule->id,
            ]);
            return;
        }

        Log::info('Schedule deleted, removing from Google Calendar', [
            'schedule_id' => $roomSchedule->id,
            'google_event_id' => $roomSchedule->google_event_id,
        ]);

        $this->deleteFromGoogleCalendar($roomSchedule);
    }

    /**
     * Delete schedule from Google Calendar for all connected users
     */
    private function deleteFromGoogleCalendar(RoomSchedule $roomSchedule): void
    {
        $room = $roomSchedule->room;

        if (!$room) {
            Log::warning('Room not found for schedule', ['schedule_id' => $roomSchedule->id]);
            return;
        }

        // Delete for room owner (teacher)
        $teacher = $room->user;
        if ($teacher && $teacher->google_access_token) {
            Log::info('Dispatching delete job for teacher', [
                'schedule_id' => $roomSchedule->id,
                'user_id' => $teacher->id,
            ]);

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
                    $studentId = $student->id;
                } else {
                    $studentId = $participant;
                    $student = \App\Models\User::find($studentId);
                }

                if ($student && $student->google_access_token) {
                    Log::info('Dispatching delete job for student', [
                        'schedule_id' => $roomSchedule->id,
                        'user_id' => $student->id,
                    ]);

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
            Log::info('Schedule is inactive, skipping sync', ['schedule_id' => $roomSchedule->id]);
            return;
        }

        $room = $roomSchedule->room;

        if (!$room) {
            Log::warning('Room not found for schedule', ['schedule_id' => $roomSchedule->id]);
            return;
        }

        // Sync for room owner (teacher)
        $teacher = $room->user;
        if ($teacher && $teacher->google_access_token) {
            Log::info('Dispatching sync job for teacher', [
                'schedule_id' => $roomSchedule->id,
                'user_id' => $teacher->id,
            ]);

            SyncScheduleToGoogleCalendar::dispatch($roomSchedule, $teacher->id);
        }

        // Sync for all students assigned to this room
        Log::info('Checking room participants', [
            'schedule_id' => $roomSchedule->id,
            'room_id' => $room->id,
            'participants_type' => gettype($room->participants),
            'participants_class' => is_object($room->participants) ? get_class($room->participants) : null,
        ]);

        // Handle both array and Collection
        $participants = $room->participants;

        if ($participants && (is_array($participants) || $participants instanceof \Illuminate\Support\Collection)) {
            foreach ($participants as $participant) {
                // If participant is already a User model (from relationship)
                if ($participant instanceof \App\Models\User) {
                    $student = $participant;
                    $studentId = $student->id;
                } else {
                    // If participant is just an ID
                    $studentId = $participant;
                    $student = \App\Models\User::find($studentId);
                }

                Log::info('Checking student for sync', [
                    'schedule_id' => $roomSchedule->id,
                    'student_id' => $studentId,
                    'student_found' => $student ? 'yes' : 'no',
                    'has_google_token' => $student && $student->google_access_token ? 'yes' : 'no',
                ]);

                if ($student && $student->google_access_token) {
                    Log::info('Dispatching sync job for student', [
                        'schedule_id' => $roomSchedule->id,
                        'user_id' => $student->id,
                    ]);

                    SyncScheduleToGoogleCalendar::dispatch($roomSchedule, $student->id);
                }
            }
        } else {
            Log::warning('No participants or invalid type', [
                'schedule_id' => $roomSchedule->id,
                'room_id' => $room->id,
            ]);
        }
    }
}
