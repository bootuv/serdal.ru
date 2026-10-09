<?php

namespace App\Services;

use App\Models\BlogPost;
use App\Services\ShareCard\CircleImage;
use App\Services\ShareCard\EmojiTextRenderer;
use App\Services\ShareCard\TextStyle;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Interfaces\ImageInterface;
use Intervention\Image\Laravel\Facades\Image;

/**
 * Картинка статьи для соцсетей и мессенджеров (og:image, 1200×630): логотип, темы, заголовок, фото и имя автора.
 * С обложкой — обложка на фоне под темным градиентом, без нее — темный фон с двумя значками Serdal (звезда из фавикона): крупный бирюзовый сверху, маленький желтый снизу.
 * Собирается при первом запросе и лежит на локальном диске, пока не поменяются заголовок, автор, темы или обложка
 * (версия — в адресе, иначе соцсети показывали бы старую картинку).
 */
class BlogShareImage
{
    public const WIDTH = 1200;
    public const HEIGHT = 630;

    /** Поменял оформление — подними версию: картинки всех статей соберутся заново. */
    private const DESIGN = 5;

    private const DISK = 'local';
    private const DIR = 'blog-og';

    private const PAD = 64;
    private const DARK = [32, 35, 35];        // #202323 — как фон og-default.png
    private const YELLOW = 'ffe500';

    // Значки без обложки: [центр x, центр y, размер, цвет] — крупный бирюзовый выглядывает из верхнего угла, маленький
    // желтый снизу; до заголовка и подписи автора не доходят. Цвета — только фирменные (BRAND.md и переменные сайта: бирюзовый — --brand-secondary)
    private const STARS = [[1130, 80, 700, [146, 247, 237]], [1010, 550, 300, [255, 229, 0]]];
    private const FAINT = 'b5b8b8';             // faint — подпись под именем

    private const AVATAR = 72;
    private const TITLE_MAX = 64;
    private const TITLE_MIN = 40;
    private const TITLE_LINES = 4;
    private const TITLE_LINE_HEIGHT = 1.18;

    public function __construct(private EmojiTextRenderer $renderer) {}

    /** Версия картинки: меняется вместе со всем, что на ней нарисовано. */
    public function version(BlogPost $post): string
    {
        return substr(md5(implode('|', [
            self::DESIGN, $post->id, $post->title, $post->cover_url, $post->author?->name, $post->author?->avatar,
            $post->tags->pluck('name')->implode(','),
        ])), 0, 12);
    }

    public function url(BlogPost $post): string
    {
        return \App\Support\Seo::url(route('blog.og', ['slug' => $post->slug, 'v' => $this->version($post)], false));
    }

    /** JPEG картинки: из готового файла или собранный сейчас. Старые версии этой статьи удаляются. */
    public function jpeg(BlogPost $post): string
    {
        $disk = Storage::disk(self::DISK);
        $path = self::DIR . '/' . $post->id . '-' . $this->version($post) . '.jpg';

        if ($disk->exists($path)) {
            return $disk->get($path);
        }

        $jpeg = $this->render($post);

        foreach ($disk->files(self::DIR) as $old) {
            if (str_starts_with(basename($old), $post->id . '-')) {
                $disk->delete($old);
            }
        }
        $disk->put($path, $jpeg);

        return $jpeg;
    }

    public function render(BlogPost $post): string
    {
        $cover = $this->coverBytes($post);
        $card = $cover !== null ? $this->coverBackground($cover) : $this->plainBackground();

        $left = self::PAD;
        // На темном фоне справа значок — заголовок до него не доходит
        $width = $cover !== null ? self::WIDTH - 2 * self::PAD : 760;

        $this->placeLogo($card);

        // Снизу вверх: подпись автора, над ней заголовок, над заголовком темы
        $avatarTop = self::HEIGHT - self::PAD - self::AVATAR;
        $card->place($this->avatar($post), 'top-left', $left, $avatarTop);

        $name = $this->fitLine($post->authorName(), new TextStyle(resource_path('fonts/Inter-SemiBold.ttf'), 30, 'ffffff', 1.2), $width - self::AVATAR - 24);
        $site = new TextStyle(resource_path('fonts/Inter-Regular.ttf'), 22, 'ffffff', 1.2);
        $nameStyle = new TextStyle(resource_path('fonts/Inter-SemiBold.ttf'), 30, 'ffffff', 1.2);
        $textLeft = $left + self::AVATAR + 20;
        $this->renderer->draw($card, $name, $textLeft, $avatarTop + 6, $nameStyle);
        $this->renderer->draw($card, 'Блог Serdal · serdal.ru', $textLeft, $avatarTop + 44, new TextStyle($site->fontPath, $site->size, self::FAINT, 1.2));

        [$title, $titleStyle] = $this->fitTitle($post->title, $width);
        $titleBottom = $avatarTop - 36;
        $titleTop = $titleBottom - $this->renderer->inkHeight($title, $titleStyle);
        $this->renderer->draw($card, $title, $left, $titleTop, $titleStyle);

        $tags = $this->tagsLine($post, $width);
        if ($tags !== '') {
            $tagStyle = new TextStyle(resource_path('fonts/Inter-SemiBold.ttf'), 24, self::YELLOW, 1.2);
            $this->renderer->draw($card, $tags, $left, $titleTop - 24 - $this->renderer->inkHeight($tags, $tagStyle), $tagStyle);
        }

        return $card->toJpeg(quality: 88)->toString();
    }

    /** Обложка на всю картинку: чуть притемнена целиком, сверху — под логотип, снизу — сильно, под текст. */
    private function coverBackground(string $bytes): ImageInterface
    {
        $card = Image::read($bytes)->cover(self::WIDTH, self::HEIGHT);
        $gd = $card->core()->native();
        imagealphablending($gd, true);

        for ($y = 0; $y < self::HEIGHT; $y++) {
            $top = 0.55 * max(0, 1 - $y / 220);                         // под логотипом
            $t = min(1, max(0, ($y - 100) / 300));                       // с 100 до 400 px — плавно в почти черный
            $bottom = 0.72 * $t * $t * (3 - 2 * $t);
            $alpha = min(0.94, 0.22 + $top + $bottom);
            imageline($gd, 0, $y, self::WIDTH - 1, $y, imagecolorallocatealpha($gd, ...[...self::DARK, 127 - (int) round($alpha * 127)]));
        }

        return $card;
    }

    /** Без обложки — темный фон и справа два значка Serdal — бирюзовый и желтый (звезда из фавикона), выглядывают из-за краев. */
    private function plainBackground(): ImageInterface
    {
        $scale = 2; // рисуем в двойном размере, чтобы края были гладкими
        $big = imagecreatetruecolor(self::WIDTH * $scale, self::HEIGHT * $scale);
        imagefill($big, 0, 0, imagecolorallocate($big, ...self::DARK));

        foreach (self::STARS as [$cx, $cy, $size, $color]) {
            $points = [];
            foreach (self::starOutline() as [$x, $y]) {
                $points[] = (int) round(($cx + ($x / 32 - 0.5) * $size) * $scale);
                $points[] = (int) round(($cy + ($y / 32 - 0.5) * $size) * $scale);
            }
            imagefilledpolygon($big, $points, imagecolorallocate($big, ...$color));
        }

        $gd = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        imagecopyresampled($gd, $big, 0, 0, 0, 0, self::WIDTH, self::HEIGHT, self::WIDTH * $scale, self::HEIGHT * $scale);

        ob_start();
        imagepng($gd);

        return Image::read(ob_get_clean());
    }

    /** Контур звезды из public/images/favicon.svg (поле 32×32): кривые Безье разбиты на точки. */
    private static function starOutline(): array
    {
        $svg = file_get_contents(public_path('images/favicon.svg'));
        preg_match('/<path d="([^"]+)"/', $svg, $m);
        preg_match_all('/[MCZ]|-?\d*\.?\d+/i', $m[1], $tokens);

        $points = [];
        $current = [0.0, 0.0];
        $command = null;
        $numbers = [];
        foreach ($tokens[0] as $token) {
            if (ctype_alpha($token)) {
                $command = strtoupper($token);
                $numbers = [];

                continue;
            }
            $numbers[] = (float) $token;
            if ($command === 'M' && count($numbers) === 2) {
                $current = $numbers;
                $points[] = $current;
                $numbers = [];
            } elseif ($command === 'C' && count($numbers) === 6) {
                [$x1, $y1, $x2, $y2, $x, $y] = $numbers;
                for ($i = 1; $i <= 24; $i++) {
                    $t = $i / 24;
                    $u = 1 - $t;
                    $points[] = [
                        $u ** 3 * $current[0] + 3 * $u * $u * $t * $x1 + 3 * $u * $t * $t * $x2 + $t ** 3 * $x,
                        $u ** 3 * $current[1] + 3 * $u * $u * $t * $y1 + 3 * $u * $t * $t * $y2 + $t ** 3 * $y,
                    ];
                }
                $current = [$x, $y];
                $numbers = [];
            }
        }

        return $points;
    }

    /** Логотип в левом верхнем углу — белый (исходный черный инвертируем). */
    private function placeLogo(ImageInterface $card): void
    {
        $logo = Image::read(public_path('images/logo.png'));
        imagefilter($logo->core()->native(), IMG_FILTER_NEGATE);
        imagefilter($logo->core()->native(), IMG_FILTER_BRIGHTNESS, 255);
        $card->place($logo->scale(height: 40), 'top-left', self::PAD, 52);
    }

    private function avatar(BlogPost $post): ImageInterface
    {
        $author = $post->author;
        $bytes = null;

        if ($author?->avatar) {
            try {
                $bytes = Storage::disk('s3')->get($author->avatar);
            } catch (\Throwable) {
                $bytes = null;
            }
        }
        if ($bytes === null && ! $author) {
            $bytes = file_get_contents(public_path('images/webclip.png'));
        }
        if ($bytes === null) {
            return $this->initials($author->name);
        }

        try {
            return CircleImage::make($bytes, self::AVATAR);
        } catch (\Throwable) {
            return $this->initials($author?->name ?? 'Serdal');
        }
    }

    /** Нет фото — инициалы на желтом круге, как на сайте. */
    private function initials(string $name): ImageInterface
    {
        $parts = preg_split('/\s+/u', trim($name));
        $text = mb_strtoupper(mb_substr($parts[0] ?? '', 0, 1) . mb_substr($parts[1] ?? '', 0, 1));

        $square = Image::create(self::AVATAR * 2, self::AVATAR * 2)->fill(self::YELLOW);
        $square->text($text, self::AVATAR, self::AVATAR, function ($font) {
            $font->filename(resource_path('fonts/Inter-SemiBold.ttf'));
            $font->size(56);
            $font->color('202323');
            $font->align('center');
            $font->valign('middle');
        });

        return CircleImage::make($square->toPng()->toString(), self::AVATAR);
    }

    /**
     * Самый крупный кегль, при котором заголовок занимает не больше TITLE_LINES строк и длинное слово не вылезает за край.
     * Не влезает и самым мелким — укорачиваем с многоточием.
     *
     * @return array{string, TextStyle}
     */
    private function fitTitle(string $title, int $width): array
    {
        $title = trim(preg_replace('/\s+/u', ' ', $title));
        $style = new TextStyle(resource_path('fonts/Inter-SemiBold.ttf'), self::TITLE_MAX, 'ffffff', self::TITLE_LINE_HEIGHT, $width);

        for ($size = self::TITLE_MAX; $size >= self::TITLE_MIN; $size -= 2) {
            $candidate = $style->withSize($size);
            if ($this->renderer->widestWord($title, $candidate) <= $width && $this->renderer->lineCount($title, $candidate) <= self::TITLE_LINES) {
                return [$title, $candidate];
            }
        }

        $style = $style->withSize(self::TITLE_MIN);
        if ($this->renderer->widestWord($title, $style) > $width) {
            $style = $style->withSize(max(24, (int) floor(self::TITLE_MIN * $width / $this->renderer->widestWord($title, $style))));
        }
        $words = preg_split('/\s+/u', $title);
        while (count($words) > 1 && $this->renderer->lineCount(implode(' ', $words) . '…', $style) > self::TITLE_LINES) {
            array_pop($words);
        }

        return [rtrim(implode(' ', $words), ' .,:;—-') . '…', $style];
    }

    /** Темы статьи через точку — сколько поместится в одну строку. */
    private function tagsLine(BlogPost $post, int $width): string
    {
        $style = new TextStyle(resource_path('fonts/Inter-SemiBold.ttf'), 24, self::YELLOW, 1.2, $width);
        $line = '';

        foreach ($post->tags->pluck('name') as $name) {
            $next = $line === '' ? $name : $line . ' · ' . $name;
            if ($this->renderer->lineCount($next, $style) > 1 || $this->renderer->widestWord($next, $style) > $width) {
                break;
            }
            $line = $next;
        }

        return $line;
    }

    /** Строка в одну линию: не помещается — укорачиваем с многоточием. */
    private function fitLine(string $text, TextStyle $style, int $width): string
    {
        $style = new TextStyle($style->fontPath, $style->size, $style->color, $style->lineHeight, $width);
        $fits = fn (string $s) => $this->renderer->lineCount($s, $style) === 1 && $this->renderer->widestWord($s, $style) <= $width;

        if ($fits($text)) {
            return $text;
        }
        while (mb_strlen($text) > 1 && ! $fits($text . '…')) {
            $text = mb_substr($text, 0, -1);
        }

        return rtrim($text) . '…';
    }

    /** Обложка: с нашего хранилища по пути из адреса, иначе по адресу. Не скачалась — картинка без обложки. */
    private function coverBytes(BlogPost $post): ?string
    {
        if (! $post->cover_url) {
            return null;
        }

        try {
            $path = app(EditorMediaService::class)->pathFromUrl($post->cover_url);
            $bytes = $path ? Storage::disk(HelpCenterService::DISK)->get($path) : null;
        } catch (\Throwable) {
            $bytes = null;
        }

        try {
            $bytes ??= Http::timeout(8)->get($post->cover_url)->throw()->body();
            Image::read($bytes); // проверка, что это картинка

            return $bytes;
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }
}
