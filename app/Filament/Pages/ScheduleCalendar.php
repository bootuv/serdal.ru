<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class ScheduleCalendar extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-calendar';

    protected static ?string $navigationLabel = 'Расписание занятий';

    protected static ?string $title = '';

    protected static string $view = 'filament.pages.schedule-calendar';

    protected static ?int $navigationSort = 1;

    public function getViewData(): array
    {
        // Get all schedules for admin
        $schedules = \App\Models\RoomSchedule::with(['room.user'])
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
        $now = now();
        $endDate = $now->copy()->addMonths(3);

        foreach ($schedules as $schedule) {
            if ($schedule->type === 'once') {
                if ($schedule->scheduled_at && $schedule->scheduled_at->gte($now)) {
                    $events[] = [
                        'id' => $schedule->id,
                        'room_id' => $schedule->room_id,
                        'title' => $schedule->room->name,
                        'start' => $schedule->scheduled_at,
                        'owner' => $schedule->room->user->name,
                        'type' => 'once',
                        'duration' => $schedule->duration_minutes,
                    ];
                }
            } else {
                // Generate recurring events for next 3 months
                $current = $now->copy()->startOfDay();
                while ($current->lte($endDate)) {
                    if ($schedule->isActiveAt($current->copy()->setTimeFromTimeString($schedule->recurrence_time ?? '00:00'))) {
                        $events[] = [
                            'id' => $schedule->id,
                            'room_id' => $schedule->room_id,
                            'title' => $schedule->room->name,
                            'start' => $current->copy()->setTimeFromTimeString($schedule->recurrence_time),
                            'owner' => $schedule->room->user->name,
                            'type' => $schedule->recurrence_type,
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
