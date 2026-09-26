<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** Сообщения чата поддержки прочитаны (галочки ✓✓ у собеседника). */
class SupportMessagesRead implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public int $chatId, public array $messageIds, public string $readAt)
    {
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('support-chat.' . $this->chatId)];
    }

    public function broadcastAs(): string
    {
        return 'support.messages.read';
    }

    public function broadcastWith(): array
    {
        return ['message_ids' => $this->messageIds, 'read_at' => $this->readAt];
    }
}
