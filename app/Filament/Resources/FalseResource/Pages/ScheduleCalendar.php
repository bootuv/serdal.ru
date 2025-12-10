<?php

namespace App\Filament\Resources\FalseResource\Pages;

use App\Filament\Resources\FalseResource;
use Filament\Resources\Pages\Page;

class ScheduleCalendar extends Page
{
    protected static string $resource = FalseResource::class;

    protected static string $view = 'filament.resources.false-resource.pages.schedule-calendar';
}
