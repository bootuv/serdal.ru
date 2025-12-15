<?php

namespace App\Filament\Student\Resources\RoomResource\Pages;

use App\Filament\Student\Resources\RoomResource;
use Filament\Resources\Pages\ListRecords;

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
}
