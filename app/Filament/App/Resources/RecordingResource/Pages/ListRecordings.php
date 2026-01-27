<?php

namespace App\Filament\App\Resources\RecordingResource\Pages;

use App\Filament\App\Resources\RecordingResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRecordings extends ListRecords
{
    protected static string $resource = RecordingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // No manual create action
        ];
    }

    public function getListeners(): array
    {
        return [
            "echo:recordings,.recording.updated" => '$refresh',
            "echo:recordings,recording.updated" => '$refresh',
            "echo:recordings,RecordingUpdated" => '$refresh',
        ];
    }

    public function mount(): void
    {
        try {
            // Optimization: Run sync in background with throttling
            $user = auth()->user();
            $cacheKey = "last_recordings_sync_{$user->id}";

            if (!\Illuminate\Support\Facades\Cache::has($cacheKey)) {
                \App\Jobs\SyncUserRecordings::dispatch($user);
                // Cache for 60 seconds to prevent spamming
                \Illuminate\Support\Facades\Cache::put($cacheKey, true, 60);
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Recording Sync Dispatch Error: ' . $e->getMessage());
        }

        parent::mount();
    }
}
