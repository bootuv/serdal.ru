<?php

namespace App\Filament\Student\Widgets;

use Filament\Widgets\Widget;
use App\Models\Room;
use Illuminate\Database\Eloquent\Builder;

class UpcomingSessionsWidget extends Widget
{
    protected static string $view = 'filament.student.widgets.upcoming-sessions-widget';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 1;

    protected function getListeners(): array
    {
        return [
            "echo:rooms,.room.status.updated" => '$refresh',
        ];
    }

    protected function getViewData(): array
    {
        $now = now();
        $in24Hours = $now->copy()->addHours(24);

        // Get rooms where student is a participant, with next_start within 24 hours or in progress
        $rooms = Room::query()
            ->whereHas('participants', function (Builder $query) {
                $query->where('users.id', auth()->id());
            })
            ->get()
            ->filter(function ($room) use ($now, $in24Hours) {
                if (!$room->next_start) {
                    return false;
                }

                // Calculate end time based on duration
                $duration = $room->duration ?? 45;
                $endTime = $room->next_start->copy()->addMinutes($duration);

                // Show if:
                // 1. Upcoming within 24 hours (next_start is in future and within 24h)
                // 2. Currently in progress (started but not yet ended)
                $isUpcoming = $room->next_start->gte($now) && $room->next_start->lte($in24Hours);
                $isInProgress = $room->next_start->lte($now) && $endTime->gte($now);

                return $isUpcoming || $isInProgress;
            })
            ->sortBy('next_start')
            ->take(5);

        // Also get currently running rooms
        $runningRooms = Room::query()
            ->whereHas('participants', function (Builder $query) {
                $query->where('users.id', auth()->id());
            })
            ->where('is_running', true)
            ->get();

        // Merge running rooms first, then upcoming
        $allRooms = $runningRooms->merge($rooms)->unique('id')->take(5);

        return [
            'rooms' => $allRooms,
            'roomsUrl' => \App\Filament\Student\Resources\RoomResource::getUrl('index'),
        ];
    }
}
