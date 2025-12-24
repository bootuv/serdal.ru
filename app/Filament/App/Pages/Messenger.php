<?php

namespace App\Filament\App\Pages;

use App\Models\Message;
use App\Models\Room;
use Filament\Pages\Page;

class Messenger extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationLabel = 'Сообщения';

    protected static ?string $title = 'Сообщения';

    protected static string $view = 'filament.app.pages.messenger';

    protected static ?int $navigationSort = 3;

    public static function getNavigationBadge(): ?string
    {
        $userId = auth()->id();

        // Get all rooms where user is owner
        $roomIds = Room::where('user_id', $userId)->pluck('id');

        // Count unread messages from others
        $unreadCount = Message::whereIn('room_id', $roomIds)
            ->where('user_id', '!=', $userId)
            ->whereNull('read_at')
            ->count();

        return $unreadCount > 0 ? (string) $unreadCount : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

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
            ->withCount([
                'messages as unread_messages_count' => function ($query) use ($user) {
                    $query->where('user_id', '!=', $user->id)->whereNull('read_at');
                }
            ])
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
