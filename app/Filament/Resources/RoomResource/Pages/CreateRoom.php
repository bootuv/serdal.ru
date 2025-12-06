<?php

namespace App\Filament\Resources\RoomResource\Pages;

use App\Filament\Resources\RoomResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateRoom extends CreateRecord
{
    protected static string $resource = RoomResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['meeting_id'] = (string) \Illuminate\Support\Str::uuid();
        $data['moderator_pw'] = \Illuminate\Support\Str::random(8);
        $data['attendee_pw'] = \Illuminate\Support\Str::random(8);

        return $data;
    }
}
