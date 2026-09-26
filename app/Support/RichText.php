<?php

namespace App\Support;

use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * Текст заданий, ответов и комментариев в кабинетах.
 * В базе — HTML (старый кабинет пишет его редактором), в новом кабинете вводим простым текстом.
 */
class RichText
{
    /** HTML из базы → безопасный HTML для блока с классом `.rich` (очистка как в Filament). Пустой текст — null. */
    public static function html(?string $html): ?HtmlString
    {
        if ($html === null || trim(strip_tags($html)) === '') {
            return null;
        }

        // Ссылки из условия открываются в новой вкладке, чтобы не терять кабинет
        return new HtmlString(preg_replace('/<a(?=\s)/i', '<a target="_blank" rel="noopener"', Str::sanitizeHtml($html)));
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
}
