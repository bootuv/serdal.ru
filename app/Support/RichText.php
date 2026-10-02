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
        if ($html === null || trim(strip_tags($html, '<img><video>')) === '') {
            return null;
        }

        // Ссылки из условия открываются в новой вкладке, чтобы не терять кабинет
        return new HtmlString(preg_replace('/<a(?=\s)/i', '<a target="_blank" rel="noopener"', self::sanitize($html)));
    }

    /**
     * Видео в тексте (новости, статьи блога) → наш плеер записей (x-ui.video-player). Бывшие GIF (loop) остаются
     * беззвучным зацикленным <video>. Принимает уже очищенный HTML (результат html()).
     */
    public static function players(?HtmlString $html): ?HtmlString
    {
        if ($html === null || ! str_contains((string) $html, '<video')) {
            return $html;
        }

        return new HtmlString(preg_replace_callback('~<video\b([^>]*)>.*?</video>~is', function (array $m) {
            if (preg_match('~\sloop\b~i', $m[1])) {
                return $m[0];
            }
            $attr = fn (string $name) => preg_match('~\s' . $name . '="([^"]*)"~i', $m[1], $a) ? html_entity_decode($a[1]) : null;
            if (! $src = $attr('src')) {
                return '';
            }

            $ratio = (int) $attr('width') && (int) $attr('height') ? (int) $attr('width') . ' / ' . (int) $attr('height') : null;

            return \Illuminate\Support\Facades\Blade::render('<x-ui.video-player :src="$src" :poster="$poster" label="Видео" fit :ratio="$ratio" />', ['src' => $src, 'poster' => $attr('poster'), 'ratio' => $ratio]);
        }, (string) $html));
    }

    /** HTML из редактора → очищенный HTML для хранения (та же очистка, что при показе). Пустой — null. */
    public static function clean(?string $html): ?string
    {
        if ($html === null || trim(strip_tags($html, '<img><video>')) === '') {
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

    /**
     * HTML → Markdown для ИИ-агентов и llms-full.txt (статьи блога): заголовки, абзацы, списки, цитаты, ссылки,
     * картинки, жирный и курсив. Относительные ссылки становятся абсолютными ($base — адрес сайта).
     */
    public static function toMarkdown(?string $html, string $base = ''): string
    {
        if (! trim((string) $html)) {
            return '';
        }

        $doc = new \DOMDocument;
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8"?><div>' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $md = self::mdChildren($doc->documentElement, rtrim($base, '/'));

        return trim(preg_replace("/\n{3,}/", "\n\n", $md));
    }

    private static function mdChildren(\DOMNode $node, string $base, string $list = ''): string
    {
        $out = '';
        $n = 0;
        foreach ($node->childNodes as $child) {
            if ($child instanceof \DOMText) {
                $out .= preg_replace('/\s+/u', ' ', $child->textContent);

                continue;
            }
            if (! $child instanceof \DOMElement) {
                continue;
            }
            $inner = fn () => self::mdChildren($child, $base);
            $abs = fn (string $url) => str_starts_with($url, '/') && ! str_starts_with($url, '//') ? $base . $url : $url;
            $out .= match (strtolower($child->nodeName)) {
                'h1', 'h2' => "\n\n## " . trim($inner()) . "\n\n",
                'h3' => "\n\n### " . trim($inner()) . "\n\n",
                'h4', 'h5', 'h6' => "\n\n#### " . trim($inner()) . "\n\n",
                'p', 'div', 'figure', 'section' => "\n\n" . trim($inner()) . "\n\n",
                'br' => "  \n",
                'hr' => "\n\n---\n\n",
                'strong', 'b' => ($t = trim($inner())) !== '' ? '**' . $t . '**' : '',
                'em', 'i' => ($t = trim($inner())) !== '' ? '*' . $t . '*' : '',
                'code' => '`' . $child->textContent . '`',
                'a' => ($href = $child->getAttribute('href')) ? '[' . trim($inner()) . '](' . $abs($href) . ')' : $inner(),
                'img' => $child->getAttribute('src') ? "\n\n![" . $child->getAttribute('alt') . '](' . $abs($child->getAttribute('src')) . ")\n\n" : '',
                'ul' => "\n\n" . self::mdChildren($child, $base, 'ul') . "\n\n",
                'ol' => "\n\n" . self::mdChildren($child, $base, 'ol') . "\n\n",
                'li' => ($list === 'ol' ? (++$n) . '. ' : '- ') . trim(preg_replace("/\n{2,}/", "\n", $inner())) . "\n",
                'blockquote' => "\n\n" . preg_replace('/^/m', '> ', trim($inner())) . "\n\n",
                'script', 'style' => '',
                default => $inner(),
            };
        }

        return $out;
    }

    /** Очистка HTML: безопасные теги (и video), относительные ссылки и картинки, class и style (правила прежнего редактора). */
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
                // GIF в новостях хранится зацикленным беззвучным видео — без autoplay оно стоит на первом кадре
                ->allowAttribute('autoplay', allowedElements: ['video'])
                ->withMaxInputLength(500000),
        );

        return $sanitizer->sanitize($html);
    }
}
