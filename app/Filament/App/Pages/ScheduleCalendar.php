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
                        ];
                    }
                    $current->addDay();
                }
            }
        }
        return collect($events)->sortBy('start');
    }
}
