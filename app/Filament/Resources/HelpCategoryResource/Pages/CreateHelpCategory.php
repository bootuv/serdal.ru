<?php

namespace App\Filament\Resources\HelpCategoryResource\Pages;

use App\Filament\Resources\HelpCategoryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateHelpCategory extends CreateRecord
{
    protected static string $resource = HelpCategoryResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
