<?php

namespace App\Services\ShareCard;

use Intervention\Image\Drivers\Gd\FontProcessor;
use Intervention\Image\Interfaces\ImageInterface;
use Intervention\Image\Laravel\Facades\Image;
use Intervention\Image\Typography\Font;
use Intervention\Image\Typography\FontFactory;

/**
 * Рисует текст с эмодзи: обычные символы — шрифтом, эмодзи — картинками
 * (см. EmojiImageProvider). Перенос строк, ширина и высота считаются здесь же,
 * потому что стандартный text() Intervention рисует всю строку одним шрифтом.
 */
class EmojiTextRenderer
{
    private const BASE = '[\x{00A9}\x{00AE}\x{203C}\x{2049}\x{2122}\x{2139}\x{2194}-\x{21AA}'
        . '\x{231A}-\x{23FA}\x{24C2}\x{25AA}-\x{27BF}\x{2934}\x{2935}\x{2B00}-\x{2BFF}'
        . '\x{3030}\x{303D}\x{3297}\x{3299}\x{1F000}-\x{1FAFF}]'
        . '[\x{FE0E}\x{FE0F}]?[\x{1F3FB}-\x{1F3FF}]?';

    // Флаг-тег (например, флаг Англии), флаг страны, keycap либо базовый
    // символ со склейками через ZWJ (семьи, профессии и т.п.)
    private const SEQUENCE = '\x{1F3F4}[\x{E0060}-\x{E007F}]+'
        . '|[\x{1F1E6}-\x{1F1FF}]{2}'
        . '|[0-9#*]\x{FE0F}?\x{20E3}'
        . '|' . self::BASE . '(?:\x{200D}' . self::BASE . ')*';

    private const SPLIT_PATTERN = '/(' . self::SEQUENCE . ')/u';
    private const MATCH_PATTERN = '/^(?:' . self::SEQUENCE . ')$/u';

    // Служебные символы, которые остались вне эмодзи-последовательностей
    private const INVISIBLE_PATTERN = '/[\x{200D}\x{FE0E}\x{FE0F}]/u';

    // Эмодзи рисуется квадратом чуть меньше кегля, с небольшим воздухом по бокам
    private const EMOJI_SCALE = 0.95;
    private const EMOJI_ADVANCE_SCALE = 1.08;

    // Насколько верх картинки поднят над базовой линией (в долях её высоты)
    private const EMOJI_BASELINE_RATIO = 0.88;

    private FontProcessor $processor;

    /** @var array<string, int> */
    private array $spaceWidths = [];

    public function __construct(private EmojiImageProvider $emoji)
    {
        $this->processor = new FontProcessor();
    }

    /**
     * Высота текстового блока: столько же, сколько занял бы text() Intervention.
     */
    public function height(string $text, TextStyle $style): int
    {
        $lines = $this->lines($text, $style);

        return count($lines) * $this->processor->leading($this->font($style));
    }

    /**
     * Высота до нижнего края букв последней строки — без «воздуха» интерлиньяжа
     * под ней. По ней выравнивают низ блока по макету.
     */
    public function inkHeight(string $text, TextStyle $style): int
    {
        $font = $this->font($style);
        $lines = $this->lineCount($text, $style);

        return ($lines - 1) * $this->processor->leading($font) + $this->processor->typographicalSize($font);
    }

    public function lineCount(string $text, TextStyle $style): int
    {
        return count($this->lines($text, $style));
    }

    /**
     * Расстояние между базовыми линиями соседних строк.
     */
    public function leading(TextStyle $style): int
    {
        return $this->processor->leading($this->font($style));
    }

    /**
     * Ширина самого широкого слова — GD переносит текст только по пробелам,
     * поэтому по ней подбирается кегль для заголовков.
     */
    public function widestWord(string $text, TextStyle $style): int
    {
        $widest = 0;

        foreach ($this->words($text) as $word) {
            $widest = max($widest, $this->word($word, $style)[1]);
        }

        return $widest;
    }

    /**
     * Рисует текст от точки ($left, $top) и возвращает Y низа блока.
     */
    public function draw(ImageInterface $image, string $text, int $left, int $top, TextStyle $style): int
    {
        $font = $this->font($style);
        $leading = $this->processor->leading($font);
        $baselineOffset = $this->processor->capHeight($font);
        $emojiSize = $this->emojiSize($style);
        $emojiPadding = (int) round(($this->emojiAdvance($style) - $emojiSize) / 2);

        $y = $top;

        foreach ($this->lines($text, $style) as $line) {
            foreach ($line as [$type, $value, $offset]) {
                if ($type === 'text') {
                    $image->text($value, $left + $offset, $y, function (FontFactory $factory) use ($style) {
                        $factory->filename($style->fontPath);
                        $factory->size($style->size);
                        $factory->color($style->color);
                        $factory->align('left');
                        $factory->valign('top');
                        $factory->lineHeight($style->lineHeight);
                    });

                    continue;
                }

                $bytes = $this->emoji->bytes($value);

                if ($bytes === null) {
                    continue;
                }

                $image->place(
                    Image::read($bytes)->resize($emojiSize, $emojiSize),
                    'top-left',
                    $left + $offset + $emojiPadding,
                    $y + $baselineOffset - (int) round($emojiSize * self::EMOJI_BASELINE_RATIO),
                );
            }

            $y += $leading;
        }

        return $y;
    }

    /**
     * Раскладка по строкам: каждая строка — список сегментов
     * [тип, значение, отступ по X от левого края блока].
     *
     * @return list<list<array{0: string, 1: string, 2: int}>>
     */
    private function lines(string $text, TextStyle $style): array
    {
        $space = $this->spaceWidth($style);
        $wrapWidth = $style->wrapWidth;

        $lines = [];
        $current = [];
        $lineWidth = 0;

        foreach ($this->words($text) as $word) {
            [$segments, $width] = $this->word($word, $style);

            if ($segments === []) {
                continue;
            }

            if ($current !== [] && $wrapWidth !== null && $lineWidth + $space + $width > $wrapWidth) {
                $lines[] = $current;
                $current = [];
                $lineWidth = 0;
            }

            $offset = $current === [] ? 0 : $lineWidth + $space;

            foreach ($segments as [$type, $value, $position]) {
                $current[] = [$type, $value, $offset + $position];
            }

            $lineWidth = $offset + $width;
        }

        if ($current !== []) {
            $lines[] = $current;
        }

        return $lines === [] ? [[]] : $lines;
    }

    /**
     * Сегменты одного слова и его полная ширина. Эмодзи без картинки
     * выбрасываются — раньше вместо них рисовались пустые квадраты.
     *
     * @return array{list<array{0: string, 1: string, 2: int}>, int}
     */
    private function word(string $word, TextStyle $style): array
    {
        $segments = [];
        $width = 0;

        foreach (preg_split(self::SPLIT_PATTERN, $word, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) as $part) {
            if (preg_match(self::MATCH_PATTERN, $part)) {
                if ($this->emoji->bytes($part) === null) {
                    continue;
                }

                $segments[] = ['emoji', $part, $width];
                $width += $this->emojiAdvance($style);

                continue;
            }

            $part = preg_replace(self::INVISIBLE_PATTERN, '', $part);

            if ($part === '') {
                continue;
            }

            $segments[] = ['text', $part, $width];
            $width += $this->textWidth($part, $style);
        }

        return [$segments, $width];
    }

    /**
     * @return list<string>
     */
    private function words(string $text): array
    {
        return preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    private function textWidth(string $text, TextStyle $style): int
    {
        $box = $this->processor->boxSize($text, $this->font($style));

        return (int) round($box->width() + $box->pivot()->x());
    }

    private function spaceWidth(TextStyle $style): int
    {
        $key = $style->fontPath . '|' . $style->size;

        // Пробел меряем по разнице ширин, потому что bbox сам по себе его не показывает
        return $this->spaceWidths[$key] ??= max(
            1,
            $this->textWidth('A A', $style) - $this->textWidth('AA', $style)
        );
    }

    private function emojiSize(TextStyle $style): int
    {
        return max(1, (int) round($style->size * self::EMOJI_SCALE));
    }

    private function emojiAdvance(TextStyle $style): int
    {
        return max(1, (int) round($style->size * self::EMOJI_ADVANCE_SCALE));
    }

    private function font(TextStyle $style): Font
    {
        $font = new Font($style->fontPath);
        $font->setSize($style->size);
        $font->setLineHeight($style->lineHeight);

        return $font;
    }
}
