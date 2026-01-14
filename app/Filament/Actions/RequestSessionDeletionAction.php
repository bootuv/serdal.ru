<?php

namespace App\Filament\Actions;

use App\Models\MeetingSession;
use App\Models\User;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\Action;

class RequestSessionDeletionAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'requestDeletion';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('')
            ->tooltip('Запросить удаление')
            ->icon('heroicon-o-trash')
            ->color('gray')
            ->requiresConfirmation()
            ->modalIcon('heroicon-o-exclamation-triangle')
            ->modalIconColor('warning')
            ->modalHeading('Запрос на удаление сессии')
            ->modalDescription('Укажите причину удаления. Администратор рассмотрит ваш запрос.')
            ->form([
                Textarea::make('deletion_reason')
                    ->label('Причина удаления')
                    ->required(),
            ])
            ->action(function (MeetingSession $record, array $data) {
                $record->update([
                    'deletion_requested_at' => now(),
                    'deletion_reason' => $data['deletion_reason'],
                ]);

                // Notify admins about the deletion request
                $admins = User::where('role', User::ROLE_ADMIN)->get();
                foreach ($admins as $admin) {
                    $admin->notify(new \App\Notifications\SessionDeletionRequested($record, auth()->user()));
                }

                Notification::make()
                    ->title('Запрос отправлен')
                    ->success()
                    ->send();
            })
            ->visible(fn(MeetingSession $record) => is_null($record->deletion_requested_at));
    }
}
