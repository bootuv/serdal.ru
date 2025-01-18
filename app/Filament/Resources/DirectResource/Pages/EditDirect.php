<?php

namespace App\Filament\Resources\DirectResource\Pages;

use App\Filament\Resources\DirectResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDirect extends EditRecord
{
    protected static string $resource = DirectResource::class;

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
}
