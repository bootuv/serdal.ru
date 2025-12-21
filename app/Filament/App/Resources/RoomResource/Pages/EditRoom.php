<?php

namespace App\Filament\App\Resources\RoomResource\Pages;

use App\Filament\App\Resources\RoomResource;
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

        // Delete removed schedules one by one to trigger observers
        foreach ($removedScheduleIds as $scheduleId) {
            $schedule = RoomSchedule::find($scheduleId);
            if ($schedule) {
                $schedule->delete();
            }
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
