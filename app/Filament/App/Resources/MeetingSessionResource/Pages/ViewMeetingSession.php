<?php

namespace App\Filament\App\Resources\MeetingSessionResource\Pages;

use App\Filament\App\Resources\MeetingSessionResource;
use Filament\Resources\Pages\ViewRecord;

class ViewMeetingSession extends ViewRecord
{
    protected static string $resource = MeetingSessionResource::class;

    protected static string $view = 'filament.app.resources.meeting-session-resource.pages.view-meeting-session';

    public function getTitle(): string
    {
        return 'Отчет о сессии';
    }

    public function getHeading(): string
    {
        return $this->record->room->name ?? 'Отчет о вебинаре';
    }
}
