<?php

namespace App\Filament\Resources\RoomResource\Pages;

use App\Filament\Resources\RoomResource;
use App\Models\RoomSchedule;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Log;

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

    protected function beforeSave(): void
    {
        // Get current schedule IDs from the form
        $formScheduleIds = collect($this->data['schedules'] ?? [])
            ->pluck('id')
            ->filter()
            ->toArray();

        // Get existing schedule IDs from the database
        $existingScheduleIds = $this->record->schedules()->pluck('id')->toArray();

        // Find schedules that were removed (exist in DB but not in form)
        $removedScheduleIds = array_diff($existingScheduleIds, $formScheduleIds);

        Log::info('EditRoom beforeSave - checking for removed schedules', [
            'room_id' => $this->record->id,
            'form_schedule_ids' => $formScheduleIds,
            'existing_schedule_ids' => $existingScheduleIds,
            'removed_schedule_ids' => $removedScheduleIds,
        ]);

        // Delete removed schedules one by one to trigger observers
        foreach ($removedScheduleIds as $scheduleId) {
            $schedule = RoomSchedule::find($scheduleId);
            if ($schedule) {
                Log::info('Deleting schedule explicitly to trigger observer', [
                    'schedule_id' => $scheduleId,
                    'google_event_id' => $schedule->google_event_id,
                ]);
                $schedule->delete();
            }
        }
    }
}

