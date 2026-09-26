<?php

namespace App\Services;

use App\Events\MessageSent;
use App\Events\MessagesRead;
use App\Events\SupportMessageSent;
use App\Jobs\SendSupportMessageTelegramNotification;
use App\Jobs\SendUnreadMessageNotification;
use App\Jobs\SendUnreadSupportMessageNotification;
use App\Models\Message;
use App\Models\Room;
use App\Models\SupportChat;
use App\Models\SupportMessage;
use App\Models\User;
use App\Support\HumanDate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

/**
 * Сообщения: чаты занятий (учитель — владелец, ученики — участники) и чат с поддержкой.
 * Используется новым экраном «Сообщения» и старыми компонентами RoomChat / SupportChatComponent.
 */
class MessengerService
{
    public const TEACHER_ROLES = [User::ROLE_TUTOR, User::ROLE_ADMIN];

    /** Задержка уведомления о непрочитанном сообщении (если прочитали раньше — не шлём). */
    private const NOTIFY_DELAY_SECONDS = 30;

    public static function isTeacher(User $user): bool
    {
        return in_array($user->role, self::TEACHER_ROLES, true);
    }

    /** Занятия, чаты которых видит пользователь (включая архивные). */
    public function rooms(User $user): Builder
    {
        $query = Room::withTrashed();

        return self::isTeacher($user)
            ? $query->where('user_id', $user->id)
            : $query->whereHas('participants', fn ($q) => $q->where('users.id', $user->id));
    }

    public function room(User $user, int $roomId): ?Room
    {
        return $this->rooms($user)->whereKey($roomId)->first();
    }

    public function supportChat(User $user): SupportChat
    {
        return SupportChat::getOrCreateForUser($user);
    }

    /** Все непрочитанные: чаты занятий + поддержка. Для счётчика в меню. */
    public function unreadCount(User $user): int
    {
        $rooms = Message::whereIn('room_id', $this->rooms($user)->select('rooms.id'))
            ->where('user_id', '!=', $user->id)
            ->whereNull('read_at')
            ->count();

        $support = SupportMessage::whereHas('supportChat', fn ($q) => $q->where('user_id', $user->id))
            ->where('user_id', '!=', $user->id)
            ->whereNull('read_at')
            ->count();

        return $rooms + $support;
    }

    /**
     * Список диалогов, новые сверху. Поддержка — отдельно (закреплена).
     *
     * @return array{support: array, dialogs: Collection<int, array>}
     */
    public function dialogs(User $user): array
    {
        $rooms = $this->rooms($user)
            ->with(['participants:id,name,username', 'user:id,name'])
            ->withCount(['messages as unread_count' => fn ($q) => $q->where('user_id', '!=', $user->id)->whereNull('read_at')])
            ->get();

        $last = Message::whereIn('id', Message::selectRaw('max(id)')->whereIn('room_id', $rooms->pluck('id'))->groupBy('room_id'))
            ->get()
            ->keyBy('room_id');

        $dialogs = $rooms->map(function (Room $room) use ($user, $last) {
            $info = $this->describe($user, $room);
            $message = $last->get($room->id);

            return $info + [
                'key' => 'room-' . $room->id,
                'room_id' => $room->id,
                'unread' => (int) $room->unread_count,
                'preview' => $message ? $this->preview($user, $message, $info['type'] === 'group') : 'Сообщений пока нет',
                'time' => $message ? $this->shortTime($message->created_at) : '',
                'sort' => ($message?->created_at ?? $room->created_at)?->getTimestamp() ?? 0,
            ];
        })->sortByDesc('sort')->values();

        $chat = $this->supportChat($user);
        $lastSupport = $chat->messages()->latest('id')->first();

        $support = [
            'key' => 'support',
            'type' => 'support',
            'name' => 'Поддержка Serdal',
            'sub' => 'Вопросы о кабинете, оплате и занятиях',
            'link' => null,
            'archived' => false,
            'unread' => $chat->messages()->where('user_id', '!=', $user->id)->whereNull('read_at')->count(),
            'preview' => $lastSupport ? $this->preview($user, $lastSupport, false) : 'Напишите, если что-то не получается',
            'time' => $lastSupport ? $this->shortTime($lastSupport->created_at) : '',
            'avatar' => null,
        ];

        return ['support' => $support, 'dialogs' => $dialogs];
    }

    /** Имя, подпись и тип диалога занятия с точки зрения пользователя. */
    public function describe(User $user, Room $room): array
    {
        $teacher = self::isTeacher($user);
        $participants = $room->participants;
        $group = $room->type === 'group' || $participants->count() > 1;
        $archived = $room->trashed();

        if ($teacher) {
            $student = $group ? null : $participants->first();
            $name = $student?->name ?? $room->name;
            $sub = $group
                ? plural_ru($participants->count(), 'ученик', 'ученика', 'учеников') . $this->nextLesson($room)
                : $room->name . $this->nextLesson($room);
            $link = match (true) {
                $archived => null,
                $student !== null && Route::has('cabinet.teacher.student') => ['label' => 'Карточка ученика', 'url' => TeacherStudentsService::studentUrl($student)],
                Route::has('cabinet.teacher.lesson') => ['label' => 'Занятие', 'url' => route('cabinet.teacher.lesson', $room->id)],
                default => null,
            };
            $avatar = $student;
        } else {
            $name = $group ? $room->name : ($room->user?->name ?? $room->name);
            $sub = $group ? ($room->user?->name ?? '') : $room->name . $this->nextLesson($room);
            $link = null;
            $avatar = $group ? null : $room->user;
        }

        return [
            'type' => $group || ! $avatar ? 'group' : 'person',
            'name' => $name,
            'sub' => $sub,
            'link' => $link,
            'archived' => $archived,
            'avatar' => $avatar,
        ];
    }

    private function nextLesson(Room $room): string
    {
        return $room->next_start && $room->next_start->isFuture() && ! $room->trashed()
            ? ' · занятие ' . HumanDate::at($room->next_start)
            : '';
    }

    private function preview(User $user, Model $message, bool $group): string
    {
        $body = trim((string) $message->content);
        if ($body === '' && ! empty($message->attachments)) {
            $body = $message->attachments[0]['name'] ?? 'Файл';
        }
        $body = str_replace(["\r", "\n"], ' ', $body);

        return match (true) {
            $message->user_id === $user->id => 'Вы: ' . $body,
            $group => ($message->user?->name ?? '') . ': ' . $body,
            default => $body,
        };
    }

    /** Время в списке диалогов: «14:05», «вчера», «вт», «28 мая». */
    private function shortTime(Carbon $at): string
    {
        return match (true) {
            $at->isToday() => $at->format('H:i'),
            $at->isYesterday() => 'вчера',
            $at->greaterThan(now()->subDays(6)->startOfDay()) => ['вс', 'пн', 'вт', 'ср', 'чт', 'пт', 'сб'][$at->dayOfWeek],
            default => HumanDate::date($at),
        };
    }

    /**
     * Лента сообщений: разделители дней и сообщения (старые сверху).
     *
     * @return array{items: array<int, array>, more: bool}
     */
    public function thread(User $user, Room|SupportChat $chat, int $limit): array
    {
        $rows = $chat->messages()->with('user:id,name')->latest('id')->take($limit + 1)->get();
        $more = $rows->count() > $limit;
        $rows = $rows->take($limit)->reverse()->values();

        $group = $chat instanceof Room && $this->describe($user, $chat)['type'] === 'group';
        $items = [];
        $day = null;
        foreach ($rows as $m) {
            $d = $m->created_at->toDateString();
            if ($d !== $day) {
                $day = $d;
                $items[] = ['day' => mb_strtoupper(mb_substr($label = HumanDate::day($m->created_at), 0, 1)) . mb_substr($label, 1)];
            }
            $own = $m->user_id === $user->id;
            $items[] = [
                'id' => $m->id,
                'own' => $own,
                'who' => ! $own && ($group || $chat instanceof SupportChat) ? ($chat instanceof SupportChat ? 'Поддержка Serdal' : $m->user?->name) : null,
                'text' => (string) $m->content,
                'files' => $this->files($m->attachments ?? []),
                'time' => $m->created_at->format('H:i'),
                'read' => $own && $m->read_at !== null,
                'canEdit' => $own && trim((string) $m->content) !== '',
                'canDelete' => $this->canDelete($user, $m),
            ];
        }

        return ['items' => $items, 'more' => $more];
    }

    private function files(array $attachments): array
    {
        return collect($attachments)->filter(fn ($a) => is_array($a) && isset($a['path']))->map(fn (array $a) => [
            'name' => $a['name'] ?? basename($a['path']),
            'url' => Storage::disk('s3')->url($a['path']),
            'image' => str_starts_with((string) ($a['type'] ?? ''), 'image/'),
            'size' => isset($a['size']) ? $this->size((int) $a['size']) : null,
        ])->values()->all();
    }

    private function size(int $bytes): string
    {
        return $bytes >= 1048576
            ? str_replace('.', ',', (string) round($bytes / 1048576, 1)) . ' МБ'
            : max(1, (int) round($bytes / 1024)) . ' КБ';
    }

    /** Помечает прочитанными чужие сообщения и сообщает собеседникам (галочки). */
    public function markRead(User $user, Room|SupportChat $chat): void
    {
        $unread = $chat->messages()->where('user_id', '!=', $user->id)->whereNull('read_at');
        $ids = (clone $unread)->pluck('id')->all();
        if ($ids === []) {
            return;
        }

        $readAt = now();
        $chat->messages()->whereKey($ids)->update(['read_at' => $readAt]);

        if ($chat instanceof Room) {
            broadcast(new MessagesRead($chat->id, $ids, $readAt->toISOString()))->toOthers();
        }
    }

    /**
     * Отправляет сообщение. $attachments — уже загруженные файлы: [path, name, type, size].
     */
    public function send(User $user, Room|SupportChat $chat, string $text, array $attachments = []): Model
    {
        $text = trim($text);
        $attachments = array_values(array_map(fn (array $a) => [
            'path' => $a['path'],
            'name' => $a['name'],
            'type' => $a['type'],
            'size' => $a['size'],
        ], $attachments));

        if ($chat instanceof SupportChat) {
            $message = SupportMessage::create([
                'support_chat_id' => $chat->id,
                'user_id' => $user->id,
                'content' => $text,
                'attachments' => $attachments ?: null,
            ]);
            $message->load('user');
            broadcast(new SupportMessageSent($message))->toOthers();

            if ($user->role === User::ROLE_ADMIN && $chat->user_id !== $user->id) {
                SendUnreadSupportMessageNotification::dispatch($message, $chat->user)->delay(now()->addSeconds(self::NOTIFY_DELAY_SECONDS));
            } else {
                foreach (User::where('role', User::ROLE_ADMIN)->get() as $admin) {
                    SendUnreadSupportMessageNotification::dispatch($message, $admin)->delay(now()->addSeconds(self::NOTIFY_DELAY_SECONDS));
                }
                SendSupportMessageTelegramNotification::dispatch($message);
            }

            return $message;
        }

        $message = Message::create([
            'room_id' => $chat->id,
            'user_id' => $user->id,
            'content' => $text,
            'attachments' => $attachments ?: null,
        ]);
        $message->load('user');
        broadcast(new MessageSent($message))->toOthers();

        $chat->loadMissing('participants', 'user');
        $recipients = $chat->participants->push($chat->user)->filter()->unique('id')->reject(fn (User $u) => $u->id === $user->id);
        foreach ($recipients as $recipient) {
            SendUnreadMessageNotification::dispatch($message, $recipient)->delay(now()->addSeconds(self::NOTIFY_DELAY_SECONDS));
        }

        return $message;
    }

    /** Удалить можно своё сообщение; учитель — любое в своём занятии; админ — любое. */
    public function canDelete(User $user, Model $message): bool
    {
        if ($message->user_id === $user->id || $user->role === User::ROLE_ADMIN) {
            return true;
        }

        return $message instanceof Message
            && self::isTeacher($user)
            && Room::withTrashed()->whereKey($message->room_id)->where('user_id', $user->id)->exists();
    }

    public function delete(User $user, Model $message): void
    {
        abort_unless($this->canDelete($user, $message), 403);

        foreach ($message->attachments ?? [] as $a) {
            if (is_array($a) && isset($a['path'])) {
                Storage::disk('s3')->delete($a['path']);
            }
        }
        // Вложения удалены выше; хук модели SupportMessage ищет их на другом диске
        $message->attachments = null;
        $message->delete();
    }

    public function update(User $user, Model $message, string $text): void
    {
        abort_unless($message->user_id === $user->id, 403);
        $text = trim($text);
        if ($text !== '') {
            $message->update(['content' => $text]);
        }
    }

    /** Ссылка на диалог: новый экран, если он есть, иначе старый кабинет. */
    public static function url(User $user, ?int $roomId = null, bool $support = false): string
    {
        $route = self::isTeacher($user) ? 'cabinet.teacher.messages' : 'cabinet.student.messages';
        $params = array_filter(['room' => $roomId, 'support' => $support ? 1 : null]);

        if (Route::has($route)) {
            return route($route, $params);
        }

        $base = self::isTeacher($user) ? '/tutor/messenger' : '/student/messenger';

        return url($base . ($params ? '?' . http_build_query($params) : ''));
    }
}
