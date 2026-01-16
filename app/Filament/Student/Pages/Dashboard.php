<?php

namespace App\Filament\Student\Pages;

use Filament\Pages\Page;

class Dashboard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-home';

    protected static ?string $navigationLabel = 'Инфопанель';

    protected static ?string $title = 'Инфопанель';

    protected static string $view = 'filament.student.pages.dashboard';

    protected static ?int $navigationSort = -2;
}
