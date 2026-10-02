<?php

namespace App\Notifications;

use App\Models\BlogComment;
use App\Notifications\Messages\CabinetMessage;
use Illuminate\Support\Str;

/** Обсуждение в блоге: автору статьи — новый комментарий, автору комментария — ответ на него. Без письма. */
class BlogCommentAdded extends CabinetNotification
{
    public bool $deleteWhenMissingModels = true;

    public function __construct(public BlogComment $comment, public bool $reply) {}

    public function toDatabase(object $notifiable): array
    {
        $post = $this->comment->post;
        $who = $this->comment->user?->name ?: 'Кто-то';

        // Без рода в тексте («ответил(а)»): кто — в начале текста уведомления
        return CabinetMessage::make($this->reply ? 'Ответ на ваш комментарий' : 'Новый комментарий к вашей статье')
            ->body($who . ' · «' . $post->title . '»: ' . Str::limit((string) $this->comment->body, 120))
            ->icon('chat')
            ->action('Открыть обсуждение', route('blog.show', $post->slug) . '#comment-' . $this->comment->id)
            ->toArray();
    }
}
