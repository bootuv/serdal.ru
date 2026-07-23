<?php

namespace App\Services;

use App\Models\Review;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Interfaces\ImageInterface;
use Intervention\Image\Laravel\Facades\Image;
use Intervention\Image\Typography\FontFactory;

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

    // Высота шапки считается от фактических переносов имени и подписи;
    // линия и текст идут после самого нижнего элемента (аватар или подпись)
    private const DIVIDER_GAP = 130;  // низ шапки -> линия
    private const TEXT_GAP = 160;     // линия -> текст отзыва

    private const TEXT_LEFT = 192;
    private const TEXT_WRAP_WIDTH = 1776;
    private const TEXT_LINE_HEIGHT = 1.6;
    private const TEXT_BOTTOM = 3450; // верх логотипа (y=3538) минус отступ

    private const HEADER_TEXT_LEFT = self::TEXT_LEFT + self::AVATAR_SIZE + 72;
    private const HEADER_WRAP_WIDTH = self::TEXT_LEFT + self::TEXT_WRAP_WIDTH - self::HEADER_TEXT_LEFT;

    // Размер подбирается по фактической высоте текста: максимальный, при котором отзыв
    // помещается; не крупнее шрифта имени (NAME_FONT_SIZE)
    private const TEXT_FONT_SIZES = [96, 88, 80, 72, 64, 58, 52];

    // Страховочный потолок перед точной подгонкой: fitText() сам укоротит текст до заполнения места
    private const MAX_TEXT_LENGTH = 3000;

    public function generate(Review $review): string
    {
        $card = Image::read(resource_path('images/serdal-story-bg.png'));

        $card->place($this->circularAvatar($review->user?->avatar), 'top-left', self::TEXT_LEFT, self::AVATAR_TOP);

        $name = $review->user?->name ?? 'Ученик';
        $nameFontSize = $this->headerFontSize($name, self::NAME_FONT_SIZE, 'fonts/Inter-SemiBold.ttf');
        $card->text($name, self::HEADER_TEXT_LEFT, self::NAME_TOP, function (FontFactory $font) use ($nameFontSize) {
            $font->filename(resource_path('fonts/Inter-SemiBold.ttf'));
            $font->size($nameFontSize);
            $font->color(self::TEXT_COLOR);
            $font->align('left');
            $font->valign('top');
            $font->lineHeight(1.3);
            $font->wrap(self::HEADER_WRAP_WIDTH);
        });

        $headerBottom = self::NAME_TOP + $this->textHeight(
            $name, $nameFontSize, 'fonts/Inter-SemiBold.ttf', self::HEADER_WRAP_WIDTH, 1.3
        );

        if ($review->teacher?->name) {
            $subtitle = 'Репетитор: ' . $review->teacher->name;
            $subtitleFontSize = $this->headerFontSize($subtitle, self::SUBTITLE_FONT_SIZE, 'fonts/Inter-Regular.ttf');
            $subtitleTop = $headerBottom + self::NAME_SUBTITLE_GAP;

            $card->text($subtitle, self::HEADER_TEXT_LEFT, $subtitleTop, function (FontFactory $font) use ($subtitleFontSize) {
                $font->filename(resource_path('fonts/Inter-Regular.ttf'));
                $font->size($subtitleFontSize);
                $font->color(self::TEXT_COLOR);
                $font->align('left');
                $font->valign('top');
                $font->lineHeight(1.3);
                $font->wrap(self::HEADER_WRAP_WIDTH);
            });

            $headerBottom = $subtitleTop + $this->textHeight(
                $subtitle, $subtitleFontSize, 'fonts/Inter-Regular.ttf', self::HEADER_WRAP_WIDTH, 1.3
            );
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
        [$text, $fontSize] = $this->fitText($text, self::TEXT_BOTTOM - $textTop);

        $card->text($text, self::TEXT_LEFT, $textTop, function (FontFactory $font) use ($fontSize) {
            $font->filename(resource_path('fonts/Inter-Regular.ttf'));
            $font->size($fontSize);
            $font->color(self::TEXT_COLOR);
            $font->align('left');
            $font->valign('top');
            $font->lineHeight(self::TEXT_LINE_HEIGHT);
            $font->wrap(self::TEXT_WRAP_WIDTH);
        });

        return $card->scale(width: self::OUTPUT_WIDTH)->toJpeg(quality: 90)->toString();
    }

    private function prepareText(string $text): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text));

        // GD не умеет рисовать эмодзи — убираем их, чтобы не было пустых квадратов
        $text = preg_replace('/[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}\x{FE00}-\x{FE0F}\x{2B00}-\x{2BFF}\x{200D}]/u', '', $text);
        $text = trim(preg_replace('/ {2,}/', ' ', $text));

        if (mb_strlen($text) > self::MAX_TEXT_LENGTH) {
            $text = rtrim(mb_substr($text, 0, self::MAX_TEXT_LENGTH), " \t.,!?;:") . '…';
        }

        return $text;
    }

    /**
     * Подбирает максимальный размер шрифта, при котором текст помещается по высоте.
     * Если не влезает даже минимальным — укорачивает текст с многоточием.
     *
     * @return array{string, int}
     */
    private function fitText(string $text, int $availableHeight): array
    {
        $length = mb_strlen($text);

        foreach (self::TEXT_FONT_SIZES as $size) {
            // Грубая нижняя оценка высоты (заведомо оптимистичная по ширине символа),
            // чтобы не делать дорогой точный замер для заведомо не влезающих размеров
            $optimisticLines = (int) ceil($length / (self::TEXT_WRAP_WIDTH / ($size * 0.4)));
            if ($optimisticLines * $this->leadingFor($size) > $availableHeight) {
                continue;
            }

            if ($this->textHeight($text, $size) <= $availableHeight) {
                return [$text, $size];
            }
        }

        $sizes = self::TEXT_FONT_SIZES;
        $minSize = end($sizes);

        $height = $this->textHeight($text, $minSize);
        while ($height > $availableHeight && mb_strlen($text) > 100) {
            $keep = max(100, (int) floor(mb_strlen($text) * $availableHeight / $height) - 10);
            $text = rtrim(mb_substr($text, 0, $keep), " \t.,!?;:…") . '…';
            $height = $this->textHeight($text, $minSize);
        }

        return [$text, $minSize];
    }

    private function leadingFor(int $fontSize): int
    {
        // Intervention считает интерлиньяж от размера в пунктах (px * 0.75)
        return (int) round($fontSize * 0.75 * self::TEXT_LINE_HEIGHT);
    }

    /**
     * GD переносит текст только по пробелам: слово шире колонки вылезет за край.
     * Уменьшаем размер шрифта так, чтобы самое длинное слово помещалось.
     */
    private function headerFontSize(string $text, int $baseSize, string $fontPath): int
    {
        $widest = 0;
        foreach (preg_split('/\s+/u', $text) as $word) {
            // GD принимает размер шрифта в пунктах (px * 0.75)
            $box = imagettfbbox($baseSize * 0.75, 0, resource_path($fontPath), $word);
            $widest = max($widest, abs($box[4] - $box[0]));
        }

        if ($widest <= self::HEADER_WRAP_WIDTH) {
            return $baseSize;
        }

        return max(40, (int) floor($baseSize * self::HEADER_WRAP_WIDTH / $widest));
    }

    private function textHeight(
        string $text,
        int $fontSize,
        string $fontPath = 'fonts/Inter-Regular.ttf',
        int $wrapWidth = self::TEXT_WRAP_WIDTH,
        float $lineHeight = self::TEXT_LINE_HEIGHT,
    ): int {
        // Меряем тем же процессором, что рисует текст: совпадают и перенос строк, и интерлиньяж
        $font = new \Intervention\Image\Typography\Font(resource_path($fontPath));
        $font->setSize($fontSize);
        $font->setLineHeight($lineHeight);
        $font->setWrapWidth($wrapWidth);

        $processor = new \Intervention\Image\Drivers\Gd\FontProcessor();
        $lines = $processor->textBlock($text, $font, new \Intervention\Image\Geometry\Point(0, 0))->count();

        return $lines * $processor->leading($font);
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
