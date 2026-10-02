<?php

namespace App\Notifications;

use App\Models\BlogPost;
use App\Notifications\Messages\CabinetMessage;

/** Новая статья автора: ученику — его учителя, подписчику — автора из его ленты. Кабинет и пуш, без письма. */
class TeacherPublishedBlogPost extends CabinetNotification
{
    public bool $deleteWhenMissingModels = true;

    public function __construct(public BlogPost $post, public bool $follower = false) {}

    public function toDatabase(object $notifiable): array
    {
        return CabinetMessage::make($this->follower ? 'Новая статья в вашей ленте' : 'Новая статья вашего учителя')
            ->body(($this->post->author?->name ?: 'Учитель') . ' — «' . $this->post->title . '»')
            ->icon('pencil')
            ->action('Читать статью', route('blog.show', $this->post->slug))
            ->toArray();
    }
}
