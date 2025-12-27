<?php

namespace App\Filament\Student\Resources\RecordingResource\Pages;

use App\Filament\Student\Resources\RecordingResource;
use Filament\Resources\Pages\ListRecords;

class ListRecordings extends ListRecords
{
    protected static string $resource = RecordingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // No actions for students
        ];
    }
}
