<?php

namespace App\Filament\App\Pages;

use App\Models\Room;
use Filament\Pages\Page;

class Messenger extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationLabel = 'Сообщения';

    protected static ?string $title = 'Сообщения';

    protected static string $view = 'filament.app.pages.messenger';

    protected static ?int $navigationSort = 3;

    public ?int $selectedRoomId = null;

    public function mount(): void
    {
        // Можно передать room_id через query параметр
        $this->selectedRoomId = request()->query('room');
    }

    public function getViewData(): array
    {
        $user = auth()->user();

        // Получаем занятия, где пользователь является владельцем
        $rooms = Room::where('user_id', $user->id)
            ->withCount('messages')
            ->with([
                'participants',
                'messages' => function ($query) {
                    $query->latest()->limit(1);
                }
            ])
            ->orderByDesc(function ($query) {
                $query->select('created_at')
                    ->from('messages')
                    ->whereColumn('room_id', 'rooms.id')
                    ->latest()
                    ->limit(1);
            })
            ->get();

        $selectedRoom = $this->selectedRoomId
            ? $rooms->firstWhere('id', $this->selectedRoomId)
            : null;

        return [
            'rooms' => $rooms,
            'selectedRoom' => $selectedRoom,
        ];
    }

    public function selectRoom(int $roomId): void
    {
        $this->selectedRoomId = $roomId;
    }

    public function getListeners()
    {
        $listeners = [
            "echo-private:App.Models.User." . auth()->id() . ",.Illuminate\\Notifications\\Events\\BroadcastNotificationCreated" => 'refreshRooms',
        ];

        $user = auth()->user();
        // Подписываемся на все комнаты, где пользователь владелец
        $rooms = Room::where('user_id', $user->id)->pluck('id');

        foreach ($rooms as $roomId) {
            $listeners["echo-private:room.{$roomId},.message.sent"] = 'refreshRooms';
        }

        return $listeners;
    }

    public function refreshRooms()
    {
        // This will trigger a re-render
    }
}
