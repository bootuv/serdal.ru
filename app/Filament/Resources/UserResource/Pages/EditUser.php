<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('assignSubscription')
                ->label('Назначить подписку')
                ->icon('heroicon-o-credit-card')
                ->visible(fn() => in_array($this->record->role, [\App\Models\User::ROLE_TUTOR, \App\Models\User::ROLE_MENTOR]))
                ->modalHeading(fn() => 'Назначить подписку: ' . $this->record->name)
                ->modalDescription(function () {
                    $current = $this->record->activeSubscription();

                    return $current
                        ? 'Сейчас: «' . $current->tariff->name . '»' . ($current->ends_at ? ' до ' . $current->ends_at->format('d.m.Y') : ' (бессрочно)') . '. Текущая подписка будет заменена.'
                        : 'У пользователя нет активной подписки.';
                })
                ->form(UserResource::assignSubscriptionForm())
                ->action(fn(array $data) => UserResource::assignSubscription($this->record, $data)),
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Process Avatar
        if (isset($data['avatar'])) {
            $processed = \App\Helpers\FileUploadHelper::processFiles(
                $data['avatar'],
                'avatars',
                640,
                640
            );
            $data['avatar'] = $processed[0] ?? null;
        }

        return $data;
    }

    protected function afterSave(): void
    {
        // If the user edited their own profile, we need to re-login to keep the session
        // especially if the password was changed
        if ($this->record->id === auth()->id()) {
            \Illuminate\Support\Facades\Auth::login($this->record);
        }
    }
}
