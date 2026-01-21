<?php

namespace App\Filament\App\Resources\RoomResource\Pages;

use App\Filament\App\Resources\RoomResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateRoom extends CreateRecord
{
    protected static string $resource = RoomResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = auth()->id();
        $data['meeting_id'] = (string) Str::uuid();
        $data['moderator_pw'] = Str::random(8);
        $data['attendee_pw'] = Str::random(8);

        // Default type to 'pending' if not set (no participants selected)
        if (empty($data['type'])) {
            $data['type'] = 'pending';
        }

        // Fix start_date for one-time schedules
        if (isset($data['schedules'])) {
            foreach ($data['schedules'] as &$schedule) {
                if ($schedule['type'] === 'once' && isset($schedule['scheduled_at'])) {
                    $schedule['start_date'] = \Carbon\Carbon::parse($schedule['scheduled_at'])->format('Y-m-d');
                }
            }
        }

        // If custom price is disabled, set base_price to null (so it falls back to lesson type price)
        if (empty($data['custom_price_enabled'])) {
            $data['base_price'] = null;
        }
        unset($data['custom_price_enabled']);

        return $data;
    }

    protected function afterCreate(): void
    {
        $teacher = auth()->user();

        // Determine type based on actual participant count after relationship is synced
        $participantCount = $this->record->participants()->count();
        $type = match (true) {
            $participantCount === 0 => 'pending',
            $participantCount === 1 => 'individual',
            default => 'group',
        };
        $this->record->update(['type' => $type]);

        // Notify all assigned participants about the new lesson
        foreach ($this->record->participants as $student) {
            $student->notify(new \App\Notifications\TeacherAssignedLesson($this->record, $teacher));
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
