<?php

namespace App\Filament\Actions;

use App\Models\MeetingSession;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;

class RequestSessionDeletionHeaderAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'requestDeletion';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Запросить удаление')
            ->icon('heroicon-o-trash')
            ->color('gray')
            ->outlined()
            ->requiresConfirmation()
            ->modalIcon('heroicon-o-exclamation-triangle')
            ->modalIconColor('warning')
            ->modalHeading('Запрос на удаление сессии')
            ->modalDescription('Укажите причину удаления. Администратор рассмотрит ваш запрос.')
            ->form([
                Textarea::make('deletion_reason')
                    ->label('Причина удаления')
                    ->required(),
            ]);
    }

    public static function makeForRecord(MeetingSession $record, callable $onSuccess = null): static
    {
        return static::make()
            ->action(function (array $data) use ($record, $onSuccess) {
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

                if ($onSuccess) {
                    $onSuccess();
                }
            })
            ->visible(fn() => is_null($record->deletion_requested_at));
    }
}
