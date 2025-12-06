<?php

namespace App\Filament\App\Resources\LessonTypeResource\Pages;

use App\Filament\App\Resources\LessonTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateLessonType extends CreateRecord
{
    protected static string $resource = LessonTypeResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = auth()->id();
        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
