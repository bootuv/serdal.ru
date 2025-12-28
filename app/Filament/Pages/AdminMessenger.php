<?php

namespace App\Filament\Pages;

use App\Models\SupportChat;
use App\Models\SupportMessage;
use App\Models\User;
use Filament\Pages\Page;

class AdminMessenger extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-lifebuoy';

    protected static ?string $navigationLabel = 'Чаты поддержки';

    protected static ?string $title = 'Чаты поддержки';

    protected static string $view = 'filament.pages.admin-messenger';

    protected static ?int $navigationSort = 2;

    public function getHeading(): string
    {
        return '';
    }

    public static function canAccess(): bool
    {
        return auth()->user()->role === User::ROLE_ADMIN;
    }

    public static function getNavigationBadge(): ?string
    {
        // Count all unread messages from non-admin users
        $unreadCount = SupportMessage::whereHas('supportChat', function ($query) {
            $query->whereHas('user', function ($q) {
                $q->where('role', '!=', User::ROLE_ADMIN);
            });
        })
            ->whereHas('user', function ($query) {
                $query->where('role', '!=', User::ROLE_ADMIN);
            })
            ->whereNull('read_at')
            ->count();

        return $unreadCount > 0 ? (string) $unreadCount : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public ?int $selectedChatId = null;

    public function mount(): void
    {
        $this->selectedChatId = request()->query('chat');
    }

    public function getViewData(): array
    {
        // Получаем все чаты поддержки с хотя бы одним сообщением
        $chats = SupportChat::whereHas('messages')
            ->with([
                'user',
                'messages' => function ($query) {
                    $query->latest()->limit(1);
                }
            ])
            ->withCount([
                'messages as unread_messages_count' => function ($query) {
                    $query->whereHas('user', function ($q) {
                        $q->where('role', '!=', User::ROLE_ADMIN);
                    })->whereNull('read_at');
                }
            ])
            ->orderByDesc(function ($query) {
                $query->select('created_at')
                    ->from('support_messages')
                    ->whereColumn('support_chat_id', 'support_chats.id')
                    ->latest()
                    ->limit(1);
            })
            ->get();

        $selectedChat = $this->selectedChatId
            ? $chats->firstWhere('id', $this->selectedChatId)
            : null;

        return [
            'chats' => $chats,
            'selectedChat' => $selectedChat,
        ];
    }

    public function selectChat(int $chatId): void
    {
        $this->selectedChatId = $chatId;
    }

    public function refreshChats()
    {
        // This will trigger a re-render
    }
}
