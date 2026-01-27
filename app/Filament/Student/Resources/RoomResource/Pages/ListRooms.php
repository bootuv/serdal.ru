<?php

namespace App\Filament\Student\Resources\RoomResource\Pages;

use App\Filament\Student\Resources\RoomResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class ListRooms extends ListRecords
{
    protected static string $resource = RoomResource::class;

    public function getListeners()
    {
        return [
            "echo:rooms,.room.status.updated" => '$refresh',
            "echo:rooms,room.status.updated" => '$refresh',
            "echo:rooms,RoomStatusUpdated" => '$refresh',
        ];
    }

    public function mount(): void
    {
        // Student typically doesn't manage rooms, they just VIEW them.
        // But if they need to see "Running" status in real time for "Join" button...
        // Usually Students see what Teachers started.
        // So Students rely on Teachers (or System) to update the status.
        // HOWEVER, if a student opens a page, maybe we should check if the room is running?
        // But the room belongs to the Teacher.
        // If we sync status for the Student, we need to know WHICH room they are looking at.
        // ListRooms shows all rooms assigned to student?

        // If we rely on the Teacher opening the page to sync, it might be outdated.
        // So yes, Student check should also trigger a sync, but for WHOM?
        // It should sync the rooms visible to the student.
        // Typically checks GLOBAL server if standard.

        // To be safe and consistent, we can dispatch the same "User" sync but we need to know which parameters to use.
        // Students don't have BBB settings usually. They use the Room's owner settings.

        // Simple approach: Dispatch Global Sync since students usually are on default server?
        // Or if they are attached to a teacher with custom server...

        // Given complexity, and the user request "for all roles", I will assume we want to trigger a check.
        // But maybe `SyncGlobalBbbStatus` is safer for Student view as well, covering the default case.

        // Let's implement Throttling + Global Sync Job here too.

        $start = microtime(true);
        $userId = auth()->id();
        $cacheKey = "bbb_sync_student_throttle_{$userId}";
        $lastSync = Cache::get($cacheKey, 0);
        $shouldSync = time() - $lastSync > 10;

        if ($shouldSync) {
            Cache::put($cacheKey, time(), 60);
            \App\Jobs\SyncGlobalBbbStatus::dispatch();
        }

        parent::mount();
    }
}
