<?php

namespace App\Livewire\Blog;

use App\Models\BlogComment;
use App\Models\BlogPost;
use App\Models\User;
use App\Services\BlogCommentService;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Обсуждение под статьей блога: новые ветки сверху, ответы в ветке — по порядку. Логика и права — App\Services\BlogCommentService.
 * Автор комментария правит и удаляет свой; автор статьи и админ модерируют (удалить, закрыть обсуждение, запретить писать).
 */
class Comments extends Component
{
    private const PAGE = 20;

    #[Locked]
    public int $postId;

    public string $body = '';

    public ?int $replyTo = null;
    public string $replyBody = '';

    public ?int $editing = null;
    public string $editBody = '';

    public ?int $deleting = null;

    public ?int $reporting = null;
    public string $reportReason = '';

    public int $limit = self::PAGE;

    private function post(): BlogPost
    {
        return BlogPost::with('author')->findOrFail($this->postId);
    }

    private function service(): BlogCommentService
    {
        return app(BlogCommentService::class);
    }

    private function user(): User
    {
        $user = auth()->user();
        abort_unless($user, 403);

        return $user;
    }

    private function comment(int $id): BlogComment
    {
        return BlogComment::where('blog_post_id', $this->postId)->findOrFail($id);
    }

    /* ---------- Писать ---------- */

    public function send(): void
    {
        $this->service()->add($this->post(), $this->user(), $this->body);
        $this->body = '';
        $this->resetValidation('body');
    }

    public function startReply(int $id): void
    {
        $this->cancelAll();
        $this->replyTo = $this->comment($id)->id;
    }

    public function sendReply(): void
    {
        try {
            $this->service()->add($this->post(), $this->user(), $this->replyBody, $this->comment((int) $this->replyTo));
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw \Illuminate\Validation\ValidationException::withMessages(['replyBody' => $e->errors()['body'] ?? $e->getMessage()]);
        }
        $this->replyTo = null;
        $this->replyBody = '';
    }

    public function startEdit(int $id): void
    {
        $comment = $this->comment($id);
        abort_unless($this->service()->canEdit(auth()->user(), $comment), 403);
        $this->cancelAll();
        $this->editing = $comment->id;
        $this->editBody = (string) $comment->body;
    }

    public function saveEdit(): void
    {
        try {
            $this->service()->edit($this->comment((int) $this->editing), $this->user(), $this->editBody);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw \Illuminate\Validation\ValidationException::withMessages(['editBody' => $e->errors()['body'] ?? $e->getMessage()]);
        }
        $this->editing = null;
    }

    public function cancelAll(): void
    {
        $this->reset(['replyTo', 'replyBody', 'editing', 'editBody', 'deleting', 'reporting', 'reportReason']);
        $this->resetValidation();
    }

    /* ---------- Удалить, пожаловаться ---------- */

    public function askDelete(int $id): void
    {
        $this->cancelAll();
        $this->deleting = $this->comment($id)->id;
    }

    public function delete(): void
    {
        $this->service()->delete($this->comment((int) $this->deleting), $this->user());
        $this->deleting = null;
    }

    public function startReport(int $id): void
    {
        $this->cancelAll();
        $this->reporting = $this->comment($id)->id;
    }

    public function sendReport(): void
    {
        $this->service()->report($this->comment((int) $this->reporting), $this->user(), $this->reportReason);
        $this->reporting = null;
        $this->reportReason = '';
    }

    /* ---------- Модерация ---------- */

    public function ban(int $userId): void
    {
        $this->service()->ban(User::findOrFail($userId), $this->user(), $this->post());
    }

    public function unban(int $userId): void
    {
        $this->service()->unban(User::findOrFail($userId), $this->user(), $this->post());
    }

    public function toggleClosed(): void
    {
        $post = $this->post();
        $this->service()->setClosed($post, $this->user(), ! $post->comments_closed);
    }

    public function more(): void
    {
        $this->limit += self::PAGE;
    }

    /* ---------- Вид ---------- */

    public function render()
    {
        $post = $this->post();
        $user = auth()->user();
        $service = $this->service();

        $rootsQuery = $post->comments()->whereNull('parent_id');
        $total = (clone $rootsQuery)->count();
        $roots = $rootsQuery
            ->with(['user', 'replies.user', 'replies.replyTo'])
            ->latest('created_at')->latest('id')
            ->limit($this->limit)->get();

        $moderator = $service->canModerate($user, $post);
        $people = $roots->pluck('user_id')->merge($roots->flatMap->replies->pluck('user_id'))->filter()->unique()->all();

        return view('livewire.blog.comments', [
            'post' => $post,
            'user' => $user,
            'why' => $service->whyCannotComment($user, $post),
            'moderator' => $moderator,
            'roots' => $roots,
            'count' => (int) $post->comments_count,
            'more' => max(0, $total - $this->limit),
            'banned' => $moderator ? $service->bannedIds($post, $people) : [],
            // Мои неразобранные жалобы — подтверждение под комментарием, «Пожаловаться» для него больше не показываем
            'reported' => $user ? \App\Models\BlogCommentReport::where('user_id', $user->id)->whereNull('resolved_at')
                ->whereIn('blog_comment_id', $roots->pluck('id')->merge($roots->flatMap->replies->pluck('id')))
                ->pluck('blog_comment_id')->all() : [],
            'loginUrl' => route('login', ['next' => $post->url . '#comments']),
            'service' => $service,
        ]);
    }
}
