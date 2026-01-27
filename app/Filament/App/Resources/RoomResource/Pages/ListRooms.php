<?php

namespace App\Filament\App\Resources\RoomResource\Pages;

use App\Filament\App\Resources\RoomResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class ListRooms extends ListRecords
{
    protected static string $resource = RoomResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    public function getListeners(): array
    {
        return [
            "echo:rooms,.room.status.updated" => '$refresh',
            "echo:rooms,room.status.updated" => '$refresh',
            "echo:rooms,RoomStatusUpdated" => '$refresh',
        ];
    }

    public function mount(): void
    {
        $start = microtime(true);
        Log::info("[App] ListRooms::mount STARTED at " . $start);

        // Optimization: Throttle BBB sync to run at most once every 10 seconds per user
        // We key by user_id because users might use different servers (custom BBB keys)
        $userId = auth()->id();
        $cacheKey = "bbb_sync_throttle_{$userId}";
        $lastSync = Cache::get($cacheKey, 0);
        $shouldSync = time() - $lastSync > 10;

        Log::info("[App] ListRooms::mount Check Sync: " . (microtime(true) - $start) . "s. Should sync: " . ($shouldSync ? 'YES' : 'NO'));

        if ($shouldSync) {
            Cache::put($cacheKey, time(), 60);

            // Dispatch background job to sync status without blocking response
            Log::info("[App] Dispatching SyncUserBbbStatus job");
            // Standard dispatch to DB queue (Worker is running, so it will be picked up async)
            \App\Jobs\SyncUserBbbStatus::dispatch($userId);
        } else {
            Log::info("[App] ListRooms::mount Sync Skipped (Throttled)");
        }

        parent::mount();
        Log::info("[App] ListRooms::mount FINISHED at " . (microtime(true) - $start) . "s");
    }
}
