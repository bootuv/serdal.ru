<?php

namespace App\Filament\App\Pages;

use Filament\Pages\Page;

class ScheduleCalendar extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-calendar';

    protected static ?string $navigationLabel = 'Расписание занятий';

    protected static ?string $title = 'Календарь';

    protected static string $view = 'filament.app.pages.schedule-calendar';

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

        // Google Calendar Integration Buttons
        if ($isGoogleConnected) {
            $actions[] = \Filament\Actions\Action::make('disconnectGoogle')
                ->label('Отключить Google Calendar')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
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
        $schedules = \App\Models\RoomSchedule::with(['room.user'])
            ->whereHas('room', function ($query) {
                $query->where('user_id', auth()->id());
            })
            ->where('is_active', true)
            ->get();

        return [
            'schedules' => $schedules,
            'events' => $this->generateCalendarEvents($schedules),
        ];
    }

    protected function generateCalendarEvents($schedules)
    {
        $events = [];
        $start = now()->subMonths(1)->startOfMonth();
        $end = now()->addMonths(2)->endOfMonth();
        $userId = auth()->id();
        $now = now();

        foreach ($schedules as $schedule) {
            if ($schedule->type === 'once') {
                if ($schedule->scheduled_at && $schedule->scheduled_at->between($start, $end)) {
                    $events[] = [
                        'id' => $schedule->id,
                        'room_id' => $schedule->room_id,
                        'title' => $schedule->room->name,
                        'start' => $schedule->scheduled_at,
                        'end' => $schedule->scheduled_at->copy()->addMinutes($schedule->duration_minutes),
                        'owner' => $schedule->room->user->name,
                        'type' => 'once',
                        'room_type' => $schedule->room->type,
                        'duration' => $schedule->duration_minutes,
                        'is_running' => $schedule->room->is_running,
                    ];
                }
            } else {
                $current = $start->copy();
                while ($current->lte($end)) {
                    if ($schedule->isActiveAt($current->copy()->setTimeFromTimeString($schedule->recurrence_time ?? '00:00'))) {
                        $dt = $current->copy()->setTimeFromTimeString($schedule->recurrence_time);
                        $events[] = [
                            'id' => $schedule->id,
                            'room_id' => $schedule->room_id,
                            'title' => $schedule->room->name,
                            'start' => $dt,
                            'end' => $dt->copy()->addMinutes($schedule->duration_minutes),
                            'owner' => $schedule->room->user->name,
                            'type' => $schedule->recurrence_type,
                            'room_type' => $schedule->room->type,
                            'duration' => $schedule->duration_minutes,
                            'is_running' => $schedule->room->is_running,
                        ];
                    }
                    $current->addDay();
                }
            }
        }

        // Add running rooms owned by the tutor that might not have a schedule for today
        $runningRooms = \App\Models\Room::where('is_running', true)
            ->where('user_id', $userId)
            ->with('user')
            ->get();

        foreach ($runningRooms as $room) {
            // Check if this room is already in events for today
            $alreadyInEvents = collect($events)->contains(function ($event) use ($room, $now) {
                return $event['room_id'] === $room->id &&
                    $event['start']->isSameDay($now);
            });

            if (!$alreadyInEvents) {
                // Add as a running event for today
                $events[] = [
                    'id' => 'running-' . $room->id,
                    'room_id' => $room->id,
                    'title' => $room->name,
                    'start' => $now->copy()->startOfHour(),
                    'end' => $now->copy()->addHour(),
                    'owner' => $room->user->name,
                    'type' => 'running',
                    'room_type' => $room->type,
                    'duration' => 60,
                    'is_running' => true,
                ];
            }
        }

        return collect($events)->sortBy('start');
    }
}
