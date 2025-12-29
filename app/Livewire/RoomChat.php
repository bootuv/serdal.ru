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
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Components\TextInput;
use Intervention\Image\Laravel\Facades\Image;

class RoomChat extends Component implements HasActions, HasForms
{
    use WithFileUploads;
    use InteractsWithActions;
    use InteractsWithForms;

    private const MAX_IMAGE_WIDTH = 1920;
    private const MAX_IMAGE_HEIGHT = 1080;

    public ?Room $room = null;
    public string $newMessage = '';
    public $messages = [];
    public $attachments = [];
    public $processedAttachments = []; // Обработанные вложения с путями в S3
    public ?int $editingMessageId = null; // ID редактируемого сообщения
    public ?string $editingMessageOriginalContent = null;

    /**
     * Хук вызывается сразу после загрузки файла
     * Здесь мы обрабатываем изображения пока временный файл ещё доступен
     */
    public function updatedAttachments()
    {
        foreach ($this->attachments as $index => $file) {
            // Пропускаем уже обработанные файлы
            if (isset($this->processedAttachments[$index])) {
                continue;
            }

            $mimeType = $file->getMimeType();
            $originalName = $file->getClientOriginalName();

            // Проверяем, является ли файл изображением (не GIF)
            if (str_starts_with($mimeType, 'image/') && !str_contains($mimeType, 'gif')) {
                try {
                    // Читаем содержимое файла пока он ещё доступен
                    $imageContent = $file->get();

                    if ($imageContent) {
                        $image = Image::read($imageContent);

                        // Уменьшаем только если изображение больше HD
                        $image->scaleDown(self::MAX_IMAGE_WIDTH, self::MAX_IMAGE_HEIGHT);

                        // Генерируем имя файла
                        $extension = $file->getClientOriginalExtension() ?: 'jpg';
                        $filename = 'chat-attachments/' . uniqid() . '_' . time() . '.' . $extension;

                        // Сохраняем в S3
                        $encodedImage = $image->encodeByExtension($extension, quality: 85);
                        Storage::disk('s3')->put($filename, (string) $encodedImage, 'public');

                        // Сохраняем данные об обработанном файле
                        $this->processedAttachments[$index] = [
                            'path' => $filename,
                            'name' => $originalName,
                            'type' => $mimeType,
                            'size' => strlen((string) $encodedImage),
                            'processed' => true,
                        ];
                        continue;
                    }
                } catch (\Exception $e) {
                    \Log::error('Image resize failed during upload: ' . $e->getMessage());
                }
            }

            // Для не-изображений или при ошибке обработки - сохраняем как есть
            $path = $file->storePublicly('chat-attachments', 's3');
            $this->processedAttachments[$index] = [
                'path' => $path,
                'name' => $originalName,
                'type' => $mimeType,
                'size' => $file->getSize(),
                'processed' => false,
            ];
        }
    }

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
                'can_delete' => $msg->user_id === auth()->id() || in_array(auth()->user()->role, [\App\Models\User::ROLE_ADMIN, \App\Models\User::ROLE_TUTOR, \App\Models\User::ROLE_MENTOR]),
                'can_edit' => $msg->user_id === auth()->id(),
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
            'can_delete' => true,
            'can_edit' => true,
            'read_at' => null,
            'user_color' => $user->avatar_text_color,
        ];

        $this->newMessage = '';
        $this->attachments = [];
        $this->processedAttachments = [];


        $this->dispatch('message-sent');
    }



    public function editMessage(int $messageId)
    {
        $message = Message::find($messageId);

        if (!$message || $message->user_id !== auth()->id()) {
            return;
        }

        $this->editingMessageId = $message->id;
        $this->editingMessageOriginalContent = $message->content;
        $this->newMessage = $message->content;

        // Focus on input
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

        $message = Message::find($this->editingMessageId);

        if (!$message || $message->user_id !== auth()->id()) {
            $this->cancelEdit();
            return;
        }

        $message->update([
            'content' => trim($this->newMessage)
        ]);

        // Update local state
        foreach ($this->messages as $key => $msg) {
            if ($msg['id'] === $message->id) {
                $this->messages[$key]['content'] = $message->content;
                break;
            }
        }

        $this->cancelEdit();
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
            'can_delete' => in_array(auth()->user()->role, [\App\Models\User::ROLE_ADMIN, \App\Models\User::ROLE_TUTOR, \App\Models\User::ROLE_MENTOR]),
            'can_edit' => false,
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
        unset($this->processedAttachments[$index]);
        $this->attachments = array_values($this->attachments);
        $this->processedAttachments = array_values($this->processedAttachments);
    }

    public function deleteMessageAction(): Action
    {
        return Action::make('deleteMessage')
            ->requiresConfirmation()
            ->modalHeading('Удалить сообщение')
            ->modalDescription('Вы уверены, что хотите удалить это сообщение? Это действие нельзя отменить.')
            ->modalSubmitActionLabel('Удалить')
            ->color('danger')
            ->icon('heroicon-o-trash')
            ->action(function (array $arguments) {
                $messageId = $arguments['id'];
                $this->deleteMessage($messageId);
            });
    }


    public function deleteMessage(int $messageId)
    {
        $message = Message::find($messageId);

        if (!$message) {
            return;
        }

        $user = auth()->user();
        $isOwn = $message->user_id === $user->id;
        $isStaff = in_array($user->role, [\App\Models\User::ROLE_ADMIN, \App\Models\User::ROLE_TUTOR, \App\Models\User::ROLE_MENTOR]);

        if (!$isOwn && !$isStaff) {
            return;
        }

        // Delete attachments from S3
        if (!empty($message->attachments)) {
            foreach ($message->attachments as $attachment) {
                if (isset($attachment['path'])) {
                    if (Storage::disk('s3')->exists($attachment['path'])) {
                        Storage::disk('s3')->delete($attachment['path']);
                        \Log::info('Deleted chat attachment from S3: ' . $attachment['path']);
                    } else {
                        \Log::warning('Chat attachment not found on S3 during delete: ' . $attachment['path']);
                    }
                }
            }
        }

        $message->delete();

        // Update local state by removing the message
        $this->messages = array_filter($this->messages, fn($msg) => $msg['id'] !== $messageId);

        // Re-index array to avoid JS issues with associative arrays where lists are expected
        $this->messages = array_values($this->messages);
    }

    public function render()
    {
        return view('livewire.room-chat');
    }
}
