<?php

namespace App\Livewire\Cabinet\Admin;

use App\Helpers\FileUploadHelper;
use App\Livewire\Cabinet\Admin\Concerns\AdminScreen;
use App\Models\SupportChat;
use App\Models\SupportMessage;
use App\Models\User;
use App\Services\AdminPersonCardService;
use App\Services\MessengerService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Поддержка: все обращения пользователей, переписка от имени поддержки, «Карточка человека».
 * Макет: AdminSupport. Отправка и прочтение — MessengerService (сторона администратора).
 */
#[Layout('components.layouts.cabinet', ['title' => 'Поддержка', 'active' => 'support', 'bare' => true])]
class Support extends Component
{
    use AdminScreen;
    use WithFileUploads;

    private const MAX_FILES = 10;

    private const PAGE = 30;

    /** Открытый диалог (id чата поддержки). */
    #[Url(except: null)]
    public ?int $chat = null;

    #[Url(except: 'all')]
    public string $filter = 'all';

    #[Url(except: '')]
    public string $q = '';

    public string $draft = '';

    public int $limit = self::PAGE;

    public bool $cardOpen = false;

    /** Только что выбранные файлы (wire:model). */
    public array $picked = [];

    /** Загруженные вложения: path, name, type, size. */
    #[Locked]
    public array $files = [];

    #[Locked]
    public ?int $editingId = null;

    #[Locked]
    public ?int $deletingId = null;

    /** Диалоги, где было непрочитанное при открытии: остаются в «Непрочитанных», пока открыты. */
    #[Locked]
    public ?int $keepInUnread = null;

    public function mount(): void
    {
        $this->authorizeAdmin();
        if (! in_array($this->filter, ['all', 'unread'], true)) {
            $this->filter = 'all';
        }
        if ($this->chat && ! $this->openChat()) {
            $this->chat = null;
        }
        $this->markOpenRead();
    }

    private function service(): MessengerService
    {
        return app(MessengerService::class);
    }

    private function admin(): User
    {
        return auth()->user();
    }

    /** Открытый чат поддержки (чужой — владелец не администратор). */
    private function openChat(): ?SupportChat
    {
        if (! $this->chat) {
            return null;
        }

        return SupportChat::whereKey($this->chat)
            ->whereHas('user', fn ($q) => $q->where('role', '!=', User::ROLE_ADMIN))
            ->with('user')
            ->first();
    }

    private function markOpenRead(): void
    {
        if ($chat = $this->openChat()) {
            $this->service()->markRead($this->admin(), $chat);
        }
    }

    public function open(int $id): void
    {
        $this->resetComposer();
        $this->limit = self::PAGE;
        $this->cardOpen = false;
        $this->chat = $id;
        abort_unless($chat = $this->openChat(), 404);

        $this->keepInUnread = $chat->messages()->where('user_id', $chat->user_id)->whereNull('read_at')->exists() ? $chat->id : null;
        $this->markOpenRead();
        $this->dispatch('chat-opened');
    }

    public function close(): void
    {
        $this->resetComposer();
        $this->chat = null;
        $this->cardOpen = false;
    }

    public function more(): void
    {
        $this->limit += self::PAGE;
    }

    public function openCard(): void
    {
        $this->cardOpen = (bool) $this->openChat();
    }

    public function closeCard(): void
    {
        $this->cardOpen = false;
    }

    /** Новое сообщение или прочтение в одном из чатов поддержки (Reverb). */
    public function incoming(): void
    {
        $this->markOpenRead();
        $this->dispatch('chat-updated');
    }

    public function getListeners(): array
    {
        $listeners = [
            'echo-private:App.Models.User.' . $this->admin()->id . ',.Illuminate\\Notifications\\Events\\BroadcastNotificationCreated' => '$refresh',
        ];
        foreach (SupportChat::whereHas('messages')->pluck('id') as $id) {
            $listeners["echo-private:support-chat.{$id},.support.message.sent"] = 'incoming';
        }
        if ($this->chat) {
            $listeners["echo-private:support-chat.{$this->chat},.support.messages.read"] = '$refresh';
        }

        return $listeners;
    }

    public function updatedPicked(): void
    {
        $this->validate([
            'picked' => ['array', 'max:' . (self::MAX_FILES - count($this->files))],
            'picked.*' => ['file', 'max:51200'],
        ], [
            'picked.max' => 'Можно прикрепить не больше ' . self::MAX_FILES . ' файлов.',
            'picked.*.max' => 'Файл больше 50 МБ — уменьшите его или разделите на части.',
        ]);

        foreach (FileUploadHelper::processChatAttachments($this->picked, [], 'support-attachments') as $file) {
            unset($file['processed']);
            $this->files[] = $file;
        }
        $this->picked = [];
    }

    public function removeFile(int $index): void
    {
        if (isset($this->files[$index])) {
            FileUploadHelper::deleteChatAttachment($this->files[$index]);
            unset($this->files[$index]);
            $this->files = array_values($this->files);
        }
    }

    public function send(): void
    {
        abort_unless($chat = $this->openChat(), 404);

        $this->validate(['draft' => ['nullable', 'string', 'max:5000']], ['draft.max' => 'Сообщение слишком длинное — разделите его на несколько.']);

        if ($this->editingId) {
            $this->service()->update($this->admin(), $this->message($this->editingId), $this->draft);
            $this->resetComposer();

            return;
        }

        if (trim($this->draft) === '' && $this->files === []) {
            return;
        }

        $this->service()->send($this->admin(), $chat, $this->draft, $this->files);
        $this->files = [];
        $this->draft = '';
        $this->dispatch('chat-updated');
    }

    public function edit(int $id): void
    {
        $message = $this->message($id);
        abort_unless($message->user_id === $this->admin()->id, 403);

        $this->editingId = $message->id;
        $this->draft = (string) $message->content;
        $this->dispatch('chat-edit');
    }

    public function cancelEdit(): void
    {
        $this->resetComposer();
    }

    /** Удалить можно только свои ответы. */
    public function confirmDelete(int $id): void
    {
        $message = $this->message($id);
        abort_unless($message->user_id === $this->admin()->id, 403);
        $this->deletingId = $message->id;
    }

    public function cancelDelete(): void
    {
        $this->deletingId = null;
    }

    public function delete(): void
    {
        if ($this->deletingId) {
            $message = $this->message($this->deletingId);
            abort_unless($message->user_id === $this->admin()->id, 403);
            $this->service()->delete($this->admin(), $message);
            if ($this->editingId === $this->deletingId) {
                $this->resetComposer();
            }
        }
        $this->deletingId = null;
    }

    private function message(int $id): SupportMessage
    {
        abort_unless($chat = $this->openChat(), 404);
        $message = SupportMessage::where('support_chat_id', $chat->id)->find($id);
        abort_unless($message, 404);

        return $message;
    }

    private function resetComposer(): void
    {
        foreach ($this->files as $file) {
            FileUploadHelper::deleteChatAttachment($file);
        }
        $this->files = [];
        $this->picked = [];
        $this->draft = '';
        $this->editingId = null;
        $this->deletingId = null;
        $this->resetValidation();
    }

    public function render()
    {
        $service = $this->service();
        $all = $service->supportDialogs($this->q);
        $dialogs = $this->filter === 'unread'
            ? $all->filter(fn (array $d) => $d['unread'] > 0 || $d['id'] === $this->chat && $this->keepInUnread === $d['id'])->values()
            : $all;

        $chat = $this->openChat();
        $people = app(AdminPersonCardService::class);
        $unreadDialogs = $service->supportUnreadDialogs();

        return view('livewire.cabinet.admin.support', [
            'dialogs' => $dialogs,
            'filters' => ['unread' => 'Непрочитанные' . ($unreadDialogs ? ' · ' . $unreadDialogs : ''), 'all' => 'Все'],
            'current' => $chat ? [
                'id' => $chat->id,
                'user' => $chat->user,
                'name' => $chat->user->name,
                'sub' => $people->subline($chat->user),
            ] : null,
            'thread' => $chat ? $service->thread($this->admin(), $chat, $this->limit) : null,
            'card' => $chat && $this->cardOpen ? $people->card($chat->user) : null,
            'nothingFound' => $dialogs->isEmpty() && trim($this->q) !== '',
            'allRead' => $dialogs->isEmpty() && trim($this->q) === '' && $this->filter === 'unread',
        ]);
    }
}
