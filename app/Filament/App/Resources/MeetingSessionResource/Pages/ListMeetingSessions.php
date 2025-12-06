<?php

namespace App\Filament\App\Resources\MeetingSessionResource\Pages;

use App\Filament\App\Resources\MeetingSessionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMeetingSessions extends ListRecords
{
    protected static string $resource = MeetingSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Sessions are auto-created, no manual creation
        ];
    }
}
