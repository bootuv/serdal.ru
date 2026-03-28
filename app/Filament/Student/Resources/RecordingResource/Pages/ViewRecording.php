<?php

namespace App\Filament\Student\Resources\RecordingResource\Pages;

use App\Filament\Student\Resources\RecordingResource;
use App\Models\Recording;
use App\Models\Room;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;

class ViewRecording extends Page
{
    protected static string $resource = RecordingResource::class;

    protected static string $view = 'filament.app.resources.recording-resource.pages.view-recording';

    public Recording $record;

    public function mount(Recording $record): void
    {
        $this->record = $record;

        // Check if student has access to this recording (via teacher relationship)
        $teacherIds = auth()->user()->teachers()->pluck('users.id');

        $teacherRoomMeetingIds = Room::whereIn('user_id', $teacherIds)
            ->pluck('meeting_id')
            ->filter()
            ->toArray();

        if (!in_array($record->meeting_id, $teacherRoomMeetingIds)) {
            abort(403);
        }
    }

    public function getTitle(): string|Htmlable
    {
        return $this->record->name ?? 'Запись урока';
    }

    public function getBreadcrumbs(): array
    {
        return [
            RecordingResource::getUrl() => 'Записи',
            '#' => $this->record->name ?? 'Запись урока',
        ];
    }


}
