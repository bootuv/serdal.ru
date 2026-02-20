<?php

namespace App\Filament\Resources\RoomResource\Pages;

use App\Filament\Resources\RoomResource;
use App\Models\RoomSchedule;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRoom extends EditRecord
{
    protected static string $resource = RoomResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

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

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // If custom price is disabled, set base_price to null
        if (empty($data['custom_price_enabled'])) {
            $data['base_price'] = null;
        }
        unset($data['custom_price_enabled']);

        return $data;
    }

    protected function getSchedulesHash(array $schedules): string
    {
        $simplified = array_map(function ($s) {
            $type = $s['type'] ?? 'recurring';

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

            $time = $s['start_time'] ?? $s['recurrence_time'] ?? null;
            if ($time) {
                $time = substr($time, 0, 5);
            }

            $days = $s['day_of_week'] ?? $s['recurrence_days'] ?? [];
            if (is_string($days))
                $days = json_decode($days, true) ?? [];
            if (is_array($days)) {
                sort($days);
                $days = json_encode($days);
            }

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
        $formScheduleIds = collect($this->data['schedules'] ?? [])
            ->pluck('id')
            ->filter()
            ->toArray();

        $existingScheduleIds = $this->record->schedules()->pluck('id')->toArray();
        $removedScheduleIds = array_diff($existingScheduleIds, $formScheduleIds);

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
        $type = $participantCount > 1 ? 'group' : 'individual';
        $this->record->updateQuietly(['type' => $type]);

        // Get the new participant IDs from form data
        $newParticipantIds = array_unique($this->data['participants'] ?? []);

        // Find newly added participants
        $addedParticipantIds = array_diff($newParticipantIds, $this->previousParticipantIds);

        // In Admin panel, teacher is the room owner
        $teacher = $this->record->user;

        // Notify new participants about lesson assignment
        foreach ($addedParticipantIds as $participantId) {
            $student = \App\Models\User::find($participantId);
            if ($student) {
                $student->notify(new \App\Notifications\TeacherAssignedLesson($this->record, $teacher));
            }
        }

        // Check for schedule changes using FORM DATA
        $currentSchedulesHash = $this->getSchedulesHash($this->data['schedules'] ?? []);

        if ($this->originalSchedulesHash !== $currentSchedulesHash) {
            // Notify exisiting participants (exclude newly added ones)
            $existingParticipants = array_diff($newParticipantIds, $addedParticipantIds);
            $existingParticipants = array_unique($existingParticipants);

            foreach ($existingParticipants as $participantId) {
                $student = \App\Models\User::find($participantId);
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
