<?php

namespace App\Filament\Resources\HelpArticleResource\Pages;

use App\Filament\Resources\HelpArticleResource;
use App\Filament\Resources\HelpArticleResource\Widgets\HelpCenterTabs;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListHelpArticles extends ListRecords
{
    protected static string $resource = HelpArticleResource::class;

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
