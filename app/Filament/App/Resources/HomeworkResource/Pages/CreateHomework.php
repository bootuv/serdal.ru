<?php

namespace App\Filament\App\Resources\HomeworkResource\Pages;

use App\Filament\App\Resources\HomeworkResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;
use Filament\Notifications\Actions\Action;

class CreateHomework extends CreateRecord
{
    protected static string $resource = HomeworkResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['teacher_id'] = auth()->id();
        return $data;
    }

    protected function afterCreate(): void
    {
        // Отправить уведомления всем назначенным ученикам
        foreach ($this->record->students as $student) {
            $student->notify(new \App\Notifications\NewHomework($this->record));
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
