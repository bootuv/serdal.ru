<?php

namespace App\Filament\App\Resources\RecordingResource\Pages;

use App\Filament\App\Resources\RecordingResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRecordings extends ListRecords
{
    protected static string $resource = RecordingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // No manual create action
        ];
    }

    public function getListeners(): array
    {
        return [
            "echo:recordings,.recording.updated" => '$refresh',
            "echo:recordings,recording.updated" => '$refresh',
            "echo:recordings,RecordingUpdated" => '$refresh',
        ];
    }

    public function mount(): void
    {
        // Новые записи подтягиваются в фоне, не чаще раза в минуту (общий сервис с новым кабинетом)
        app(\App\Services\TeacherRecordingsService::class)->syncInBackground(auth()->user());

        parent::mount();
    }
}
