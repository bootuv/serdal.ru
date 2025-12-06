<?php

namespace App\Filament\App\Resources\LessonTypeResource\Pages;

use App\Filament\App\Resources\LessonTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListLessonTypes extends ListRecords
{
    protected static string $resource = LessonTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
