<?php

namespace App\Notifications;

use App\Models\BlogPost;
use App\Notifications\Messages\CabinetMessage;

/** Учителю: его статью в блоге опубликовали или вернули на доработку (с комментарием). Письмом — решение, которое нельзя пропустить. */
class BlogPostReviewed extends CabinetNotification
{
    public bool $deleteWhenMissingModels = true;

    public function __construct(public BlogPost $post, public bool $published)
    {
        $this->mail = true;
    }

    public function toDatabase(object $notifiable): array
    {
        $scheduled = $this->published && $this->post->isScheduled();
        $message = $this->published
            ? CabinetMessage::make($scheduled ? 'Статью приняли в блог' : 'Статья опубликована в блоге')
                ->body('«' . $this->post->title . '» ' . ($scheduled ? 'выйдет на сайте ' . \App\Support\HumanDate::at($this->post->published_at) . '.' : 'теперь на сайте Serdal.'))
                ->action($scheduled ? 'Открыть статью' : 'Открыть на сайте', $scheduled ? route('cabinet.teacher.blog-article', ['post' => $this->post->id]) : route('blog.show', $this->post->slug))
            : CabinetMessage::make('Статью вернули на доработку')
                ->body('«' . $this->post->title . '»' . ($this->post->review_note ? ': ' . $this->post->review_note : '. Посмотрите комментарий в статье.'))
                ->action('Открыть статью', route('cabinet.teacher.blog-article', ['post' => $this->post->id]));

        return $message->icon('pencil')->toArray();
    }
}
