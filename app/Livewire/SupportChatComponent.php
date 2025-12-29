<?php

namespace App\Livewire;

use App\Models\SupportChat;
use App\Models\SupportMessage;
use App\Models\User;
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
    public string $newMessage = '';
    public $messages = [];
    public bool $isAdmin = false;
    public bool $showUserCard = false;
    public $attachments = [];
    public $processedAttachments = []; // Обработанные вложения с путями в S3

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
                        $filename = 'support-attachments/' . uniqid() . '_' . time() . '.' . $extension;

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
            $path = $file->storePublicly('support-attachments', 's3');
            $this->processedAttachments[$index] = [
                'path' => $path,
                'name' => $originalName,
                'type' => $mimeType,
                'size' => $file->getSize(),
                'processed' => false,
            ];
        }
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
                'attachments' => $msg->attachments ?? [],
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
        if (!$this->supportChat || (trim($this->newMessage) === '' && empty($this->attachments))) {
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
            'content' => trim($this->newMessage),
            'attachments' => !empty($attachmentsData) ? $attachmentsData : null,
        ]);

        $message->load('user');

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
        ];

        $this->newMessage = '';
        $this->attachments = [];
        $this->processedAttachments = [];

        $this->dispatch('message-sent');
    }

    public function removeAttachment($index)
    {
        unset($this->attachments[$index]);
        unset($this->processedAttachments[$index]);
        $this->attachments = array_values($this->attachments);
        $this->processedAttachments = array_values($this->processedAttachments);
    }

    public function render()
    {
        return view('livewire.support-chat-component');
    }
}
