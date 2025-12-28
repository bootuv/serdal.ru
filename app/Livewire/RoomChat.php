<?php

namespace App\Livewire;

use App\Events\MessageSent;
use App\Models\Message;
use App\Models\Room;
use App\Notifications\NewMessage;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Storage;

class RoomChat extends Component
{
    use WithFileUploads;

    public ?Room $room = null;
    public string $newMessage = '';
    public $messages = [];
    public $attachments = [];

    public function mount(?Room $room = null)
    {
        $this->room = $room;
        if ($room) {
            $this->markAsRead();
            $this->loadMessages();
        }
    }

    public function markAsRead()
    {
        if (!$this->room) {
            return;
        }

        $this->room->messages()
            ->where('user_id', '!=', auth()->id())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    public function loadMessages()
    {
        if (!$this->room) {
            $this->messages = [];
            return;
        }

        // Also mark as read on polling
        $this->markAsRead();

        $this->messages = $this->room->messages()
            ->with('user')
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(fn($msg) => [
                'id' => $msg->id,
                'user_id' => $msg->user_id,
                'user_name' => $msg->user->name,
                'user_avatar' => $msg->user->avatar_url,
                'content' => $msg->content,
                'attachments' => $msg->attachments ?? [],
                'created_at' => $msg->created_at->format('H:i'),
                'is_own' => $msg->user_id === auth()->id(),
                'read_at' => $msg->read_at,
                'user_color' => $msg->user->avatar_text_color,
            ])
            ->toArray();
    }

    public function selectRoom(int $roomId)
    {
        $user = auth()->user();

        // Проверяем доступ к комнате
        $room = Room::where('id', $roomId)
            ->where(function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->orWhereHas('participants', fn($q) => $q->where('user_id', $user->id));
            })
            ->first();

        if ($room) {
            $this->room = $room;
            $this->markAsRead();
            $this->loadMessages();
            $this->dispatch('room-selected', roomId: $roomId);
        }
    }

    public function sendMessage()
    {
        if (!$this->room || (trim($this->newMessage) === '' && empty($this->attachments))) {
            return;
        }

        $user = auth()->user();

        // Проверяем доступ
        $hasAccess = $this->room->user_id === $user->id
            || $this->room->participants()->where('user_id', $user->id)->exists();

        if (!$hasAccess) {
            return;
        }

        // Обработка вложений
        $attachmentsData = [];
        if (!empty($this->attachments)) {
            foreach ($this->attachments as $file) {
                $path = $file->storePublicly('chat-attachments', 's3');
                $attachmentsData[] = [
                    'path' => $path,
                    'name' => $file->getClientOriginalName(),
                    'type' => $file->getMimeType(),
                    'size' => $file->getSize(),
                ];
            }
        }

        $message = Message::create([
            'room_id' => $this->room->id,
            'user_id' => $user->id,
            'content' => trim($this->newMessage),
            'attachments' => !empty($attachmentsData) ? $attachmentsData : null,
        ]);

        $message->load('user');

        // Broadcast для real-time обновления
        broadcast(new MessageSent($message))->toOthers();

        // Уведомления для всех участников кроме отправителя
        $recipients = collect();

        // Добавляем владельца, если он не отправитель
        if ($this->room->user_id !== $user->id) {
            $recipients->push($this->room->user);
        }

        // Добавляем участников, кроме отправителя
        foreach ($this->room->participants as $participant) {
            if ($participant->id !== $user->id) {
                $recipients->push($participant);
            }
        }

        // Отправляем уведомления с задержкой и проверкой
        foreach ($recipients as $recipient) {
            \App\Jobs\SendUnreadMessageNotification::dispatch($message, $recipient)
                ->delay(now()->addSeconds(30));
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
            'read_at' => null,
            'user_color' => $user->avatar_text_color,
        ];

        $this->newMessage = '';
        $this->attachments = [];

        $this->dispatch('message-sent');
    }

    #[On('echo-private:room.{room.id},.message.sent')]
    public function onMessageReceived($event)
    {
        // Не добавляем свои сообщения повторно
        if ($event['user_id'] === auth()->id()) {
            return;
        }

        // Сразу помечаем как прочитанное, так как окно открыто
        $this->markAsRead();

        $this->messages[] = [
            'id' => $event['id'],
            'user_id' => $event['user_id'],
            'user_name' => $event['user_name'],
            'user_avatar' => $event['user_avatar'],
            'content' => $event['content'],
            'created_at' => \Carbon\Carbon::parse($event['created_at'])->format('H:i'),
            'is_own' => false,
            'read_at' => now(), // Marked as read because we just read it by being here
            'user_color' => $event['user_color'] ?? '#000000',
        ];

        $this->dispatch('message-received');
    }

    public function getListeners()
    {
        if (!$this->room) {
            return [];
        }

        return [
            "echo-private:room.{$this->room->id},.message.sent" => 'onMessageReceived',
        ];
    }

    public function removeAttachment($index)
    {
        unset($this->attachments[$index]);
        $this->attachments = array_values($this->attachments);
    }

    public function render()
    {
        return view('livewire.room-chat');
    }
}
