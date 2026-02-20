<?php

namespace App\Filament\App\Resources\LessonTypeResource\Pages;

use App\Filament\App\Resources\LessonTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditLessonType extends EditRecord
{
    protected static string $resource = LessonTypeResource::class;

    public function getTitle(): string
    {
        return 'Редактировать цену';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
