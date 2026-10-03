<?php

namespace App\Notifications;

use App\Models\BlogPost;
use App\Notifications\Messages\CabinetMessage;

/** Администраторам: учитель отправил статью в блог на проверку (дублируется в Telegram — BlogService::submit). */
class BlogPostSubmitted extends CabinetNotification
{
    public bool $deleteWhenMissingModels = true;

    public function __construct(public BlogPost $post, public bool $resubmitted = false)
    {
    }

    public function toDatabase(object $notifiable): array
    {
        return CabinetMessage::make($this->resubmitted ? 'Статья снова на проверке' : 'Статья на проверку')
            ->body(($this->post->author?->name ?? 'Учитель') . ' · «' . $this->post->title . '»')
            ->icon('pencil')
            ->action('Проверить статью', route('cabinet.admin.blog-article', ['post' => $this->post->id]))
            ->toArray();
    }
}
