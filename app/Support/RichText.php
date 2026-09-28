<?php

namespace App\Support;

use Illuminate\Support\HtmlString;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Текст заданий, ответов и комментариев в кабинетах.
 * В базе — HTML. Условия заданий и тексты профиля вводятся редактором (x-ui.editor), ответы и комментарии — простым текстом.
 */
class RichText
{
    /** HTML из базы → безопасный HTML для блока с классом `.rich`. Пустой текст — null. */
    public static function html(?string $html): ?HtmlString
    {
        if ($html === null || trim(strip_tags($html)) === '') {
            return null;
        }

        // Ссылки из условия открываются в новой вкладке, чтобы не терять кабинет
        return new HtmlString(preg_replace('/<a(?=\s)/i', '<a target="_blank" rel="noopener"', self::sanitize($html)));
    }

    /** HTML из редактора → очищенный HTML для хранения (та же очистка, что при показе). Пустой — null. */
    public static function clean(?string $html): ?string
    {
        if ($html === null || trim(strip_tags($html)) === '') {
            return null;
        }

        return self::sanitize($html);
    }

    /** Простой текст → HTML для хранения: абзацы по пустой строке, переносы строк — <br>. */
    public static function fromPlain(?string $text): ?string
    {
        $text = trim(str_replace("\r\n", "\n", (string) $text));

        if ($text === '') {
            return null;
        }

        return collect(preg_split("/\n{2,}/", $text))
            ->map(fn (string $p) => '<p>' . nl2br(e(trim($p)), false) . '</p>')
            ->implode('');
    }

    /** Простой текст сообщения → экранированный HTML, где адреса (https://…, www.…) стали ссылками в новой вкладке. */
    public static function linkify(string $text): HtmlString
    {
        $parts = preg_split('~((?:https?://|www\.)[^\s<>"\']+)~iu', $text, -1, PREG_SPLIT_DELIM_CAPTURE);

        $html = '';
        foreach ($parts as $i => $part) {
            if ($i % 2 === 0) {
                $html .= e($part);
                continue;
            }

            // Знаки препинания в конце — часть предложения, а не адреса; закрывающая скобка — если нет открывающей
            preg_match('~^(.*?)([.,;:!?…]*)$~su', $part, $m);
            [$url, $tail] = [$m[1], $m[2]];
            while (str_ends_with($url, ')') && substr_count($url, '(') < substr_count($url, ')')) {
                $url = substr($url, 0, -1);
                $tail = ')' . $tail;
            }
            if (preg_match('~^(?:https?://|www\.)$~i', $url)) {
                $html .= e($part);
                continue;
            }

            $href = preg_match('~^https?://~i', $url) ? $url : 'https://' . $url;
            $html .= '<a href="' . e($href) . '" target="_blank" rel="noopener noreferrer" class="link">' . e($url) . '</a>' . e($tail);
        }

        return new HtmlString($html);
    }

    /** HTML → простой текст для поля ввода. */
    public static function toPlain(?string $html): string
    {
        if (! $html) {
            return '';
        }

        $text = preg_replace(['/<br\s*\/?>/i', '/<\/(p|div|li|h[1-6])>/i'], ["\n", "\n\n"], $html);
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace("/\n{3,}/", "\n\n", $text));
    }

    /** Очистка HTML: безопасные теги, относительные ссылки и картинки, class и style (правила прежнего редактора). */
    private static function sanitize(string $html): string
    {
        static $sanitizer = null;
        $sanitizer ??= new HtmlSanitizer(
            (new HtmlSanitizerConfig)
                ->allowSafeElements()
                ->allowRelativeLinks()
                ->allowRelativeMedias()
                ->allowAttribute('class', allowedElements: '*')
                ->allowAttribute('style', allowedElements: '*')
                ->withMaxInputLength(500000),
        );

        return $sanitizer->sanitize($html);
    }
}
