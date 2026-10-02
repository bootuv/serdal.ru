<?php

namespace App\Services;

use App\Models\BlogComment;
use App\Models\BlogCommentBan;
use App\Models\BlogCommentReport;
use App\Models\BlogPost;
use App\Models\User;
use App\Notifications\BlogCommentAdded;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Обсуждения под статьями блога (Livewire App\Livewire\Blog\Comments на странице статьи).
 *
 * Пишут вошедшие учителя, ученики и админ; ответы — один уровень (ответ на ответ попадает в ту же ветку с упоминанием).
 * Автор комментария правит его («изменено») и удаляет; удаленный комментарий с ответами остается заглушкой.
 * Модерация: автор статьи — в своих статьях (удалить чужой комментарий, закрыть обсуждение, запретить человеку
 * комментировать его статьи), админ — везде (запрет — во всем блоге). Жалобы разбирает админ.
 * Уведомления: автору статьи — о новом комментарии, автору комментария — об ответе.
 */
class BlogCommentService
{
    public const MAX_LENGTH = 2000;

    /** Не больше стольких комментариев в минуту от одного человека. */
    public const PER_MINUTE = 5;

    private const ROLES = [User::ROLE_STUDENT, User::ROLE_TUTOR, User::ROLE_ADMIN];

    public function __construct(private BlogService $blog) {}

    /* ---------- Права ---------- */

    /** Почему человек не может написать (null — может). */
    public function whyCannotComment(?User $user, BlogPost $post): ?string
    {
        return match (true) {
            ! $user => 'guest',
            ! in_array($user->role, self::ROLES, true) || $user->is_blocked => 'role',
            ! $post->isPublished() => 'draft',
            $post->comments_closed && ! $this->canModerate($user, $post) => 'closed',
            $this->isBanned($user, $post) => 'banned',
            default => null,
        };
    }

    /** Модерирует обсуждение: админ или автор статьи. */
    public function canModerate(?User $user, BlogPost $post): bool
    {
        return $user && ($user->role === User::ROLE_ADMIN || ($post->author_id && $post->author_id === $user->id));
    }

    public function isBanned(User $user, BlogPost $post): bool
    {
        return BlogCommentBan::where('user_id', $user->id)
            ->where(fn ($q) => $q->whereNull('author_id')->when($post->author_id, fn ($q) => $q->orWhere('author_id', $post->author_id)))
            ->exists();
    }

    public function canEdit(?User $user, BlogComment $comment): bool
    {
        return $user && ! $comment->isDeleted() && $comment->user_id === $user->id;
    }

    public function canDelete(?User $user, BlogComment $comment, BlogPost $post): bool
    {
        return $user && ! $comment->isDeleted() && ($comment->user_id === $user->id || $this->canModerate($user, $post));
    }

    /* ---------- Действия ---------- */

    /** Новый комментарий или ответ. $parent — комментарий, на который отвечают. */
    public function add(BlogPost $post, User $user, string $body, ?BlogComment $parent = null): BlogComment
    {
        if ($why = $this->whyCannotComment($user, $post)) {
            throw ValidationException::withMessages(['body' => self::reason($why)]);
        }
        $body = $this->clean($body);

        $key = 'blog-comment:' . $user->id;
        if (RateLimiter::tooManyAttempts($key, self::PER_MINUTE)) {
            throw ValidationException::withMessages(['body' => 'Слишком часто — подождите минуту и попробуйте снова']);
        }
        RateLimiter::hit($key, 60);

        abort_if($parent && $parent->blog_post_id !== $post->id, 404);
        $root = $parent?->parent_id ? $parent->parent : $parent;

        $comment = DB::transaction(function () use ($post, $user, $body, $root, $parent) {
            $comment = BlogComment::create([
                'blog_post_id' => $post->id,
                'user_id' => $user->id,
                'parent_id' => $root?->id,
                'reply_to_user_id' => $parent && $parent->user_id !== $user->id ? $parent->user_id : null,
                'body' => $body,
            ]);
            $this->recount($post);

            return $comment;
        });

        // Уведомления: автору статьи и тому, кому ответили (каждому один раз, себе — нет)
        $notified = [$user->id];
        $replyTo = $parent?->user;
        if ($replyTo && ! in_array($replyTo->id, $notified, true)) {
            $replyTo->notify(new BlogCommentAdded($comment, reply: true));
            $notified[] = $replyTo->id;
        }
        if ($post->author && ! in_array($post->author->id, $notified, true)) {
            $post->author->notify(new BlogCommentAdded($comment, reply: false));
        }

        return $comment;
    }

    public function edit(BlogComment $comment, User $user, string $body): void
    {
        abort_unless($this->canEdit($user, $comment), 403);
        $comment->update(['body' => $this->clean($body), 'edited_at' => now()]);
    }

    /**
     * Удалить: без ответов — совсем, с ответами — заглушка «Комментарий удален», чтобы ветка не потеряла смысл.
     * Удаление корня без живых ответов удаляет и ветку.
     */
    public function delete(BlogComment $comment, User $actor): void
    {
        $post = $comment->post;
        abort_unless($this->canDelete($actor, $comment, $post), 403);
        $by = $comment->user_id === $actor->id ? BlogComment::DELETED_BY_AUTHOR : BlogComment::DELETED_BY_MODERATOR;

        DB::transaction(function () use ($comment, $by, $post) {
            $hasLiveReplies = ! $comment->parent_id && $comment->replies()->whereNull('deleted_at')->exists();
            if ($hasLiveReplies) {
                $comment->update(['body' => null, 'deleted_at' => now(), 'deleted_by' => $by]);
            } else {
                $parent = $comment->parent;
                $comment->delete();
                // Последний ответ под удаленным корнем — заглушка больше не нужна
                if ($parent?->isDeleted() && ! $parent->replies()->whereNull('deleted_at')->exists()) {
                    $parent->delete();
                }
            }
            BlogCommentReport::where('blog_comment_id', $comment->id)->whereNull('resolved_at')->update(['resolved_at' => now()]);
            $this->recount($post);
        });
    }

    public function report(BlogComment $comment, User $user, ?string $reason = null): void
    {
        abort_if($comment->isDeleted() || $comment->user_id === $user->id, 403);
        BlogCommentReport::updateOrCreate(
            ['blog_comment_id' => $comment->id, 'user_id' => $user->id],
            ['reason' => $reason ? mb_substr(trim($reason), 0, 500) : null, 'resolved_at' => null],
        );
    }

    /** Жалобы разобраны без удаления комментария. */
    public function dismissReports(BlogComment $comment): void
    {
        $comment->reports()->whereNull('resolved_at')->update(['resolved_at' => now()]);
    }

    /** Комментарии с неразобранными жалобами — для админки. */
    public function reported()
    {
        return BlogComment::whereHas('reports', fn ($q) => $q->whereNull('resolved_at'))
            ->withCount(['reports as open_reports' => fn ($q) => $q->whereNull('resolved_at')])
            ->with(['user', 'post', 'reports' => fn ($q) => $q->whereNull('resolved_at')->with('user')])
            ->latest('updated_at')->get();
    }

    public function reportsCount(): int
    {
        return BlogComment::whereHas('reports', fn ($q) => $q->whereNull('resolved_at'))->count();
    }

    /** Запретить комментировать: автор статьи — в своих статьях, админ — везде. */
    public function ban(User $target, User $actor, BlogPost $post): void
    {
        abort_unless($this->canModerate($actor, $post) && $target->id !== $actor->id && $target->role !== User::ROLE_ADMIN, 403);
        $authorId = $actor->role === User::ROLE_ADMIN ? null : $actor->id;
        BlogCommentBan::firstOrCreate(['user_id' => $target->id, 'author_id' => $authorId], ['created_by' => $actor->id]);
    }

    public function unban(User $target, User $actor, BlogPost $post): void
    {
        abort_unless($this->canModerate($actor, $post), 403);
        BlogCommentBan::where('user_id', $target->id)
            ->when($actor->role === User::ROLE_ADMIN, fn ($q) => $q, fn ($q) => $q->where('author_id', $actor->id))
            ->delete();
    }

    /** Кому из обсуждения запрещено писать — для подписи «Заблокирован» у модератора. */
    public function bannedIds(BlogPost $post, array $userIds): array
    {
        return BlogCommentBan::whereIn('user_id', $userIds)
            ->where(fn ($q) => $q->whereNull('author_id')->when($post->author_id, fn ($q) => $q->orWhere('author_id', $post->author_id)))
            ->pluck('user_id')->unique()->all();
    }

    public function setClosed(BlogPost $post, User $actor, bool $closed): void
    {
        abort_unless($this->canModerate($actor, $post), 403);
        $post->update(['comments_closed' => $closed]);
    }

    /* ---------- Служебное ---------- */

    private function clean(string $body): string
    {
        $body = trim(preg_replace("/\n{3,}/", "\n\n", str_replace("\r\n", "\n", $body)));
        if ($body === '') {
            throw ValidationException::withMessages(['body' => 'Напишите комментарий']);
        }
        if (mb_strlen($body) > self::MAX_LENGTH) {
            throw ValidationException::withMessages(['body' => 'Слишком длинно — не больше ' . self::MAX_LENGTH . ' знаков']);
        }

        return $body;
    }

    /** Счетчик комментариев (живые, без заглушек) и вес для «Популярных». */
    private function recount(BlogPost $post): void
    {
        $post->update(['comments_count' => $post->comments()->whereNull('deleted_at')->count()]);
        $this->blog->refreshHotScore($post);
    }

    public static function reason(string $why): string
    {
        return match ($why) {
            'guest' => 'Войдите, чтобы участвовать в обсуждении',
            'closed' => 'Обсуждение закрыто',
            'banned' => 'Вам закрыт доступ к обсуждению здесь',
            'draft' => 'Статья еще не опубликована',
            default => 'Комментировать могут учителя и ученики Serdal',
        };
    }
}
