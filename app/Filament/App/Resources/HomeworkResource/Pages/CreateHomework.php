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
        // Уведомить назначенных учеников (о черновике не сообщаем) — общий сервис с новым кабинетом
        app(\App\Services\HomeworkSubmissionService::class)->notifyAssigned($this->record, $this->record->students);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
