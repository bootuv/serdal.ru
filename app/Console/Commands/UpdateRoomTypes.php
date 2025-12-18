<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Room;

class UpdateRoomTypes extends Command
{
    protected $signature = 'rooms:update-types';
    protected $description = 'Update room types based on participant count';

    public function handle()
    {
        $this->info('Updating room types...');

        $updated = 0;

        Room::chunk(100, function ($rooms) use (&$updated) {
            foreach ($rooms as $room) {
                $count = $room->participants()->count();
                $newType = $count <= 1 ? 'individual' : 'group';

                if ($room->type !== $newType) {
                    $room->updateQuietly(['type' => $newType]);
                    $this->line("Updated room #{$room->id} '{$room->name}': {$count} participants -> {$newType}");
                    $updated++;
                }
            }
        });

        $this->info("Done! Updated {$updated} rooms.");

        return 0;
    }
}
