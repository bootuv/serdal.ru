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

    // Шапка одной колонкой по центру: аватарка сверху, под ней имя
    private const AVATAR_SIZE = 310;
    private const AVATAR_TOP = 185;
    private const NAME_TOP = 570;
    private const NAME_WRAP_WIDTH = 1800;

    // Группа «линия + отзыв» подтянута вверх к имени;
    // если имя переносится на вторую строку, группа сдвигается вниз на NAME_LINE_HEIGHT
    private const NAME_FONT_SIZE = 96;
    private const NAME_LINE_HEIGHT = 125;
    private const DIVIDER_Y = 820;
    private const TEXT_TOP = 910;
    private const TEXT_LEFT = 192;
    private const TEXT_WRAP_WIDTH = 1776;
    private const TEXT_LINE_HEIGHT = 1.6;
    private const TEXT_BOTTOM = 3470; // верх логотипа минус отступ

    // Размер подбирается по фактической высоте текста: максимальный, при котором отзыв
    // помещается; не крупнее шрифта имени (NAME_FONT_SIZE)
    private const TEXT_FONT_SIZES = [96, 88, 80, 72, 64, 58, 52];

    // Страховочный потолок перед точной подгонкой: fitText() сам укоротит текст до заполнения места
    private const MAX_TEXT_LENGTH = 3000;

    public function generate(Review $review): string
    {
        $card = Image::read(resource_path('images/serdal-story-bg.png'));

        $card->place($this->circularAvatar($review->user?->avatar), 'top', 0, self::AVATAR_TOP);

        $name = $review->user?->name ?? 'Ученик';
        $card->text($name, $card->width() / 2, self::NAME_TOP, function (FontFactory $font) {
            $font->filename(resource_path('fonts/Inter-SemiBold.ttf'));
            $font->size(self::NAME_FONT_SIZE);
            $font->color(self::TEXT_COLOR);
            $font->align('center');
            $font->valign('top');
            $font->lineHeight(1.3);
            $font->wrap(self::NAME_WRAP_WIDTH);
        });

        $headerShift = $this->nameIsMultiline($name) ? self::NAME_LINE_HEIGHT : 0;

        $card->drawLine(function (\Intervention\Image\Geometry\Factories\LineFactory $line) use ($headerShift) {
            $line->from(self::TEXT_LEFT, self::DIVIDER_Y + $headerShift);
            $line->to(self::TEXT_LEFT + self::TEXT_WRAP_WIDTH, self::DIVIDER_Y + $headerShift);
            $line->color(self::TEXT_COLOR);
            $line->width(6);
        });

        $text = $this->prepareText($review->text);
        [$text, $fontSize] = $this->fitText($text, self::TEXT_BOTTOM - self::TEXT_TOP - $headerShift);

        $card->text($text, self::TEXT_LEFT, self::TEXT_TOP + $headerShift, function (FontFactory $font) use ($fontSize) {
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

    private function textHeight(string $text, int $fontSize): int
    {
        // Меряем тем же процессором, что рисует текст: совпадают и перенос строк, и интерлиньяж
        $font = new \Intervention\Image\Typography\Font(resource_path('fonts/Inter-Regular.ttf'));
        $font->setSize($fontSize);
        $font->setLineHeight(self::TEXT_LINE_HEIGHT);
        $font->setWrapWidth(self::TEXT_WRAP_WIDTH);

        $processor = new \Intervention\Image\Drivers\Gd\FontProcessor();
        $lines = $processor->textBlock($text, $font, new \Intervention\Image\Geometry\Point(0, 0))->count();

        return $lines * $processor->leading($font);
    }

    private function nameIsMultiline(string $name): bool
    {
        // GD принимает размер шрифта в пунктах (px * 0.75)
        $box = imagettfbbox(self::NAME_FONT_SIZE * 0.75, 0, resource_path('fonts/Inter-SemiBold.ttf'), $name);

        return abs($box[4] - $box[0]) > self::NAME_WRAP_WIDTH;
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
