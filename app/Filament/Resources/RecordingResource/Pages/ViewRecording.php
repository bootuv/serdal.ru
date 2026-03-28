<?php

namespace App\Filament\Resources\RecordingResource\Pages;

use App\Filament\Resources\RecordingResource;
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
