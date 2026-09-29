<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Цветной значок предмета в каталоге: пастельный кружок с линейной иконкой (Tabler, resources/icons/subjects)
 * или с буквой — для языков. Значок рисуется одним SVG (цвета — атрибутами), поэтому одинаково выводится
 * на сайте и в кабинете, где инлайн-стили запрещены.
 *
 * Админ может назначить значок вручную («Настройки» → «Справочники»): subjects.icon — ключ из ICONS
 * или «text:Аа» для своей буквы, subjects.color — ключ из COLORS. Пустое поле — подбор по словам в названии;
 * незнакомый предмет получает первую букву и цвет, стабильный для его названия.
 */
class SubjectIcons
{
    public const TEXT_PREFIX = 'text:';

    /** Своя буква — не длиннее, иначе не влезет в кружок. */
    public const TEXT_MAX = 3;

    /** Цвет => [фон кружка, цвет иконки, подпись]. Порядок — как в окне выбора. */
    public const COLORS = [
        'green' => ['#dcf3e4', '#1f8a4c', 'Зелёный'],
        'teal' => ['#d6f1f1', '#13837f', 'Бирюзовый'],
        'blue' => ['#dde9fb', '#2f6bd8', 'Синий'],
        'violet' => ['#ebe3fb', '#7446d4', 'Фиолетовый'],
        'pink' => ['#fbe1ea', '#cc3b6c', 'Розовый'],
        'red' => ['#fde0dc', '#cf3e2e', 'Красный'],
        'orange' => ['#fde8d7', '#d9661f', 'Оранжевый'],
        'yellow' => ['#fbf0cc', '#a87a00', 'Жёлтый'],
    ];

    /** Иконка => подпись для выбора в админке. Файлы — resources/icons/subjects/{ключ}.svg. */
    public const ICONS = [
        'dna' => 'ДНК',
        'microscope' => 'Микроскоп',
        'flask' => 'Колба',
        'atom' => 'Атом',
        'math-symbols' => 'Знаки',
        'calculator' => 'Калькулятор',
        'geometry' => 'Циркуль',
        'chart-line' => 'График',
        'book' => 'Книга',
        'notebook' => 'Тетрадь',
        'pencil' => 'Карандаш',
        'backpack' => 'Рюкзак',
        'school' => 'Школа',
        'users-group' => 'Люди',
        'building-bank' => 'Здание с колоннами',
        'world' => 'Глобус',
        'language' => 'Язык',
        'code' => 'Код',
        'vector-bezier' => 'Дизайн',
        'palette' => 'Палитра',
        'brush' => 'Кисть',
        'music' => 'Нота',
        'chess' => 'Шахматы',
        'ball-football' => 'Мяч',
        'puzzle' => 'Пазл',
        'briefcase' => 'Портфель',
    ];

    /**
     * Часть названия (в нижнем регистре) => [иконка или «text:буква», цвет]. Проверяется по порядку:
     * «таджвид» раньше «арабского», конкретные языки раньше общего «язык».
     */
    private const RULES = [
        'биолог' => ['dna', 'green'],
        'геометр' => ['geometry', 'violet'],
        'алгебр' => ['math-symbols', 'blue'],
        'математ' => ['math-symbols', 'blue'],
        'литератур' => ['book', 'orange'],
        'чтени' => ['book', 'orange'],
        'начальн' => ['backpack', 'yellow'],
        'обществ' => ['users-group', 'pink'],
        'хими' => ['flask', 'teal'],
        'истори' => ['building-bank', 'red'],
        'информат' => ['code', 'violet'],
        'программ' => ['code', 'violet'],
        'географ' => ['world', 'green'],
        'физик' => ['atom', 'blue'],
        'экономик' => ['chart-line', 'green'],
        'дизайн' => ['vector-bezier', 'pink'],
        'музык' => ['music', 'violet'],
        'вокал' => ['music', 'violet'],
        'рисова' => ['palette', 'orange'],
        'изо' => ['palette', 'orange'],
        'шахмат' => ['chess', 'teal'],
        'логопед' => ['pencil', 'pink'],
        'дошкольн' => ['pencil', 'yellow'],
        'дополнительн' => ['puzzle', 'yellow'],
        'таджвид' => ['text:تَ', 'teal'],
        'коран' => ['text:تَ', 'teal'],
        'арабск' => ['text:ع', 'green'],
        'русск' => ['text:Аа', 'red'],
        'рки' => ['text:Аа', 'red'],
        'английск' => ['text:Ab', 'blue'],
        'немецк' => ['text:Ää', 'yellow'],
        'французск' => ['text:Éé', 'violet'],
        'испанск' => ['text:Ññ', 'orange'],
        'китайск' => ['text:中', 'red'],
        'турецк' => ['text:Ğğ', 'red'],
        'ингушск' => ['text:Гӏ', 'orange'],
        'чеченск' => ['text:Кх', 'green'],
        'язык' => ['language', 'blue'],
    ];

    /** @var array<string, ?string> */
    private static array $svgCache = [];

    /**
     * Итоговый значок: ручной выбор поверх подобранного по названию.
     *
     * @return array{icon: ?string, text: ?string, color: string}
     */
    public static function resolve(string $name, ?string $icon = null, ?string $color = null): array
    {
        $lower = Str::lower(trim($name));
        [$autoMark, $autoColor] = self::match($lower) ?? [self::TEXT_PREFIX . Str::upper(Str::substr($lower, 0, 1)), self::colorFor($lower)];

        $mark = self::validMark($icon) ? $icon : $autoMark;
        $color = isset(self::COLORS[$color ?? '']) ? $color : $autoColor;

        return str_starts_with($mark, self::TEXT_PREFIX)
            ? ['icon' => null, 'text' => mb_substr($mark, mb_strlen(self::TEXT_PREFIX)), 'color' => $color]
            : ['icon' => $mark, 'text' => null, 'color' => $color];
    }

    /** Значение для subjects.icon: ключ иконки или «text:…» с непустой короткой буквой. */
    public static function validMark(?string $mark): bool
    {
        if ($mark === null || $mark === '') {
            return false;
        }
        if (str_starts_with($mark, self::TEXT_PREFIX)) {
            $text = mb_substr($mark, mb_strlen(self::TEXT_PREFIX));

            return trim($text) !== '' && mb_strlen($text) <= self::TEXT_MAX;
        }

        return isset(self::ICONS[$mark]);
    }

    /** SVG-значок размером $size: кружок цвета предмета с иконкой или буквой. */
    public static function badge(string $name, ?string $icon = null, ?string $color = null, int $size = 30): string
    {
        $r = self::resolve($name, $icon, $color);
        [$bg, $fg] = self::COLORS[$r['color']];

        $inner = '';
        if ($r['icon'] && ($body = self::iconBody($r['icon']))) {
            // Иконка 24×24 занимает 60% кружка
            $inner = '<g transform="translate(20 20) scale(2.5)" fill="none" stroke="' . $fg . '" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">' . $body . '</g>';
        } elseif ($r['text'] !== null) {
            $text = $r['text'];
            // Арабские буквы мельче кириллицы того же кегля — делаем крупнее; две-три буквы — мельче одной
            $fontSize = preg_match('/\p{Arabic}/u', $text) ? 52 : (mb_strlen($text) > 1 ? 40 : 46);
            $inner = '<text x="50" y="50" text-anchor="middle" dominant-baseline="central" font-size="' . $fontSize . '" font-weight="600" fill="' . $fg . '">' . e($text) . '</text>';
        }

        return '<svg class="subject-icon" width="' . $size . '" height="' . $size . '" viewBox="0 0 100 100" aria-hidden="true" focusable="false">'
            . '<circle cx="50" cy="50" r="50" fill="' . $bg . '"/>' . $inner . '</svg>';
    }

    private static function match(string $lower): ?array
    {
        foreach (self::RULES as $needle => $rule) {
            // Короткие ключи («изо», «рки») — только целым словом, иначе «изо» найдётся в «изображении»
            $found = mb_strlen($needle) <= 3
                ? preg_match('/(^|[^\p{L}])' . preg_quote($needle, '/') . '($|[^\p{L}])/u', $lower)
                : str_contains($lower, $needle);
            if ($found) {
                return $rule;
            }
        }

        return null;
    }

    private static function colorFor(string $lower): string
    {
        $keys = array_keys(self::COLORS);

        return $keys[crc32($lower) % count($keys)];
    }

    /** Фигуры иконки без обёртки <svg> и служебной рамки Tabler. */
    private static function iconBody(string $icon): ?string
    {
        if (! array_key_exists($icon, self::$svgCache)) {
            $path = resource_path("icons/subjects/{$icon}.svg");
            $body = null;
            if (isset(self::ICONS[$icon]) && is_file($path) && preg_match('/<svg[^>]*>(.*)<\/svg>/s', file_get_contents($path), $m)) {
                $body = preg_replace('/<path stroke="none"[^>]*\/>/', '', $m[1]);
                $body = trim(preg_replace('/\s+/', ' ', $body));
            }
            self::$svgCache[$icon] = $body;
        }

        return self::$svgCache[$icon];
    }
}
