<?php

namespace App\Notifications;

use App\Notifications\Messages\CabinetMessage;
use App\Notifications\Traits\BroadcastsNotification;
use App\Support\Seo;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;

/**
 * Уведомление кабинета. Содержимое — toDatabase() через CabinetMessage; из него же собираются реалтайм,
 * пуш и письмо. Каналы:
 * - всегда: кабинет (колокольчик) и реалтайм (тост, звук при $broadcastSound);
 * - пуш — если человек включил уведомления на каком-то устройстве;
 * - письмо — если $mail (деньги и решения, которые нельзя пропустить) и у человека есть почта.
 * Отправляется очередью после коммита транзакции: действие человека не ждёт пушей и писем.
 */
abstract class CabinetNotification extends Notification implements ShouldBroadcast, ShouldQueueAfterCommit
{
    use Queueable, BroadcastsNotification;

    /** Дублировать письмом. */
    protected bool $mail = false;

    abstract public function toDatabase(object $notifiable): array;

    public function via(object $notifiable): array
    {
        $channels = ['database', 'broadcast'];

        if (method_exists($notifiable, 'pushSubscriptions') && $notifiable->pushSubscriptions()->exists()) {
            $channels[] = WebPushChannel::class;
        }

        if ($this->mail && filled($notifiable->email ?? null)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $data = $this->toDatabase($notifiable);
        $name = trim((string) ($notifiable->first_name ?? ''));

        $mail = (new MailMessage)
            ->subject($data['title'] . ' — ' . Seo::SITE_NAME)
            ->greeting($name !== '' ? "Здравствуйте, {$name}!" : 'Здравствуйте!')
            ->line((string) $data['body']);

        if ($url = CabinetMessage::urlOf($data)) {
            $mail->action($data['action'] ?? 'Открыть', $url);
        }

        return $mail->salutation('Команда ' . Seo::SITE_NAME);
    }
}
