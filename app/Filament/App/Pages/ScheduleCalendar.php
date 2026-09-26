<?php

namespace App\Filament\App\Pages;

use Filament\Pages\Page;

class ScheduleCalendar extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-calendar';

    protected static ?string $navigationLabel = 'Расписание занятий';

    protected static ?string $title = 'Календарь';

    protected static string $view = 'filament.app.pages.schedule-calendar';

    protected static ?int $navigationSort = 3;

    protected function getListeners(): array
    {
        return [
            "echo:rooms,.room.status.updated" => '$refresh',
            "echo:rooms,room.status.updated" => '$refresh',
            "echo:rooms,RoomStatusUpdated" => '$refresh',
        ];
    }

    protected function getHeaderActions(): array
    {
        $user = auth()->user();
        $isGoogleConnected = !empty($user->google_access_token);

        $actions = [];

        // Google Calendar Integration Buttons
        if ($isGoogleConnected) {
            $actions[] = \Filament\Actions\Action::make('disconnectGoogle')
                ->label('Отключить Google Calendar')
                ->icon('heroicon-o-x-circle')
                ->color('gray')
                ->outlined()
                ->requiresConfirmation()
                ->modalHeading('Отключить Google Calendar?')
                ->modalDescription('Синхронизация с Google Calendar будет отключена.')
                ->modalSubmitActionLabel('Отключить')
                ->action(function () {
                    return redirect()->to(route('google.calendar.disconnect'));
                });
        } else {
            $actions[] = \Filament\Actions\Action::make('connectGoogle')
                ->label('Подключить Google Calendar')
                ->icon('heroicon-o-calendar')
                ->color('info')
                ->url(route('google.calendar.connect'))
                ->openUrlInNewTab(false);
        }

        return $actions;
    }

    public function getViewData(): array
    {
        $service = app(\App\Services\TeacherScheduleService::class);
        $schedules = $service->schedules(auth()->id());

        return [
            'schedules' => $schedules,
            // Вхождения и идущие занятия — общий расчёт с новым кабинетом учителя
            'events' => $service->events(auth()->id(), now()->subMonths(1)->startOfMonth(), now()->addMonths(2)->endOfMonth(), $schedules),
        ];
    }
}
