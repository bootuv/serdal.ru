<?php

namespace App\Filament\Resources\MeetingSessionResource\Pages;

use App\Filament\Resources\MeetingSessionResource;
use App\Models\MeetingSession;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewMeetingSession extends ViewRecord
{
    protected static string $resource = MeetingSessionResource::class;

    protected static string $view = 'filament.resources.meeting-session-resource.pages.view-meeting-session';

    public function getTitle(): string
    {
        return 'Отчет о сессии';
    }

    public function getHeading(): string
    {
        return $this->record->room->name ?? 'Отчет о вебинаре';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('approveDeletion')
                ->label('Одобрить')
                ->icon('heroicon-o-check')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Подтверждение удаления')
                ->modalDescription('Вы действительно хотите удалить эту сессию? Это действие необратимо.')
                ->action(function () {
                    app(\App\Services\SessionDeletionService::class)->approve($this->record, auth()->user());

                    \Filament\Notifications\Notification::make()
                        ->title('Сессия удалена')
                        ->success()
                        ->send();

                    $this->redirect($this->getResource()::getUrl('index'));
                })
                ->visible(fn() => !is_null($this->record->deletion_requested_at)),

            Actions\Action::make('rejectDeletion')
                ->label('Отклонить')
                ->icon('heroicon-o-x-mark')
                ->color('gray')
                ->outlined()
                ->requiresConfirmation()
                ->action(function () {
                    app(\App\Services\SessionDeletionService::class)->reject($this->record, auth()->user());

                    \Filament\Notifications\Notification::make()
                        ->title('Запрос отклонен')
                        ->success()
                        ->send();
                })
                ->visible(fn() => !is_null($this->record->deletion_requested_at)),

            Actions\DeleteAction::make()
                ->label('Удалить')
                ->visible(fn() => is_null($this->record->deletion_requested_at)),
        ];
    }
}
