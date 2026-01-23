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
}
