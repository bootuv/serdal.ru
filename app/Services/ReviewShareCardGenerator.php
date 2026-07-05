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

    // Шапка: аватарка слева, имя справа от неё, под ними разделительная линия
    private const AVATAR_SIZE = 310;
    private const AVATAR_TOP = 185;
    private const AVATAR_LEFT = 185;
    private const NAME_LEFT = 585;
    private const NAME_WRAP_WIDTH = 1390;
    private const DIVIDER_Y = 620;

    // Текст отзыва центрируется между линией и логотипом внизу шаблона
    private const LOGO_TOP = 3560;
    private const TEXT_LEFT = 192;
    private const TEXT_WRAP_WIDTH = 1776;
    private const MAX_TEXT_LENGTH = 1000;

    public function generate(Review $review): string
    {
        $card = Image::read(resource_path('images/serdal-story-bg.png'));

        $card->place($this->circularAvatar($review->user?->avatar), 'top-left', self::AVATAR_LEFT, self::AVATAR_TOP);

        $nameCenterY = self::AVATAR_TOP + intdiv(self::AVATAR_SIZE, 2);
        $card->text($review->user?->name ?? 'Ученик', self::NAME_LEFT, $nameCenterY, function (FontFactory $font) {
            $font->filename(resource_path('fonts/Inter-SemiBold.ttf'));
            $font->size(96);
            $font->color(self::TEXT_COLOR);
            $font->align('left');
            $font->valign('middle');
            $font->lineHeight(1.3);
            $font->wrap(self::NAME_WRAP_WIDTH);
        });

        $card->drawLine(function (\Intervention\Image\Geometry\Factories\LineFactory $line) use ($card) {
            $line->from(0, self::DIVIDER_Y);
            $line->to($card->width(), self::DIVIDER_Y);
            $line->color(self::TEXT_COLOR);
            $line->width(6);
        });

        $text = $this->prepareText($review->text);
        $fontSize = $this->fontSizeFor($text);
        $textCenterY = intdiv(self::DIVIDER_Y + self::LOGO_TOP, 2);

        $card->text($text, self::TEXT_LEFT, $textCenterY, function (FontFactory $font) use ($fontSize) {
            $font->filename(resource_path('fonts/Inter-Regular.ttf'));
            $font->size($fontSize);
            $font->color(self::TEXT_COLOR);
            $font->align('left');
            $font->valign('middle');
            $font->lineHeight(1.6);
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

    private function fontSizeFor(string $text): int
    {
        $length = mb_strlen($text);

        return match (true) {
            $length <= 200 => 104,
            $length <= 400 => 96,
            $length <= 600 => 88,
            $length <= 800 => 80,
            default => 72,
        };
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
