<?php

namespace App\Notifications;

use App\Models\BlogPost;
use App\Notifications\Messages\CabinetMessage;

/** Ученику: его учитель опубликовал статью в блоге. Кабинет и пуш, без письма. */
class TeacherPublishedBlogPost extends CabinetNotification
{
    public bool $deleteWhenMissingModels = true;

    public function __construct(public BlogPost $post) {}

    public function toDatabase(object $notifiable): array
    {
        return CabinetMessage::make('Новая статья вашего учителя')
            ->body(($this->post->author?->name ?: 'Учитель') . ' — «' . $this->post->title . '»')
            ->icon('pencil')
            ->action('Читать статью', route('blog.show', $this->post->slug))
            ->toArray();
    }
}
