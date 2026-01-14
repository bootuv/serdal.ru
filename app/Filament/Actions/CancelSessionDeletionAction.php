<?php

namespace App\Filament\Actions;

use App\Models\MeetingSession;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\Action;

class CancelSessionDeletionAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'cancelDeletionRequest';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('')
            ->tooltip('Отменить запрос на удаление')
            ->icon('heroicon-o-arrow-uturn-left')
            ->color('gray')
            ->requiresConfirmation()
            ->modalHeading('Отменить запрос на удаление?')
            ->action(function (MeetingSession $record) {
                $record->update([
                    'deletion_requested_at' => null,
                    'deletion_reason' => null,
                ]);

                Notification::make()
                    ->title('Запрос отменен')
                    ->success()
                    ->send();
            })
            ->visible(fn(MeetingSession $record) => !is_null($record->deletion_requested_at));
    }
}
