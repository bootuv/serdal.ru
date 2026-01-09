<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

class DashboardWelcomeOverview extends Widget
{
    protected static string $view = 'filament.widgets.dashboard-welcome-overview';

    protected int|string|array $columnSpan = 'full';
}
