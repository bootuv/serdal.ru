<?php

namespace App\Filament\Resources\RoomResource\Pages;

use App\Filament\Resources\RoomResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateRoom extends CreateRecord
{
    protected static string $resource = RoomResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['meeting_id'] = (string) \Illuminate\Support\Str::uuid();
        $data['moderator_pw'] = \Illuminate\Support\Str::random(8);
        $data['attendee_pw'] = \Illuminate\Support\Str::random(8);

        // Fix start_date for one-time schedules
        if (isset($data['schedules'])) {
            foreach ($data['schedules'] as &$schedule) {
                if ($schedule['type'] === 'once' && isset($schedule['scheduled_at'])) {
                    $schedule['start_date'] = \Carbon\Carbon::parse($schedule['scheduled_at'])->format('Y-m-d');
                }
            }
        }

        return $data;
    }
}
