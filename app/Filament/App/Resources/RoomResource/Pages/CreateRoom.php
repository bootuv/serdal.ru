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

        // Determine type based on participant count
        $participantCount = isset($data['participants']) ? count($data['participants']) : 0;
        $data['type'] = $participantCount > 1 ? 'group' : 'individual';

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

    protected function afterCreate(): void
    {
        $teacher = auth()->user();

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
