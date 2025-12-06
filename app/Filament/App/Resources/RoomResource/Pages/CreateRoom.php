<?php

namespace App\Filament\App\Resources\RoomResource\Pages;

use App\Filament\App\Resources\RoomResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateRoom extends CreateRecord
{
    protected static string $resource = RoomResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = auth()->id();
        $data['meeting_id'] = (string) Str::uuid();
        $data['moderator_pw'] = Str::random(8);
        $data['attendee_pw'] = Str::random(8);

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
