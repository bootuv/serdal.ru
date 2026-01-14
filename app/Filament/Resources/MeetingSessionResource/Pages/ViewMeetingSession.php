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
                    $teacher = $this->record->user;
                    $roomName = $this->record->room->name ?? 'Урок';
                    $startedAt = $this->record->started_at?->format('d.m.Y H:i') ?? '';

                    $this->record->delete();

                    // Notify teacher that deletion was approved
                    if ($teacher) {
                        $teacher->notify(new \App\Notifications\SessionDeletionApproved($roomName, $startedAt));
                    }

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
                    $teacher = $this->record->user;

                    $this->record->update([
                        'deletion_requested_at' => null,
                        'deletion_reason' => null,
                    ]);

                    // Notify teacher that deletion was rejected
                    if ($teacher) {
                        $teacher->notify(new \App\Notifications\SessionDeletionRejected($this->record));
                    }

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
