<?php

namespace App\Observers;

use App\Models\Room;

class RoomObserver
{
    /**
     * Handle the Room "created" event.
     * Set initial type based on participants (if any).
     */
    public function created(Room $room): void
    {
        // After creation, check if we need to update type
        $this->updateRoomType($room);
    }

    /**
     * Handle the Room "saved" event.
     * Automatically determine room type based on participant count.
     */
    public function saved(Room $room): void
    {
        // Update type after save
        $this->updateRoomType($room);
    }

    /**
     * Update room type based on participant count.
     */
    protected function updateRoomType(Room $room): void
    {
        // Count participants
        $participantCount = $room->participants()->count();

        // Determine type based on count
        $newType = $participantCount <= 1 ? 'individual' : 'group';

        // Only update if type changed (to avoid infinite loop)
        if ($room->type !== $newType) {
            $room->updateQuietly(['type' => $newType]);
        }
    }
}
