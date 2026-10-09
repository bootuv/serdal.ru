<?php

namespace App\Services\ShareCard;

use Intervention\Image\Interfaces\ImageInterface;
use Intervention\Image\Laravel\Facades\Image;

/** Круглая картинка с прозрачными углами (фото автора на карточках для соцсетей). */
class CircleImage
{
    public static function make(string $bytes, int $size): ImageInterface
    {
        // Рисуем круг в двойном размере и уменьшаем, чтобы сгладить края
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
