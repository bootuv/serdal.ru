<?php

namespace App\Services;

use App\Models\Review;
use App\Services\ShareCard\EmojiTextRenderer;
use App\Services\ShareCard\TextStyle;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Interfaces\ImageInterface;
use Intervention\Image\Laravel\Facades\Image;

class ReviewShareCardGenerator
{
    // Шаблон 2160x3840 (9:16, логотип внизу), итоговая карточка отдается в 1080x1920
    private const OUTPUT_WIDTH = 1080;
    private const TEXT_COLOR = '1d1d1b';

    // Шапка горизонтальная: аватарка слева, справа от неё имя автора,
    // под именем подпись «Репетитор: {имя}». Всё выровнено по левому краю.
    private const AVATAR_SIZE = 360;
    private const AVATAR_TOP = 160;
    private const NAME_TOP = 200;
    private const NAME_FONT_SIZE = 96;
    private const SUBTITLE_FONT_SIZE = 64;
    private const NAME_SUBTITLE_GAP = 50;
    private const HEADER_LINE_HEIGHT = 1.3;

    // Высота шапки считается от фактических переносов имени и подписи;
    // линия и текст идут после самого нижнего элемента (аватар или подпись)
    private const DIVIDER_GAP = 130;  // низ шапки -> линия
    private const TEXT_GAP = 160;     // линия -> текст отзыва

    private const TEXT_LEFT = 192;
    private const TEXT_WRAP_WIDTH = 1776;
    private const TEXT_LINE_HEIGHT = 1.6;
    private const TEXT_LINE_HEIGHT_MAX = 1.75;

    // Логотип в шаблоне занимает y=3538..3679, снизу до края холста 161px.
    // Над логотипом оставляем ровно столько же, сколько под ним.
    private const LOGO_TOP = 3538;
    private const LOGO_BOTTOM_MARGIN = 161;
    private const TEXT_BOTTOM = self::LOGO_TOP - self::LOGO_BOTTOM_MARGIN;

    private const HEADER_TEXT_LEFT = self::TEXT_LEFT + self::AVATAR_SIZE + 72;
    private const HEADER_WRAP_WIDTH = self::TEXT_LEFT + self::TEXT_WRAP_WIDTH - self::HEADER_TEXT_LEFT;

    // Размер подбирается по фактической высоте текста: максимальный, при котором отзыв
    // помещается. Шаг дробный, чтобы текст доходил до низа колонки и над логотипом
    // не оставалось пустоты; крупнее шрифта имени (NAME_FONT_SIZE) не делаем.
    private const TEXT_FONT_SIZE_MAX = self::NAME_FONT_SIZE;
    private const TEXT_FONT_SIZE_MIN = 52;
    private const TEXT_FONT_SIZE_STEP = 0.5;

    // Страховочный потолок перед точной подгонкой: fitText() сам укоротит текст до заполнения места
    private const MAX_TEXT_LENGTH = 3000;

    public function __construct(private EmojiTextRenderer $renderer) {}

    public function generate(Review $review): string
    {
        $card = Image::read(resource_path('images/serdal-story-bg.png'));

        $card->place($this->circularAvatar($review->user?->avatar), 'top-left', self::TEXT_LEFT, self::AVATAR_TOP);

        $name = $review->user?->name ?? 'Ученик';
        $nameStyle = $this->fitHeader(new TextStyle(
            fontPath: resource_path('fonts/Inter-SemiBold.ttf'),
            size: self::NAME_FONT_SIZE,
            color: self::TEXT_COLOR,
            lineHeight: self::HEADER_LINE_HEIGHT,
            wrapWidth: self::HEADER_WRAP_WIDTH,
        ), $name);

        $this->renderer->draw($card, $name, self::HEADER_TEXT_LEFT, self::NAME_TOP, $nameStyle);

        $headerBottom = self::NAME_TOP + $this->renderer->height($name, $nameStyle);

        if ($review->teacher?->name) {
            $subtitle = 'Репетитор: ' . $review->teacher->name;
            $subtitleStyle = $this->fitHeader(new TextStyle(
                fontPath: resource_path('fonts/Inter-Regular.ttf'),
                size: self::SUBTITLE_FONT_SIZE,
                color: self::TEXT_COLOR,
                lineHeight: self::HEADER_LINE_HEIGHT,
                wrapWidth: self::HEADER_WRAP_WIDTH,
            ), $subtitle);

            $subtitleTop = $headerBottom + self::NAME_SUBTITLE_GAP;

            $this->renderer->draw($card, $subtitle, self::HEADER_TEXT_LEFT, $subtitleTop, $subtitleStyle);

            $headerBottom = $subtitleTop + $this->renderer->height($subtitle, $subtitleStyle);
        }

        $headerBottom = max($headerBottom, self::AVATAR_TOP + self::AVATAR_SIZE);
        $dividerY = $headerBottom + self::DIVIDER_GAP;
        $textTop = $dividerY + self::TEXT_GAP;

        $card->drawLine(function (\Intervention\Image\Geometry\Factories\LineFactory $line) use ($dividerY) {
            $line->from(self::TEXT_LEFT, $dividerY);
            $line->to(self::TEXT_LEFT + self::TEXT_WRAP_WIDTH, $dividerY);
            $line->color(self::TEXT_COLOR);
            $line->width(7);
        });

        $text = $this->prepareText($review->text);
        [$text, $textStyle] = $this->fitText($text, self::TEXT_BOTTOM - $textTop);
        $textTop += $this->bottomAlignShift($text, $textStyle, self::TEXT_BOTTOM - $textTop);

        $this->renderer->draw($card, $text, self::TEXT_LEFT, $textTop, $textStyle);

        return $card->scale(width: self::OUTPUT_WIDTH)->toJpeg(quality: 90)->toString();
    }

    private function prepareText(string $text): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text));

        if (mb_strlen($text) > self::MAX_TEXT_LENGTH) {
            $text = $this->truncate($text, self::MAX_TEXT_LENGTH);
        }

        return $text;
    }

    /**
     * Подбирает максимальный размер шрифта, при котором текст помещается по высоте.
     * Если не влезает даже минимальным — укорачивает текст с многоточием.
     *
     * @return array{string, TextStyle}
     */
    private function fitText(string $text, int $availableHeight): array
    {
        $style = new TextStyle(
            fontPath: resource_path('fonts/Inter-Regular.ttf'),
            size: self::TEXT_FONT_SIZE_MIN,
            color: self::TEXT_COLOR,
            lineHeight: self::TEXT_LINE_HEIGHT,
            wrapWidth: self::TEXT_WRAP_WIDTH,
        );

        // Высота растёт вместе с кеглем, поэтому ищем границу делением пополам.
        // Кегль дробный: так текст добирает колонку до низа, а не оставляет
        // пустую полосу над логотипом из-за округления до целых пикселей.
        $low = (float) self::TEXT_FONT_SIZE_MIN;
        $high = (float) self::TEXT_FONT_SIZE_MAX;
        $fitting = null;

        while ($high - $low > self::TEXT_FONT_SIZE_STEP) {
            $size = ($low + $high) / 2;

            if ($this->renderer->inkHeight($text, $style->withSize($size)) <= $availableHeight) {
                $fitting = $size;
                $low = $size;
            } else {
                $high = $size;
            }
        }

        if ($fitting !== null) {
            return [$text, $this->fillRemainder($text, $style->withSize($fitting), $availableHeight)];
        }

        // Не влезает даже минимальным кеглем — укорачиваем до заполнения колонки
        $height = $this->renderer->inkHeight($text, $style);
        while ($height > $availableHeight && mb_strlen($text) > 100) {
            $keep = max(100, (int) floor(mb_strlen($text) * $availableHeight / $height) - 10);
            $text = $this->truncate($text, $keep);
            $height = $this->renderer->inkHeight($text, $style);
        }

        return [$text, $this->fillRemainder($text, $style, $availableHeight)];
    }

    /**
     * Кегль дискретен по числу строк: следующий уже не влезает, а текущий не
     * достаёт до низа колонки. Остаток добираем интерлиньяжем — переносы строк
     * от него не зависят. Растягиваем не больше TEXT_LINE_HEIGHT_MAX, иначе
     * короткие отзывы расползлись бы строчками по всей карточке.
     */
    private function fillRemainder(string $text, TextStyle $style, int $availableHeight): TextStyle
    {
        $lines = $this->renderer->lineCount($text, $style);
        $inkHeight = $this->renderer->inkHeight($text, $style);

        if ($lines < 2 || $inkHeight >= $availableHeight) {
            return $style;
        }

        $leading = $this->renderer->leading($style);
        $filled = $style;

        // Интерлиньяж округляется до целых пикселей, поэтому берём не расчётное
        // значение, а последнее, при котором блок ещё не вылезает за низ колонки
        for ($lineHeight = $style->lineHeight; $lineHeight <= self::TEXT_LINE_HEIGHT_MAX; $lineHeight += 0.005) {
            $candidate = $style->withLineHeight($lineHeight);

            if ($inkHeight + ($lines - 1) * ($this->renderer->leading($candidate) - $leading) > $availableHeight) {
                break;
            }

            $filled = $candidate;
        }

        return $filled;
    }

    /**
     * Остаток после подгонки кегля и интерлиньяжа — меньше одной строки.
     * Сдвигаем блок на него вниз, чтобы низ текста сел ровно на TEXT_BOTTOM;
     * лишнее уходит в отступ под линией. Короткий отзыв, которому до низа
     * далеко больше строки, не тащим — он остаётся под линией как есть.
     */
    private function bottomAlignShift(string $text, TextStyle $style, int $availableHeight): int
    {
        $slack = $availableHeight - $this->renderer->inkHeight($text, $style);

        return $slack > 0 && $slack < $this->renderer->leading($style) ? $slack : 0;
    }

    /**
     * Обрезает текст с многоточием, не разрывая эмодзи-последовательности
     * (склейки через ZWJ, селекторы вариаций, тона кожи).
     */
    private function truncate(string $text, int $length): string
    {
        $text = mb_substr($text, 0, $length);
        $text = preg_replace('/[\x{200D}\x{FE0E}\x{FE0F}\x{1F3FB}-\x{1F3FF}]+$/u', '', $text);
        $text = preg_replace('/[\s.,!?;:…]+$/u', '', $text);

        return $text . '…';
    }

    /**
     * GD переносит текст только по пробелам: слово шире колонки вылезет за край.
     * Уменьшаем размер шрифта так, чтобы самое длинное слово помещалось.
     */
    private function fitHeader(TextStyle $style, string $text): TextStyle
    {
        $widest = $this->renderer->widestWord($text, $style);

        if ($widest <= self::HEADER_WRAP_WIDTH) {
            return $style;
        }

        return $style->withSize(max(40, (int) floor($style->size * self::HEADER_WRAP_WIDTH / $widest)));
    }

    private function circularAvatar(?string $avatarPath): ImageInterface
    {
        $bytes = null;

        if ($avatarPath) {
            try {
                $bytes = Storage::disk('s3')->get($avatarPath);
            } catch (\Throwable) {
                $bytes = null;
            }
        }

        if ($bytes === null) {
            $bytes = file_get_contents(resource_path('images/share-avatar-placeholder.png'));
        }

        // Рисуем круг в двойном размере и уменьшаем, чтобы сгладить края
        $size = self::AVATAR_SIZE;
        $supersampled = $size * 2;

        $source = Image::read($bytes)->cover($supersampled, $supersampled)->core()->native();

        $circle = imagecreatetruecolor($supersampled, $supersampled);
        imagealphablending($circle, false);
        imagesavealpha($circle, true);
        imagefill($circle, 0, 0, imagecolorallocatealpha($circle, 0, 0, 0, 127));

        $radius = $supersampled / 2;
        for ($x = 0; $x < $supersampled; $x++) {
            for ($y = 0; $y < $supersampled; $y++) {
                $dx = $x - $radius + 0.5;
                $dy = $y - $radius + 0.5;
                if ($dx * $dx + $dy * $dy <= $radius * $radius) {
                    imagesetpixel($circle, $x, $y, imagecolorat($source, $x, $y));
                }
            }
        }

        $final = imagecreatetruecolor($size, $size);
        imagealphablending($final, false);
        imagesavealpha($final, true);
        imagefill($final, 0, 0, imagecolorallocatealpha($final, 0, 0, 0, 127));
        imagecopyresampled($final, $circle, 0, 0, 0, 0, $size, $size, $supersampled, $supersampled);

        ob_start();
        imagepng($final);

        return Image::read(ob_get_clean());
    }
}
