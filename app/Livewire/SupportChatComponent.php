<?php

namespace App\Livewire;

use App\Models\SupportChat;
use App\Models\SupportMessage;
use App\Models\User;
use Livewire\Component;

class SupportChatComponent extends Component
{
    public ?SupportChat $supportChat = null;
    public string $newMessage = '';
    public $messages = [];
    public bool $isAdmin = false;
    public bool $showUserCard = false;

    public function mount(?SupportChat $supportChat = null)
    {
        $this->supportChat = $supportChat;
        $this->isAdmin = auth()->user()->role === User::ROLE_ADMIN;

        if ($supportChat) {
            $this->markAsRead();
            $this->loadMessages();
        }
    }

    public function openUserCard(): void
    {
        $this->showUserCard = true;
    }

    public function closeUserCard(): void
    {
        $this->showUserCard = false;
    }

    public function markAsRead(): void
    {
        if (!$this->supportChat) {
            return;
        }

        $user = auth()->user();

        // Админы помечают как прочитанные сообщения пользователя
        // Пользователи помечают как прочитанные сообщения админов
        if ($this->isAdmin) {
            // Админ читает сообщения владельца чата
            $this->supportChat->messages()
                ->where('user_id', $this->supportChat->user_id)
                ->whereNull('read_at')
                ->update(['read_at' => now()]);
        } else {
            // Пользователь читает сообщения от админов
            $this->supportChat->messages()
                ->where('user_id', '!=', $this->supportChat->user_id)
                ->whereNull('read_at')
                ->update(['read_at' => now()]);
        }
    }

    public function loadMessages(): void
    {
        if (!$this->supportChat) {
            $this->messages = [];
            return;
        }

        $this->markAsRead();

        $chatOwnerId = $this->supportChat->user_id;

        $this->messages = $this->supportChat->messages()
            ->with('user')
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(fn($msg) => [
                'id' => $msg->id,
                'user_id' => $msg->user_id,
                'user_name' => $msg->user->name,
                'user_avatar' => $msg->user->avatar_url,
                'content' => $msg->content,
                'created_at' => $msg->created_at->format('H:i'),
                'is_own' => $msg->user_id === auth()->id(),
                'is_admin' => $msg->user_id !== $chatOwnerId,
                'read_at' => $msg->read_at,
                'user_color' => $msg->user->avatar_text_color,
            ])
            ->toArray();
    }

    public function sendMessage(): void
    {
        if (!$this->supportChat || trim($this->newMessage) === '') {
            return;
        }

        $user = auth()->user();

        // Проверяем доступ: либо владелец чата, либо админ
        $hasAccess = $this->supportChat->user_id === $user->id
            || $user->role === User::ROLE_ADMIN;

        if (!$hasAccess) {
            return;
        }

        $message = SupportMessage::create([
            'support_chat_id' => $this->supportChat->id,
            'user_id' => $user->id,
            'content' => trim($this->newMessage),
        ]);

        $message->load('user');

        // Добавляем сообщение в локальный список
        $this->messages[] = [
            'id' => $message->id,
            'user_id' => $message->user_id,
            'user_name' => $message->user->name,
            'user_avatar' => $message->user->avatar_url,
            'content' => $message->content,
            'created_at' => $message->created_at->format('H:i'),
            'is_own' => true,
            'is_admin' => $user->role === User::ROLE_ADMIN,
            'read_at' => null,
            'user_color' => $user->avatar_text_color,
        ];

        $this->newMessage = '';

        $this->dispatch('message-sent');
    }

    public function render()
    {
        return view('livewire.support-chat-component');
    }
}
