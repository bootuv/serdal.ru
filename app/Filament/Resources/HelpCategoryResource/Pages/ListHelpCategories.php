<?php

namespace App\Filament\Resources\HelpCategoryResource\Pages;

use App\Filament\Resources\HelpCategoryResource;
use App\Filament\Resources\HelpArticleResource\Widgets\HelpCenterTabs;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListHelpCategories extends ListRecords
{
    protected static string $resource = HelpCategoryResource::class;

    protected static ?string $title = 'База знаний';

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            HelpCenterTabs::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }
}
