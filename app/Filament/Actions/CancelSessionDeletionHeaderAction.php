<?php

namespace App\Filament\Actions;

use App\Models\MeetingSession;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class CancelSessionDeletionHeaderAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'deletionStatus';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Ожидает удаления')
            ->icon('heroicon-o-clock')
            ->color('warning')
            ->badge()
            ->modalHeading('Запрос на удаление')
            ->modalSubmitActionLabel('Отменить запрос')
            ->modalCancelActionLabel('Закрыть');
    }

    public static function makeForRecord(MeetingSession $record): static
    {
        return static::make()
            ->modalDescription(fn() => "Причина: {$record->deletion_reason}")
            ->action(function () use ($record) {
                $record->update([
                    'deletion_requested_at' => null,
                    'deletion_reason' => null,
                ]);

                Notification::make()
                    ->title('Запрос отменен')
                    ->success()
                    ->send();
            })
            ->visible(fn() => !is_null($record->deletion_requested_at));
    }
}
