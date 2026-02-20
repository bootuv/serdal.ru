<?php

namespace App\Filament\App\Resources\LessonTypeResource\Pages;

use App\Filament\App\Resources\LessonTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateLessonType extends CreateRecord
{
    protected static string $resource = LessonTypeResource::class;

    public function getTitle(): string
    {
        return 'Создать базовую цену';
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = auth()->id();
        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    public static function canCreateAnother(): bool
    {
        return false;
    }
}
