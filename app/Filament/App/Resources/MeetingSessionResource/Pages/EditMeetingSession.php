<?php

namespace App\Filament\App\Resources\MeetingSessionResource\Pages;

use App\Filament\App\Resources\MeetingSessionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMeetingSession extends EditRecord
{
    protected static string $resource = MeetingSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
