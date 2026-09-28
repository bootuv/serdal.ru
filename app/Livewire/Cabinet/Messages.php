<?php

namespace App\Livewire\Cabinet;

use App\Helpers\FileUploadHelper;
use App\Livewire\Cabinet\Teacher\Concerns\TeacherScreen;
use App\Models\Message;
use App\Models\PersonalChat;
use App\Models\Room;
use App\Models\SupportChat;
use App\Models\SupportMessage;
use App\Models\User;
use App\Services\MessengerService;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * «Сообщения» учителя и ученика: личные чаты, чаты занятий и поддержка. Макет: «Учитель · Сообщения» (docs/design/BRAND.md).
 * Один экран на обе роли — маршруты cabinet.teacher.messages и cabinet.student.messages.
 */
#[Layout('components.layouts.cabinet', ['title' => 'Сообщения', 'active' => 'messages', 'bare' => true])]
class Messages extends Component
{
    use TeacherScreen;
    use WithFileUploads;

    private const MAX_FILES = 10;

    private const PAGE = 30;

    #[Url(except: null)]
    public ?int $room = null;

    /** Личный чат учителя и ученика. */
    #[Url(except: null)]
    public ?int $personal = null;

    /** Собеседник из ссылки «Написать…»: открываем личный чат с ним (создаём при первом открытии). */
    #[Url(except: null)]
    public ?int $with = null;

    #[Url(except: false)]
    public bool $support = false;

    public string $q = '';

    public string $draft = '';

    public int $limit = self::PAGE;

    /** Только что выбранные файлы (wire:model). */
    public array $picked = [];

    /** Загруженные вложения: path, name, type, size. */
    #[Locked]
    public array $files = [];

    #[Locked]
    public ?int $editingId = null;

    #[Locked]
    public ?int $deletingId = null;

    public function mount(): void
    {
        $user = auth()->user();
        if (MessengerService::isTeacher($user)) {
            $this->authorizeTeacher();
        } else {
            abort_unless($user?->role === User::ROLE_STUDENT, 403);
        }

        if ($this->room && ! $this->service()->room($user, $this->room)) {
            $this->room = null;
        }
        if ($this->with) {
            $this->personal = $this->service()->personalChatWith($user, $this->with)?->id;
            $this->with = null;
            $this->room = null;
            $this->support = false;
        }
        if ($this->personal && ! $this->service()->personalChat($user, $this->personal)) {
            $this->personal = null;
        }
        $this->markOpenRead();
    }

    private function service(): MessengerService
    {
        return app(MessengerService::class);
    }

    private function user(): User
    {
        return auth()->user();
    }

    /** Открытый чат: личный, занятия или поддержка. */
    private function chat(): Room|PersonalChat|SupportChat|null
    {
        return match (true) {
            $this->support => $this->service()->supportChat($this->user()),
            $this->personal !== null => $this->service()->personalChat($this->user(), $this->personal),
            $this->room !== null => $this->service()->room($this->user(), $this->room),
            default => null,
        };
    }

    private function markOpenRead(): void
    {
        if ($chat = $this->chat()) {
            $this->service()->markRead($this->user(), $chat);
        }
    }

    public function open(string $key): void
    {
        $this->resetComposer();
        $this->limit = self::PAGE;

        $this->support = false;
        $this->room = null;
        $this->personal = null;

        if ($key === 'support') {
            $this->support = true;
        } elseif (str_starts_with($key, 'with-')) {
            $chat = $this->service()->personalChatWith($this->user(), (int) str_replace('with-', '', $key));
            abort_unless($chat, 404);
            $this->personal = $chat->id;
        } elseif (str_starts_with($key, 'personal-')) {
            $id = (int) str_replace('personal-', '', $key);
            abort_unless($this->service()->personalChat($this->user(), $id), 404);
            $this->personal = $id;
        } else {
            $id = (int) str_replace('room-', '', $key);
            abort_unless($this->service()->room($this->user(), $id), 404);
            $this->room = $id;
        }

        $this->markOpenRead();
        $this->dispatch('chat-opened');
    }

    public function close(): void
    {
        $this->resetComposer();
        $this->room = null;
        $this->personal = null;
        $this->support = false;
    }

    public function more(): void
    {
        $this->limit += self::PAGE;
    }

    /** Новое сообщение или прочтение в одном из чатов (Reverb). */
    public function incoming(): void
    {
        $this->markOpenRead();
        $this->dispatch('chat-updated');
    }

    public function getListeners(): array
    {
        $user = $this->user();
        $listeners = [
            'echo-private:App.Models.User.' . $user->id . ',.Illuminate\\Notifications\\Events\\BroadcastNotificationCreated' => '$refresh',
        ];

        foreach ($this->service()->rooms($user)->withoutTrashed()->pluck('rooms.id') as $id) {
            $listeners["echo-private:room.{$id},.message.sent"] = 'incoming';
        }
        foreach ($this->service()->personalChats($user)->pluck('id') as $id) {
            $listeners["echo-private:personal-chat.{$id},.message.sent"] = 'incoming';
        }
        if ($this->room) {
            $listeners["echo-private:room.{$this->room},.messages.read"] = '$refresh';
        }
        if ($this->personal) {
            $listeners["echo-private:personal-chat.{$this->personal},.messages.read"] = '$refresh';
        }
        if ($chat = SupportChat::where('user_id', $user->id)->first()) {
            $listeners["echo-private:support-chat.{$chat->id},.support.message.sent"] = 'incoming';
            // Поддержка прочитала — галочки ✓✓
            $listeners["echo-private:support-chat.{$chat->id},.support.messages.read"] = '$refresh';
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

        $dir = $this->support ? 'support-attachments' : 'chat-attachments';
        foreach (FileUploadHelper::processChatAttachments($this->picked, [], $dir) as $file) {
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
        $chat = $this->chat();
        abort_unless($chat, 404);
        abort_if($chat instanceof Room && $chat->trashed(), 403);
        abort_if($chat instanceof PersonalChat && ! $chat->isActive(), 403);

        $this->validate(['draft' => ['nullable', 'string', 'max:5000']], ['draft.max' => 'Сообщение слишком длинное — разделите его на несколько.']);

        if ($this->editingId) {
            $this->service()->update($this->user(), $this->ownedMessage($this->editingId), $this->draft);
            $this->resetComposer();

            return;
        }

        if (trim($this->draft) === '' && $this->files === []) {
            return;
        }

        $this->service()->send($this->user(), $chat, $this->draft, $this->files);
        $this->files = [];
        $this->draft = '';
        $this->dispatch('chat-updated');
    }

    public function edit(int $id): void
    {
        $message = $this->ownedMessage($id);
        abort_unless($message->user_id === $this->user()->id, 403);

        $this->editingId = $message->id;
        $this->draft = (string) $message->content;
        $this->dispatch('chat-edit');
    }

    public function cancelEdit(): void
    {
        $this->resetComposer();
    }

    public function confirmDelete(int $id): void
    {
        $message = $this->ownedMessage($id);
        abort_unless($this->service()->canDelete($this->user(), $message), 403);
        $this->deletingId = $message->id;
    }

    public function cancelDelete(): void
    {
        $this->deletingId = null;
    }

    public function delete(): void
    {
        if ($this->deletingId) {
            $this->service()->delete($this->user(), $this->ownedMessage($this->deletingId));
            if ($this->editingId === $this->deletingId) {
                $this->resetComposer();
            }
        }
        $this->deletingId = null;
    }

    /** Сообщение из открытого чата (иначе 404). */
    private function ownedMessage(int $id): Model
    {
        $chat = $this->chat();
        abort_unless($chat, 404);

        $message = match (true) {
            $chat instanceof SupportChat => SupportMessage::where('support_chat_id', $chat->id)->find($id),
            $chat instanceof PersonalChat => Message::where('personal_chat_id', $chat->id)->find($id),
            default => Message::where('room_id', $chat->id)->find($id),
        };
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
        $user = $this->user();
        $list = $this->service()->dialogs($user);
        $q = mb_strtolower(trim($this->q));
        $match = fn (array $d) => $q === '' || str_contains(mb_strtolower($d['name']), $q);

        $chat = $this->chat();
        $current = match (true) {
            $chat instanceof SupportChat => $list['support'],
            $chat instanceof PersonalChat => $list['dialogs']->firstWhere('personal_id', $chat->id),
            $chat instanceof Room => $list['dialogs']->firstWhere('room_id', $chat->id),
            default => null,
        };

        return view('livewire.cabinet.messages', [
            'supportDialog' => $match($list['support']) ? $list['support'] : null,
            'dialogs' => $list['dialogs']->filter($match)->values(),
            'current' => $current,
            'thread' => $chat ? $this->service()->thread($user, $chat, $this->limit) : null,
            'canWrite' => $current && ! $current['archived'],
        ]);
    }
}
