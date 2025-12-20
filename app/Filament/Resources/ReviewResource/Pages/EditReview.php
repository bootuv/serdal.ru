<?php

namespace App\Filament\Resources\ReviewResource\Pages;

use App\Filament\Resources\ReviewResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditReview extends EditRecord
{
    protected static string $resource = ReviewResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('dismiss_report')
                ->label('Снять жалобу')
                ->color('gray')
                ->icon('heroicon-o-check-circle')
                ->requiresConfirmation()
                ->modalHeading('Снять жалобу')
                ->modalDescription('Вы уверены, что хотите снять жалобу с этого отзыва?')
                ->visible(fn() => $this->record->is_reported && !$this->record->is_rejected)
                ->action(function () {
                    $this->record->update(['is_reported' => false]);
                    \Filament\Notifications\Notification::make()
                        ->title('Жалоба снята')
                        ->success()
                        ->send();
                }),
            Actions\Action::make('reject')
                ->label('Отклонить отзыв')
                ->color('danger')
                ->icon('heroicon-o-x-circle')
                ->requiresConfirmation()
                ->modalHeading('Отклонить отзыв')
                ->modalDescription('Вы уверены, что хотите отклонить этот отзыв? Студент не сможет оставить новый отзыв этому учителю.')
                ->visible(fn() => !$this->record->is_rejected)
                ->action(function () {
                    $this->record->update(['is_rejected' => true, 'is_reported' => false]);
                    \Filament\Notifications\Notification::make()
                        ->title('Отзыв отклонен')
                        ->success()
                        ->send();
                    return redirect()->route('filament.admin.resources.reviews.index');
                }),
            Actions\Action::make('restore')
                ->label('Вернуть отзыв')
                ->color('success')
                ->icon('heroicon-o-arrow-uturn-left')
                ->requiresConfirmation()
                ->modalHeading('Вернуть отзыв')
                ->modalDescription('Вы уверены, что хотите вернуть этот отзыв? Он снова станет видимым для учителя.')
                ->visible(fn() => $this->record->is_rejected)
                ->action(function () {
                    $this->record->update(['is_rejected' => false]);
                    \Filament\Notifications\Notification::make()
                        ->title('Отзыв восстановлен')
                        ->success()
                        ->send();
                }),
            Actions\ActionGroup::make([
                Actions\DeleteAction::make(),
            ])
                ->color('gray'),
        ];
    }

    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction(),
            $this->getCancelFormAction(),
        ];
    }
}
