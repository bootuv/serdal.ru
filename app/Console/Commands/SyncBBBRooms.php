<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Room;
use JoisarJignesh\Bigbluebutton\Facades\Bigbluebutton;
use App\Events\RoomStatusUpdated;
use App\Models\Setting;

class SyncBBBRooms extends Command
{
    protected $signature = 'bbb:sync';
    protected $description = 'Synchronize running rooms status with BBB server';

    public function handle()
    {
        $rooms = Room::where('is_running', true)->get();
        $this->info("Found " . $rooms->count() . " running rooms. Checking status...");

        foreach ($rooms as $room) {
            $this->checkRoom($room);
        }

        $this->info("Sync completed.");
    }

    protected function checkRoom(Room $room)
    {
        // Настройка конфига BBB для конкретной комнаты (если у владельца свои настройки)
        $owner = $room->user;
        if ($owner && $owner->bbb_url && $owner->bbb_secret) {
            config([
                'bigbluebutton.BBB_SERVER_BASE_URL' => $owner->bbb_url,
                'bigbluebutton.BBB_SECURITY_SALT' => $owner->bbb_secret,
            ]);
        } else {
            // Глобальные настройки
            $globalUrl = Setting::where('key', 'bbb_url')->value('value');
            $globalSecret = Setting::where('key', 'bbb_secret')->value('value');

            if ($globalUrl && $globalSecret) {
                config([
                    'bigbluebutton.BBB_SERVER_BASE_URL' => $globalUrl,
                    'bigbluebutton.BBB_SECURITY_SALT' => $globalSecret,
                ]);
            }
        }

        try {
            if (!Bigbluebutton::isMeetingRunning(['meetingID' => $room->meeting_id])) {
                $room->update(['is_running' => false]);

                // Закрываем сессию если есть
                \App\Models\MeetingSession::where('room_id', $room->id)
                    ->where('meeting_id', $room->meeting_id)
                    ->where('status', 'running')
                    ->update([
                        'ended_at' => now(),
                        'status' => 'completed',
                        // 'notes' => 'Closed via sync command' // Removed non-existent column
                    ]);

                RoomStatusUpdated::dispatch();
                $this->info("Room '{$room->name}' (ID: {$room->id}) was not running and has been stopped.");
            } else {
                $this->line("Room '{$room->name}' is valid and running.");
            }
        } catch (\Exception $e) {
            $this->error("Error checking room '{$room->name}': " . $e->getMessage());
        }
    }
}
