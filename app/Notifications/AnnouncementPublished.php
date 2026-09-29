<?php

namespace App\Notifications;

use App\Models\Announcement;
use App\Notifications\Messages\CabinetMessage;
use App\Services\AnnouncementService;

/** Новость от администрации: колокольчик и пуш, письмом — если админ отметил «Письмо на почту». */
class AnnouncementPublished extends CabinetNotification
{
    /** Новость удалили до отправки — уведомление не нужно. */
    public bool $deleteWhenMissingModels = true;

    public function __construct(public Announcement $announcement)
    {
        $this->mail = $announcement->send_mail;
    }

    public function toDatabase(object $notifiable): array
    {
        return CabinetMessage::make($this->announcement->title)
            ->body($this->announcement->excerpt(140) ?: null)
            ->icon('news')
            ->action('Читать новость', AnnouncementService::url($this->announcement, $notifiable))
            ->toArray();
    }
}
