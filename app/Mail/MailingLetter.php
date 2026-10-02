<?php

namespace App\Mail;

use App\Support\Seo;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;

/**
 * Письмо рассылки (админка «Рассылки») — собирает App\Services\MailingService::letter().
 * Уходит через канал newsletter со своим отправителем; в подвале — «Отписаться», в заголовках — List-Unsubscribe
 * (почтовые программы показывают кнопку отписки рядом с отправителем).
 */
class MailingLetter extends Mailable
{
    public function __construct(
        public string $subjectLine,
        public ?string $preheader,
        public string $bodyHtml,
        public ?string $buttonText,
        public ?string $buttonUrl,
        public string $unsubscribeUrl,
        public ?string $pixelUrl = null,
        public bool $oneClick = false,
    ) {}

    public function envelope(): Envelope
    {
        $from = config('mail.newsletter.from');
        $replyTo = config('mail.newsletter.reply_to');

        return new Envelope(
            from: new Address($from['address'], $from['name'] ?: Seo::SITE_NAME),
            replyTo: $replyTo ? [new Address($replyTo, $from['name'] ?: Seo::SITE_NAME)] : [],
            subject: $this->subjectLine,
        );
    }

    public function headers(): Headers
    {
        $text = ['List-Unsubscribe' => '<' . $this->unsubscribeUrl . '>'];
        if ($this->oneClick) {
            $text['List-Unsubscribe-Post'] = 'List-Unsubscribe=One-Click';
        }

        return new Headers(text: $text);
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.mailing',
            text: 'emails.mailing-text',
            with: ['plainBody' => self::plain($this->bodyHtml)],
        );
    }

    /** Текстовая версия письма: абзацы и пункты списков — строками, ссылки — адресом в скобках. */
    public static function plain(string $html): string
    {
        $html = preg_replace_callback('/<a\s[^>]*href="([^"]+)"[^>]*>(.*?)<\/a>/is', function ($m) {
            $text = trim(strip_tags($m[2]));
            $url = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5);

            return $text === '' || $text === $url ? $url : $text . ' (' . $url . ')';
        }, $html);
        $html = preg_replace(['/<br\s*\/?>/i', '/<li[^>]*>/i', '/<\/(p|li|ul|ol|h[1-6])>/i'], ["\n", '— ', "\n\n"], $html);
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5);

        return trim(preg_replace(["/[ \t]+\n/", "/\n{3,}/"], ["\n", "\n\n"], $text));
    }
}
