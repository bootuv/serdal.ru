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
            Notification::make()
                ->title('Новое задание')
                ->body('Вам назначено: ' . $this->record->title)
                ->icon($this->record->type_icon)
                ->iconColor($this->record->type_color)
                ->actions([
                    Action::make('view')
                        ->label('Открыть')
                        ->button()
                        ->url(route('filament.student.resources.homework.view', $this->record)),
                ])
                ->sendToDatabase($student)
                ->broadcast($student);
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
