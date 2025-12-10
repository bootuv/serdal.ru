<?php

namespace App\Filament\App\Widgets;

use Filament\Widgets\Widget;
use App\Models\RoomSchedule;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class UpcomingSessionsWidget extends Widget
{
    protected static string $view = 'filament.app.widgets.upcoming-sessions-widget';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 1;

    protected function getViewData(): array
    {
        $schedules = RoomSchedule::query()
            ->whereHas('room', function ($query) {
                $query->where('user_id', auth()->id());
            })
            ->where('is_active', true)
            ->with(['room'])
            ->get();

        $events = $this->generateUpcomingEvents($schedules);

        return [
            'events' => $events->take(5),
        ];
    }

    protected function generateUpcomingEvents($schedules): Collection
    {
        $events = [];
        $now = now();
        $endDate = $now->copy()->addMonths(2); // Check next 2 months

        foreach ($schedules as $schedule) {
            if ($schedule->type === 'once') {
                if ($schedule->scheduled_at && $schedule->scheduled_at->gte($now)) {
                    $events[] = [
                        'id' => $schedule->id,
                        'room_id' => $schedule->room_id,
                        'title' => $schedule->room->name ?? 'Без названия',
                        'start' => $schedule->scheduled_at,
                        'type' => 'once',
                        'duration' => $schedule->duration_minutes,
                    ];
                }
            } else {
                // Generate recurring events
                $current = $now->copy()->startOfDay();
                while ($current->lte($endDate)) {
                    // Check if schedule is active at this date AND specifically at the recurrence time
                    $checkTime = $current->copy()->setTimeFromTimeString($schedule->recurrence_time ?? '00:00');

                    // We only want future events, so if checkTime is in the past, skip, 
                    // unless it's today and the time hasn't passed (handled by gte below).

                    if ($checkTime->gte($now) && $schedule->isActiveAt($checkTime)) {
                        $events[] = [
                            'id' => $schedule->id,
                            'room_id' => $schedule->room_id,
                            'title' => $schedule->room->name ?? 'Без названия',
                            'start' => $checkTime,
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
