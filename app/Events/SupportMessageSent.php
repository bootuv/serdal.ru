<?php

namespace App\Events;

use App\Models\SupportMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SupportMessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public SupportMessage $message
    ) {
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('support-chat.' . $this->message->support_chat_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'support.message.sent';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->message->id,
            'support_chat_id' => $this->message->support_chat_id,
            'user_id' => $this->message->user_id,
            'user_name' => $this->message->user->name,
            'user_avatar' => $this->message->user->avatar_url,
            'content' => $this->message->content,
            'attachments' => $this->message->attachments ?? [],
            'created_at' => $this->message->created_at->toISOString(),
            'user_color' => $this->message->user->avatar_text_color,
        ];
    }
}
