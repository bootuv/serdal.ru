<?php

namespace App\Observers;

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
        $this->syncToGoogleCalendar($roomSchedule);
    }

    /**
     * Handle the RoomSchedule "deleted" event.
     */
    public function deleted(RoomSchedule $roomSchedule): void
    {
        // TODO: Delete event from Google Calendar
        Log::info('Schedule deleted, should remove from Google Calendar', [
            'schedule_id' => $roomSchedule->id,
            'google_event_id' => $roomSchedule->google_event_id,
        ]);
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
            'participants' => $room->participants,
            'is_array' => is_array($room->participants),
        ]);

        if ($room->participants && is_array($room->participants)) {
            foreach ($room->participants as $studentId) {
                $student = \App\Models\User::find($studentId);

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
            Log::warning('No participants or participants not an array', [
                'schedule_id' => $roomSchedule->id,
                'room_id' => $room->id,
            ]);
        }
    }
}
