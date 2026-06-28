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
                $room->updateNextStart();
                $count++;
            });

        if ($count > 0) {
            try {
                \App\Events\RoomStatusUpdated::dispatch();
            } catch (\Throwable $e) {
                // Ignore broadcast errors in console command
            }
        }

        $this->info("Updated {$count} rooms.");
    }
}
