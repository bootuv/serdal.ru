<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class UpdateRoomNextStart extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'room:update-next-start';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update next_start timestamp for rooms with expired lessons';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Updating expired next_start dates...');

        // Find rooms where the scheduled start time has passed
        // We use cursor() to handle potentially large datasets efficiently
        $count = 0;

        \App\Models\Room::whereNotNull('next_start')
            ->where('next_start', '<', now())
            ->cursor()
            ->each(function ($room) use (&$count) {
                // If we want to be strict about duration, we can check it here.
                // But simply re-calculating is safer as it guarantees we find the true *next* occurrence.
                // If the immediate recalculation returns the SAME past date, it means there are no future occurrences.
                // But our calculateNextStart() filters for future dates mostly.
    
                // We should check if the lesson is TRULY over (start + duration).
                // Or we can rely on `calculateNextStart` logic.
    
                // Let's rely on updateNextStart() to do the heavy lifting.
                // It will call calculateNextStart(), which returns the earliest FUTURE occurrence.
                // So if the current `next_start` is past, the new one will be future (or null).
                $room->updateNextStart();

                // Dispatch event to trigger Livewire refresh on clients (via Pusher)
                // This ensures the list re-sorts automatically for everyone
                try {
                    \App\Events\RoomStatusUpdated::dispatch();
                } catch (\Throwable $e) {
                    // Ignore broadcast errors in console command
                }

                $count++;
            });

        $this->info("Updated {$count} rooms.");
    }
}
