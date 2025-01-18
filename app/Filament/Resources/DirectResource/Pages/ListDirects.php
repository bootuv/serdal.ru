<?php

namespace App\Filament\Resources\DirectResource\Pages;

use App\Filament\Resources\DirectResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDirects extends ListRecords
{
    protected static string $resource = DirectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
