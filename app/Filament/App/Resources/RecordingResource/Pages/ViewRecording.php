<?php

namespace App\Filament\App\Resources\RecordingResource\Pages;

use App\Filament\App\Resources\RecordingResource;
use App\Models\Recording;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

class ViewRecording extends Page
{
    protected static string $resource = RecordingResource::class;

    protected static string $view = 'filament.app.resources.recording-resource.pages.view-recording';

    public Recording $record;

    public function mount(Recording $record): void
    {
        $this->record = $record;

        // Check if user has access to this recording
        $userMeetingIds = \App\Models\Room::where('user_id', auth()->id())->pluck('meeting_id')->toArray();
        if (!in_array($record->meeting_id, $userMeetingIds)) {
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
