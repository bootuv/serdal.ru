<?php

namespace App\Filament\App\Pages;

use App\Models\Message;
use App\Models\Room;
use App\Models\SupportChat;
use App\Models\SupportMessage;
use Filament\Pages\Page;

class Messenger extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationLabel = 'Сообщения';

    protected static ?string $title = 'Сообщения';

    protected static string $view = 'filament.app.pages.messenger';

    protected static ?int $navigationSort = 4;

    public function getHeading(): string
    {
        return '';
    }

    public static function getNavigationBadge(): ?string
    {
        $userId = auth()->id();

        // Get all rooms where user is owner
        $roomIds = Room::where('user_id', $userId)->pluck('id');

        // Count unread messages from rooms
        $unreadCount = Message::whereIn('room_id', $roomIds)
            ->where('user_id', '!=', $userId)
            ->whereNull('read_at')
            ->count();

        // Count unread support messages (from admins)
        $supportChat = SupportChat::where('user_id', $userId)->first();
        if ($supportChat) {
            $unreadCount += SupportMessage::where('support_chat_id', $supportChat->id)
                ->where('user_id', '!=', $userId)
                ->whereNull('read_at')
                ->count();
        }

        return $unreadCount > 0 ? (string) $unreadCount : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public ?int $selectedRoomId = null;
    public bool $supportChatSelected = false;

    public function mount(): void
    {
        // Можно передать room_id через query параметр
        $this->selectedRoomId = request()->query('room');
        $this->supportChatSelected = request()->query('support') === '1';
    }

    public function getViewData(): array
    {
        $user = auth()->user();

        // Получаем занятия, где пользователь является владельцем
        $rooms = Room::withTrashed()
            ->where('user_id', $user->id)
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
            ->get();

        // Получаем или создаём support chat для пользователя
        $supportChat = SupportChat::getOrCreateForUser($user);

        // Подсчёт непрочитанных сообщений от поддержки
        $supportUnreadCount = SupportMessage::where('support_chat_id', $supportChat->id)
            ->where('user_id', '!=', $user->id)
            ->whereNull('read_at')
            ->count();

        // Последнее сообщение в support chat
        $lastSupportMessage = $supportChat->messages()->latest()->first();

        // Создаём единый список чатов для сортировки
        $chatItems = collect();

        // Добавляем комнаты
        foreach ($rooms as $room) {
            $lastMessage = $room->messages->first();
            $chatItems->push([
                'type' => 'room',
                'id' => $room->id,
                'room' => $room,
                'last_message_at' => $lastMessage ? $lastMessage->created_at : $room->created_at,
                'last_message_content' => $lastMessage ? $lastMessage->content : null,
                'unread_count' => $room->unread_messages_count,
                'is_archived' => $room->trashed(),
            ]);
        }

        // Добавляем чат поддержки
        $chatItems->push([
            'type' => 'support',
            'id' => $supportChat->id,
            'supportChat' => $supportChat,
            'last_message_at' => $lastSupportMessage ? $lastSupportMessage->created_at : $supportChat->created_at,
            'last_message_content' => $lastSupportMessage ? $lastSupportMessage->content : null,
            'unread_count' => $supportUnreadCount,
        ]);

        // Сортируем по времени последнего сообщения (новые сверху)
        $chatItems = $chatItems->sortByDesc('last_message_at')->values();

        $selectedRoom = $this->selectedRoomId && !$this->supportChatSelected
            ? $rooms->firstWhere('id', $this->selectedRoomId)
            : null;

        return [
            'chatItems' => $chatItems,
            'rooms' => $rooms,
            'selectedRoom' => $selectedRoom,
            'supportChat' => $supportChat,
            'supportChatSelected' => $this->supportChatSelected,
            'supportUnreadCount' => $supportUnreadCount,
            'lastSupportMessage' => $lastSupportMessage,
        ];
    }

    public function selectRoom(int $roomId): void
    {
        $this->selectedRoomId = $roomId;
        $this->supportChatSelected = false;
    }

    public function selectSupportChat(): void
    {
        $this->selectedRoomId = null;
        $this->supportChatSelected = true;
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

        // Подписываемся на чат поддержки пользователя
        $supportChat = SupportChat::where('user_id', $user->id)->first();
        if ($supportChat) {
            $listeners["echo-private:support-chat.{$supportChat->id},.support.message.sent"] = 'refreshRooms';
        }

        return $listeners;
    }

    public function refreshRooms()
    {
        // This will trigger a re-render
    }
}

