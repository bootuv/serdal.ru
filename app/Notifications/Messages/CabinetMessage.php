<?php

namespace App\Notifications\Messages;

/**
 * Содержимое уведомления кабинета: то, что хранится в базе (канал database), уходит в реалтайм (broadcast)
 * и в пуш (web push). Панель уведомлений показывает заголовок, текст и иконку; клик ведёт по ссылке.
 *
 *   CabinetMessage::make('Подписка оплачена')->body('…')->icon('wallet')->action('Моя подписка', $url)->toArray()
 *
 * Иконка — имя из components/ui/icon. Уведомления, сохранённые раньше, хранят ссылку в actions[0].url —
 * их читает self::urlOf().
 */
class CabinetMessage
{
    private ?string $body = null;

    private string $icon = 'bell';

    private ?string $url = null;

    private ?string $action = null;

    public function __construct(private string $title)
    {
    }

    public static function make(string $title): self
    {
        return new self($title);
    }

    public function body(?string $body): self
    {
        $this->body = $body;

        return $this;
    }

    public function icon(string $icon): self
    {
        $this->icon = $icon;

        return $this;
    }

    /** Кнопка в письме и ссылка при клике в панели и в пуше. */
    public function action(string $label, ?string $url): self
    {
        $this->action = $label;
        $this->url = $url;

        return $this;
    }

    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            'icon' => $this->icon,
            'url' => $this->url,
            'action' => $this->action,
        ];
    }

    /** Текст сообщения для уведомления: начало текста, а если текста нет — «Файл «имя»» / «Файлы: 3». */
    public static function messagePreview(?string $content, ?array $attachments): string
    {
        $text = trim(strip_tags((string) $content));
        if ($text !== '') {
            return \Illuminate\Support\Str::limit($text, 80);
        }

        $files = array_values($attachments ?? []);

        return match (count($files)) {
            0 => 'Сообщение',
            1 => 'Файл «' . ($files[0]['name'] ?? 'без названия') . '»',
            default => 'Файлы: ' . count($files),
        };
    }

    /** Ссылка уведомления: новый формат (url) или старый (actions[0].url). */
    public static function urlOf(array $data): ?string
    {
        return $data['url'] ?? $data['actions'][0]['url'] ?? null;
    }
}
