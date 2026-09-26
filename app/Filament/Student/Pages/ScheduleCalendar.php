<?php

namespace App\Filament\Student\Pages;

use Filament\Pages\Page;

class ScheduleCalendar extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-calendar';

    protected static ?string $navigationLabel = 'Расписание занятий';

    protected static ?string $title = 'Календарь';

    protected static string $view = 'filament.student.pages.schedule-calendar';

    protected static ?int $navigationSort = 2;

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

        // Google Calendar Integration for Students
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
                ->label('Добавить в мой Google Calendar')
                ->icon('heroicon-o-calendar')
                ->color('info')
                ->url(route('google.calendar.connect'))
                ->openUrlInNewTab(false);
        }

        return $actions;
    }

    public function getViewData(): array
    {
        $user = auth()->user();
        $service = app(\App\Services\StudentScheduleService::class);

        // Расписания занятий, в которых ученик — участник; вхождения — общий расчёт с новым кабинетом
        $schedules = $service->schedules($user->id);

        return [
            'schedules' => $schedules,
            'events' => $service->events($user->id, now()->subMonth()->startOfMonth(), now()->addMonths(2)->endOfMonth(), $schedules),
            // Преподаватели, к чьим занятиям ученик сейчас не допускается из-за просроченной оплаты
            'blockedTeacherIds' => \App\Services\PaymentRecordService::blockedTeacherIds($user->id),
        ];
    }
}
