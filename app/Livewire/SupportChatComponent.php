<?php

namespace App\Livewire;

use App\Models\SupportChat;
use App\Models\SupportMessage;
use App\Models\User;
use App\Events\SupportMessageSent;
use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;

class SupportChatComponent extends Component
{
    use WithFileUploads;

    private const MAX_IMAGE_WIDTH = 1920;
    private const MAX_IMAGE_HEIGHT = 1080;

    public ?SupportChat $supportChat = null;
    public ?string $newMessage = '';
    public $messages = [];
    public bool $isAdmin = false;
    public bool $showUserCard = false;
    public $attachments = [];
    public $processedAttachments = []; // Обработанные вложения с путями в S3
    public ?int $editingMessageId = null;
    public ?string $editingMessageOriginalContent = null;
    public int $perPage = 20;


    /**
     * Хук вызывается сразу после загрузки файла
     * Здесь мы обрабатываем изображения пока временный файл ещё доступен
     */
    public function updatedAttachments()
    {
        $this->processedAttachments = \App\Helpers\FileUploadHelper::processChatAttachments(
            $this->attachments,
            $this->processedAttachments,
            'support-attachments',
            self::MAX_IMAGE_WIDTH,
            self::MAX_IMAGE_HEIGHT
        );
    }

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

        $readAt = now();
        $affected = 0;

        // Админы помечают как прочитанные сообщения пользователя
        // Пользователи помечают как прочитанные сообщения админов
        if ($this->isAdmin) {
            // Админ читает сообщения владельца чата
            $affected = $this->supportChat->messages()
                ->where('user_id', $this->supportChat->user_id)
                ->whereNull('read_at')
                ->update(['read_at' => $readAt]);
        } else {
            // Пользователь читает сообщения от админов
            $affected = $this->supportChat->messages()
                ->where('user_id', '!=', $this->supportChat->user_id)
                ->whereNull('read_at')
                ->update(['read_at' => $readAt]);
        }

        // Broadcast only if messages were actually marked as read
        if ($affected > 0) {
            // Get IDs for broadcast
            $query = $this->supportChat->messages()->where('read_at', $readAt);
            if ($this->isAdmin) {
                $query->where('user_id', $this->supportChat->user_id);
            } else {
                $query->where('user_id', '!=', $this->supportChat->user_id);
            }
            $messageIds = $query->pluck('id')->toArray();

            broadcast(new \App\Events\MessagesRead(
                $this->supportChat->id,
                $messageIds,
                $readAt->toISOString()
            ))->toOthers();
        }
    }

    public bool $hasMorePages = false;

    public function loadMessages(): void
    {
        if (!$this->supportChat) {
            $this->messages = [];
            return;
        }

        $chatOwnerId = $this->supportChat->user_id;
        $query = $this->supportChat->messages()->with('user');

        // Optimize: Fetch one extra item to check if there are more pages
        // instead of running count(*) query
        $rawMessages = $query
            ->orderBy('created_at', 'desc')
            ->take($this->perPage + 1)
            ->get();

        $this->hasMorePages = $rawMessages->count() > $this->perPage;

        $this->messages = $rawMessages
            ->take($this->perPage)
            ->sortBy('created_at')
            ->map(fn($msg) => [
                'id' => $msg->id,
                'user_id' => $msg->user_id,
                'user_name' => $msg->user->name,
                'user_avatar' => $msg->user->avatar_url,
                'content' => $msg->content,
                'attachments' => $msg->attachments ?? [],
                'created_at' => $msg->created_at->format('H:i'),
                'is_own' => $msg->user_id === auth()->id(),
                'is_admin' => $msg->user_id !== $chatOwnerId,
                'read_at' => $msg->read_at,
                'user_color' => $msg->user->avatar_text_color,
                'can_delete' => $msg->user_id === auth()->id() || auth()->user()->role === User::ROLE_ADMIN,
                'can_edit' => $msg->user_id === auth()->id(),
            ])
            ->values()
            ->toArray();
    }

    public function loadMore()
    {
        if ($this->hasMorePages) {
            $this->perPage += 20;
            $this->loadMessages();
        }
    }

    public function sendMessage($content = null): void
    {
        // Use provided content or fall back to newMessage property
        $messageContent = $content !== null ? $content : $this->newMessage;

        if (!$this->supportChat || (trim($messageContent ?? '') === '' && empty($this->attachments))) {
            return;
        }

        $user = auth()->user();

        // Проверяем доступ: либо владелец чата, либо админ
        $hasAccess = $this->supportChat->user_id === $user->id
            || $user->role === User::ROLE_ADMIN;

        if (!$hasAccess) {
            return;
        }

        // Используем уже обработанные вложения из updatedAttachments
        $attachmentsData = [];
        if (!empty($this->processedAttachments)) {
            foreach ($this->processedAttachments as $attachment) {
                $attachmentsData[] = [
                    'path' => $attachment['path'],
                    'name' => $attachment['name'],
                    'type' => $attachment['type'],
                    'size' => $attachment['size'],
                ];
            }
        }

        $message = SupportMessage::create([
            'support_chat_id' => $this->supportChat->id,
            'user_id' => $user->id,
            'content' => trim($messageContent ?? ''),
            'attachments' => !empty($attachmentsData) ? $attachmentsData : null,
        ]);

        $message->load('user');

        // Broadcast для real-time обновления
        broadcast(new SupportMessageSent($message))->toOthers();

        // Уведомления с задержкой 30 секунд (если сообщение не будет прочитано)
        if ($this->isAdmin) {
            // Админ отправил сообщение - уведомляем владельца чата
            \App\Jobs\SendUnreadSupportMessageNotification::dispatch($message, $this->supportChat->user)
                ->delay(now()->addSeconds(30));
        } else {
            // Пользователь отправил сообщение - уведомляем всех админов
            $admins = User::where('role', User::ROLE_ADMIN)->get();
            foreach ($admins as $admin) {
                \App\Jobs\SendUnreadSupportMessageNotification::dispatch($message, $admin)
                    ->delay(now()->addSeconds(30));
            }
        }

        // Добавляем сообщение в локальный список
        $this->messages[] = [
            'id' => $message->id,
            'user_id' => $message->user_id,
            'user_name' => $message->user->name,
            'user_avatar' => $message->user->avatar_url,
            'content' => $message->content,
            'attachments' => $attachmentsData,
            'created_at' => $message->created_at->format('H:i'),
            'is_own' => true,
            'is_admin' => $user->role === User::ROLE_ADMIN,
            'read_at' => null,
            'user_color' => $user->avatar_text_color,
            'can_delete' => true,
            'can_edit' => true,
        ];

        $this->newMessage = '';
        $this->attachments = [];
        $this->processedAttachments = [];


        $this->dispatch('message-sent');
    }

    public function editMessage(int $messageId)
    {
        $message = SupportMessage::find($messageId);

        if (!$message || $message->user_id !== auth()->id()) {
            return;
        }

        $this->editingMessageId = $message->id;
        $this->editingMessageOriginalContent = $message->content;
        $this->newMessage = $message->content;

        $this->dispatch('focus-input');
    }

    public function cancelEdit()
    {
        $this->editingMessageId = null;
        $this->editingMessageOriginalContent = null;
        $this->newMessage = '';
    }

    public function updateMessage()
    {
        if (!$this->editingMessageId || trim($this->newMessage) === '') {
            return;
        }

        $message = SupportMessage::find($this->editingMessageId);

        if (!$message || $message->user_id !== auth()->id()) {
            $this->cancelEdit();
            return;
        }

        $message->update([
            'content' => trim($this->newMessage)
        ]);

        foreach ($this->messages as $key => $msg) {
            if ($msg['id'] === $message->id) {
                $this->messages[$key]['content'] = $message->content;
                break;
            }
        }

        $this->cancelEdit();
    }

    public function deleteMessage(int $messageId)
    {
        $message = SupportMessage::find($messageId);

        if (!$message) {
            return;
        }

        $user = auth()->user();
        $isOwn = $message->user_id === $user->id;
        $isAdmin = $user->role === User::ROLE_ADMIN;

        if (!$isOwn && !$isAdmin) {
            return;
        }

        if (!empty($message->attachments)) {
            foreach ($message->attachments as $attachment) {
                if (isset($attachment['path'])) {
                    if (Storage::disk('s3')->exists($attachment['path'])) {
                        Storage::disk('s3')->delete($attachment['path']);
                    }
                }
            }
        }

        $message->delete();

        $this->messages = array_values(array_filter($this->messages, fn($msg) => $msg['id'] !== $messageId));
    }

    public function removeAttachment($index)
    {
        if (isset($this->processedAttachments[$index])) {
            \App\Helpers\FileUploadHelper::deleteChatAttachment($this->processedAttachments[$index]);
        }

        unset($this->attachments[$index]);
        unset($this->processedAttachments[$index]);
        $this->attachments = array_values($this->attachments);
        $this->processedAttachments = array_values($this->processedAttachments);
    }

    public function render()
    {
        return view('livewire.support-chat-component');
    }

    public function getListeners()
    {
        if (!$this->supportChat) {
            return [];
        }

        return [
            "echo-private:support-chat.{$this->supportChat->id},.support.message.sent" => 'onMessageReceived',
            "echo-private:room.{$this->supportChat->id},.messages.read" => 'onMessagesRead',
        ];
    }

    public function onMessagesRead($event)
    {
        $messageIds = $event['message_ids'] ?? [];
        $readAt = $event['read_at'] ?? null;

        if (empty($messageIds) || !$readAt) {
            return;
        }

        // Update read_at for messages in local state
        foreach ($this->messages as &$message) {
            if (in_array($message['id'], $messageIds)) {
                $message['read_at'] = $readAt;
            }
        }
    }

    public function onMessageReceived($event)
    {
        // Не добавляем свои сообщения повторно
        if ($event['user_id'] === auth()->id()) {
            return;
        }

        // Сразу помечаем как прочитанное, так как окно открыто
        $this->markAsRead();

        $chatOwnerId = $this->supportChat->user_id;

        $this->messages[] = [
            'id' => $event['id'],
            'user_id' => $event['user_id'],
            'user_name' => $event['user_name'],
            'user_avatar' => $event['user_avatar'],
            'content' => $event['content'],
            'attachments' => $event['attachments'] ?? [],
            'created_at' => \Carbon\Carbon::parse($event['created_at'])->format('H:i'),
            'is_own' => false,
            'is_admin' => $event['user_id'] !== $chatOwnerId,
            'read_at' => now(),
            'user_color' => $event['user_color'] ?? '#000000',
            'can_delete' => auth()->user()->role === User::ROLE_ADMIN,
            'can_edit' => false,
        ];

        $this->dispatch('message-received');

    }
}
