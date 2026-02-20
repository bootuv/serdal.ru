<?php

namespace App\Filament\App\Resources\RoomResource\Pages;

use App\Filament\App\Resources\RoomResource;
use App\Models\RoomSchedule;
use App\Models\User;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRoom extends EditRecord
{
    protected static string $resource = RoomResource::class;

    protected array $previousParticipantIds = [];

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected string $originalSchedulesHash = '';

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Split scheduled_at into date and time for schedules
        if (isset($data['schedules'])) {
            foreach ($data['schedules'] as &$schedule) {
                if (isset($schedule['scheduled_at'])) {
                    $datetime = \Carbon\Carbon::parse($schedule['scheduled_at']);
                    $schedule['scheduled_date'] = $datetime->format('Y-m-d');
                    $schedule['scheduled_time'] = $datetime->format('H:i');
                }
            }
        }

        return $data;
    }


    protected function getSchedulesHash(array $schedules): string
    {
        $simplified = array_map(function ($s) {
            // Determine type
            $type = $s['type'] ?? 'recurring'; // Default in DB usually

            // --- One-time logic ---
            // Form keys: scheduled_at, scheduled_date, scheduled_time
            // DB keys: scheduled_at (or start_date + start_time if mapped)

            $scheduledAt = $s['scheduled_at'] ?? null;
            if (!$scheduledAt && isset($s['scheduled_date'], $s['scheduled_time'])) {
                $scheduledAt = $s['scheduled_date'] . ' ' . $s['scheduled_time'];
            }
            if ($scheduledAt) {
                try {
                    $scheduledAt = \Carbon\Carbon::parse($scheduledAt)->format('Y-m-d H:i');
                } catch (\Exception $e) {
                }
            }

            // --- Recurring logic ---
            // Form keys: recurrence_time, recurrence_days, start_date, end_date
            // DB keys: start_time, day_of_week

            // Time
            $time = $s['start_time'] ?? $s['recurrence_time'] ?? null;
            if ($time) {
                // Formatting to HH:MM
                $time = substr($time, 0, 5);
            }

            // Days check
            $days = $s['day_of_week'] ?? $s['recurrence_days'] ?? [];
            if (is_string($days))
                $days = json_decode($days, true) ?? []; // DB stores as JSON sometimes or casted
            if (is_array($days)) {
                sort($days);
                $days = json_encode($days);
            }

            // Start/End Dates for recurring
            $startDate = $s['start_date'] ?? null;
            $endDate = $s['end_date'] ?? null;

            return [
                'type' => $type,
                'days' => $days,
                'time' => $time,
                'scheduled_at' => $scheduledAt,
                'duration' => $s['duration'] ?? $s['duration_minutes'] ?? null,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ];
        }, $schedules);

        // Sort to ensure order doesn't affect hash
        usort($simplified, fn($a, $b) => strcmp(json_encode($a), json_encode($b)));

        return md5(json_encode($simplified));
    }



    protected function beforeSave(): void
    {
        // 1. Capture INITIAL state from the DB before any changes are applied
        $this->previousParticipantIds = array_unique($this->record->participants()->pluck('users.id')->toArray());

        // Load fresh schedules from DB for initial hash
        $this->record->load('schedules');
        $this->originalSchedulesHash = $this->getSchedulesHash($this->record->schedules->toArray());

        // 2. Existing logic for cleaning up removed schedules
        // Get current schedule IDs from the form
        $formScheduleIds = collect($this->data['schedules'] ?? [])
            ->pluck('id')
            ->filter()
            ->toArray();

        // Get existing schedule IDs from the database
        $existingScheduleIds = $this->record->schedules()->pluck('id')->toArray();

        // Find schedules that were removed (exist in DB but not in form)
        $removedScheduleIds = array_diff($existingScheduleIds, $formScheduleIds);

        // Delete removed schedules one by one to trigger observers
        foreach ($removedScheduleIds as $scheduleId) {
            $schedule = RoomSchedule::find($scheduleId);
            if ($schedule) {
                $schedule->delete();
            }
        }
    }

    protected function afterSave(): void
    {
        // Determine type based on actual participant count after relationship is synced
        $this->record->refresh();
        $participantCount = $this->record->participants()->count();
        $type = match (true) {
            $participantCount === 0 => 'pending',
            $participantCount === 1 => 'individual',
            default => 'group',
        };
        $this->record->updateQuietly(['type' => $type]);

        // Get the new participant IDs from form data
        $newParticipantIds = array_unique($this->data['participants'] ?? []);

        // Find newly added participants
        $addedParticipantIds = array_diff($newParticipantIds, $this->previousParticipantIds);

        $teacher = auth()->user();

        // Notify new participants about lesson assignment
        foreach ($addedParticipantIds as $participantId) {
            $student = User::find($participantId);
            if ($student) {
                $student->notify(new \App\Notifications\TeacherAssignedLesson($this->record, $teacher));
            }
        }

        // Check for schedule changes using FORM DATA (most current)
        // We use $this->data['schedules'] because DB might not be updated yet or refresh() might return stale relations
        $currentSchedulesHash = $this->getSchedulesHash($this->data['schedules'] ?? []);

        if ($this->originalSchedulesHash !== $currentSchedulesHash) {
            // Notify exisiting participants (exclude newly added ones to avoid double notification)
            $existingParticipants = array_diff($newParticipantIds, $addedParticipantIds);
            $existingParticipants = array_unique($existingParticipants); // Ensure uniqueness

            foreach ($existingParticipants as $participantId) {
                $student = User::find($participantId);
                if ($student) {
                    $student->notify(new \App\Notifications\TeacherUpdatedSchedule($teacher));
                }
            }
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
