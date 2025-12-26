<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class ScheduleCalendar extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-calendar';

    protected static ?string $navigationLabel = 'Расписание занятий';

    protected static ?string $title = '';

    protected static string $view = 'filament.pages.schedule-calendar';

    protected static ?int $navigationSort = 2;

    protected function getListeners(): array
    {
        return [
            "echo:rooms,.room.status.updated" => '$refresh',
            "echo:rooms,room.status.updated" => '$refresh',
            "echo:rooms,RoomStatusUpdated" => '$refresh',
        ];
    }

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
                        'end' => $schedule->scheduled_at->copy()->addMinutes($schedule->duration_minutes),
                        'owner' => $schedule->room->user->name,
                        'type' => 'once',
                        'room_type' => $schedule->room->type,
                        'duration' => $schedule->duration_minutes,
                        'is_running' => $schedule->room->is_running,
                    ];
                }
            } else {
                // Generate recurring events for next 3 months
                $current = $now->copy()->startOfDay();
                while ($current->lte($endDate)) {
                    if ($schedule->isActiveAt($current->copy()->setTimeFromTimeString($schedule->recurrence_time ?? '00:00'))) {
                        $startDt = $current->copy()->setTimeFromTimeString($schedule->recurrence_time);
                        $events[] = [
                            'id' => $schedule->id,
                            'room_id' => $schedule->room_id,
                            'title' => $schedule->room->name,
                            'start' => $startDt,
                            'end' => $startDt->copy()->addMinutes($schedule->duration_minutes),
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

        // Add running rooms that might not have a schedule for today
        $runningRooms = \App\Models\Room::where('is_running', true)
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
                    'start' => $now->copy()->startOfHour(), // Show at current hour
                    'end' => $now->copy()->addHour(), // 1 hour duration
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
