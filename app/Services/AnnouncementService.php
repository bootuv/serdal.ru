<?php

namespace App\Services;

use App\Models\Announcement;
use App\Models\AnnouncementRead;
use App\Models\User;
use App\Notifications\AnnouncementPublished;
use App\Support\RichText;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;

/**
 * Новости от администрации: админка «Новости» пишет и публикует (сразу или по времени),
 * учитель и ученик читают в разделе «Новости». Важная непрочитанная — карточкой на главной (livewire:cabinet.news-banner).
 *
 * Непрочитанными считаем новости, вышедшие после регистрации человека, и закреплённые: новичку не нужен весь архив счётчиком.
 * Уведомление (колокольчик, пуш, письмо — если отмечено) рассылается один раз, когда наступает время публикации:
 * сразу по кнопке или командой news:publish (раз в минуту).
 */
class AnnouncementService
{
    private const CHUNK = 200;

    /* ---------- Кабинеты ---------- */

    /** Новости, которые видит человек: опубликованные для его роли, закреплённые — первыми. */
    public function visibleFor(User $user): Builder
    {
        return Announcement::published()
            ->whereIn('audience', Announcement::audiencesFor($user->role))
            ->orderByDesc('is_pinned')
            ->orderByDesc('published_at')
            ->orderByDesc('id');
    }

    public function unreadFor(User $user): Builder
    {
        return $this->visibleFor($user)
            ->whereNotIn('id', AnnouncementRead::select('announcement_id')->where('user_id', $user->id))
            ->where(fn (Builder $q) => $q->where('is_pinned', true)
                ->when($user->created_at, fn (Builder $q) => $q->orWhere('published_at', '>=', $user->created_at)));
    }

    public function unreadCount(?User $user): int
    {
        if (! $user || ! in_array($user->role, [User::ROLE_TUTOR, User::ROLE_STUDENT], true)) {
            return 0;
        }

        return $this->unreadFor($user)->count();
    }

    /** Самая свежая непрочитанная важная новость — для карточки на главной. */
    public function banner(User $user): ?Announcement
    {
        return $this->unreadFor($user)->where('is_important', true)->reorder()->orderByDesc('published_at')->first();
    }

    /** id прочитанных из списка. */
    public function readIds(User $user, array $ids): array
    {
        return AnnouncementRead::where('user_id', $user->id)->whereIn('announcement_id', $ids)->pluck('announcement_id')->all();
    }

    public function markRead(Announcement $announcement, User $user): void
    {
        AnnouncementRead::firstOrCreate(
            ['announcement_id' => $announcement->id, 'user_id' => $user->id],
            ['read_at' => now()],
        );
    }

    public function canSee(Announcement $announcement, User $user): bool
    {
        return $announcement->isPublished() && in_array($announcement->audience, Announcement::audiencesFor($user->role), true);
    }

    /** Ссылка на новость в кабинете роли. */
    public static function url(Announcement $announcement, User $user): string
    {
        $name = $user->role === User::ROLE_STUDENT ? 'cabinet.student.news-item' : 'cabinet.teacher.news-item';

        return Route::has($name) ? route($name, $announcement) : url('/cabinet');
    }

    public static function listUrl(User $user): string
    {
        $name = $user->role === User::ROLE_STUDENT ? 'cabinet.student.news' : 'cabinet.teacher.news';

        return Route::has($name) ? route($name) : url('/cabinet');
    }

    /* ---------- Админка ---------- */

    /**
     * Сохранить новость. $data: title, body, audience, is_important, is_pinned, send_mail, published_at (null — черновик).
     * Кому и письмо после рассылки не меняются: уведомление уже ушло.
     */
    public function save(?Announcement $announcement, array $data, ?User $author = null): Announcement
    {
        $announcement ??= new Announcement(['created_by' => $author?->id]);

        $fields = [
            'title' => trim((string) $data['title']),
            'body' => RichText::clean($data['body'] ?? null),
            'is_important' => (bool) ($data['is_important'] ?? false),
            'is_pinned' => (bool) ($data['is_pinned'] ?? false),
            'published_at' => $data['published_at'] ?? null,
        ];
        if (! $announcement->notified_at) {
            $fields['audience'] = $data['audience'] ?? Announcement::AUDIENCE_TEACHERS;
            $fields['send_mail'] = (bool) ($data['send_mail'] ?? false);
        }

        $announcement->fill($fields)->save();
        // Картинки и видео, которых в тексте больше нет, — с хранилища
        app(EditorMediaService::class)->sync($announcement, [$announcement->body]);

        $this->notify($announcement);

        return $announcement;
    }

    public function unpublish(Announcement $announcement): void
    {
        $announcement->update(['published_at' => null]);
    }

    public function delete(Announcement $announcement): void
    {
        app(EditorMediaService::class)->purgeOwner($announcement);
        $announcement->delete();
    }

    /** Кому адресована новость: активные учителя и (или) ученики. */
    public function recipients(Announcement $announcement): Builder
    {
        return User::query()
            ->whereIn('role', $announcement->roles())
            ->where(fn (Builder $q) => $q->where('is_blocked', false)->orWhereNull('is_blocked'));
    }

    /** «Прочитали 84 из 120». */
    public function stats(Announcement $announcement): array
    {
        return [
            'read' => $announcement->reads()->count(),
            'total' => $this->recipients($announcement)->count(),
        ];
    }

    /* ---------- Рассылка ---------- */

    /** Разослать уведомления о новостях, время которых наступило (команда news:publish). */
    public function publishDue(): int
    {
        $sent = 0;
        Announcement::published()->whereNull('notified_at')->get()
            ->each(function (Announcement $a) use (&$sent) {
                $sent += $this->notify($a) ? 1 : 0;
            });

        return $sent;
    }

    /** Уведомить адресатов, если новость уже вышла и уведомление ещё не отправляли. */
    private function notify(Announcement $announcement): bool
    {
        if (! $announcement->isPublished() || $announcement->notified_at) {
            return false;
        }

        // Отмечаем атомарно: два параллельных запуска не разошлют дважды
        $claimed = Announcement::whereKey($announcement->id)->whereNull('notified_at')->update(['notified_at' => now()]);
        if (! $claimed) {
            return false;
        }
        $announcement->refresh();

        $this->recipients($announcement)->chunkById(self::CHUNK, function ($users) use ($announcement) {
            Notification::send($users, new AnnouncementPublished($announcement));
        });

        return true;
    }

    /** Время публикации из поля формы («2026-10-05T10:00»); пустое или неверное — null. */
    public static function parseTime(?string $value): ?Carbon
    {
        if (blank($value)) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
