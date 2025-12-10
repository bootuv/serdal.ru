<?php

namespace App\Filament\App\Resources\FalseResource\Pages;

use App\Filament\App\Resources\FalseResource;
use Filament\Resources\Pages\Page;

class ScheduleCalendar extends Page
{
    protected static string $resource = FalseResource::class;

    protected static string $view = 'filament.app.resources.false-resource.pages.schedule-calendar';
}
