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
 * Картинки статьи для соцсетей: OG — превью ссылки (og:image, 1200×630) и STORY — вертикальная для сторис (1080×1920,
 * «Поделиться» на странице статьи). На обеих логотип, темы, заголовок, фото и имя автора.
 * С обложкой — обложка на фоне под темным градиентом, без нее — темный фон с двумя значками Serdal (звезда из фавикона):
 * крупный бирюзовый сверху, маленький желтый снизу. Цвета — только фирменные (BRAND.md и переменные сайта).
 * Собирается при первом запросе и лежит на локальном диске, пока не поменяются заголовок, автор, темы или обложка
 * (версия — в адресе, иначе соцсети показывали бы старую картинку).
 */
class BlogShareImage
{
    public const OG = 'og';
    public const STORY = 'story';

    public const WIDTH = 1200;
    public const HEIGHT = 630;

    /** Поменял оформление — подними версию: картинки всех статей соберутся заново. */
    private const DESIGN = 9;

    private const DISK = 'local';
    private const DIR = 'blog-og';

    private const DARK = [32, 35, 35];          // ink #202323
    private const YELLOW = 'ffe500';            // brand
    private const TURQUOISE = [146, 247, 237];  // --brand-secondary сайта #92F7ED
    private const FAINT = 'b5b8b8';             // faint — подпись под именем
    private const TITLE_LINE_HEIGHT = 1.34;

    /**
     * Размеры форматов. title — самый крупный и самый мелкий кегль заголовка; top — докуда может подняться текст
     * (темы над заголовком) без обложки — до значков, и с обложкой — до логотипа. stars — значки без обложки: [центр x, центр y, размер, цвет], до текста не доходят;
     * plainWidth — ширина заголовка рядом со значками; dim — затемнение обложки: [высота полосы под логотипом,
     * откуда и докуда градиент густеет под текст]. bottom — отступ снизу (в сторис внизу поле ответа — выше).
     */
    private const FORMATS = [
        self::OG => [
            'width' => 1200, 'height' => 630, 'pad' => 64, 'bottom' => 64, 'logo' => [40, 52],
            'avatar' => 72, 'name' => 30, 'site' => 22, 'tag' => 24, 'gap' => 36,
            'title' => [88, 40], 'top' => [140, 140], 'plainWidth' => 760,
            'stars' => [[1130, 80, 700, self::TURQUOISE], [1010, 550, 300, [255, 229, 0]]],
            'dim' => [220, 100, 400],
        ],
        self::STORY => [
            'width' => 1080, 'height' => 1920, 'pad' => 96, 'bottom' => 240, 'logo' => [64, 160],
            'avatar' => 120, 'name' => 46, 'site' => 34, 'tag' => 38, 'gap' => 64,
            'title' => [108, 56], 'top' => [740, 300], 'plainWidth' => 888,
            'stars' => [[860, 280, 760, self::TURQUOISE], [60, 560, 260, [255, 229, 0]]],
            'dim' => [420, 500, 1250],
        ],
    ];

    public function __construct(private EmojiTextRenderer $renderer) {}

    /** Версия картинки: меняется вместе со всем, что на ней нарисовано. */
    public function version(BlogPost $post): string
    {
        return substr(md5(implode('|', [
            self::DESIGN, $post->id, $post->title, $post->cover_url, $post->author?->name, $post->author?->avatar,
            $post->tags->pluck('name')->implode(','),
        ])), 0, 12);
    }

    public function url(BlogPost $post, string $format = self::OG): string
    {
        $route = $format === self::STORY ? 'blog.story' : 'blog.og';

        return \App\Support\Seo::url(route($route, ['slug' => $post->slug, 'v' => $this->version($post)], false));
    }

    /** JPEG картинки: из готового файла или собранный сейчас. Старые версии этой статьи в этом формате удаляются. */
    public function jpeg(BlogPost $post, string $format = self::OG): string
    {
        $disk = Storage::disk(self::DISK);
        $prefix = $post->id . '-' . ($format === self::STORY ? 'story-' : '');
        $path = self::DIR . '/' . $prefix . $this->version($post) . '.jpg';

        if ($disk->exists($path)) {
            return $disk->get($path);
        }

        $jpeg = $this->render($post, $format);

        foreach ($disk->files(self::DIR) as $old) {
            $name = basename($old);
            $sameFormat = $format === self::STORY ? str_starts_with($name, $prefix) : ! str_contains($name, '-story-');
            if (str_starts_with($name, $post->id . '-') && $sameFormat) {
                $disk->delete($old);
            }
        }
        $disk->put($path, $jpeg);

        return $jpeg;
    }

    public function render(BlogPost $post, string $format = self::OG): string
    {
        $f = self::FORMATS[$format];
        $cover = $this->coverBytes($post);
        $card = $cover !== null ? $this->coverBackground($cover, $f) : $this->plainBackground($f);

        $left = $f['pad'];
        // На темном фоне справа значки — заголовок до них не доходит
        $width = $cover !== null ? $f['width'] - 2 * $f['pad'] : $f['plainWidth'];

        $this->placeLogo($card, $f);

        // Снизу вверх: подпись автора, над ней заголовок, над заголовком темы
        $avatar = $f['avatar'];
        $avatarTop = $f['height'] - $f['bottom'] - $avatar;
        $card->place($this->avatar($post, $avatar), 'top-left', $left, $avatarTop);

        $nameStyle = new TextStyle(resource_path('fonts/Inter-SemiBold.ttf'), $f['name'], 'ffffff', 1.2);
        $siteStyle = new TextStyle(resource_path('fonts/Inter-Regular.ttf'), $f['site'], self::FAINT, 1.2);
        $textLeft = $left + $avatar + (int) round($avatar * 0.28);
        $name = $this->fitLine($post->authorName(), $nameStyle, $f['width'] - $f['pad'] - $textLeft);
        $textTop = $avatarTop + (int) round(($avatar - $f['name'] * 1.2 - $f['site'] * 1.2 - $avatar * 0.08) / 2);
        $this->renderer->draw($card, $name, $textLeft, $textTop, $nameStyle);
        $this->renderer->draw($card, 'Блог Serdal · serdal.ru', $textLeft, $textTop + (int) round($f['name'] * 1.2 + $avatar * 0.08), $siteStyle);

        // Темы над заголовком; заголовок — самым крупным кеглем, который помещается в место до верхней границы
        $tagStyle = new TextStyle(resource_path('fonts/Inter-SemiBold.ttf'), $f['tag'], self::YELLOW, 1.2, $width);
        $tags = $this->tagsLine($post, $tagStyle, $width);
        $tagsBlock = $tags !== '' ? $this->renderer->inkHeight($tags, $tagStyle) + $f['tag'] : 0;
        $titleBottom = $avatarTop - $f['gap'];
        $topLimit = $f['top'][$cover !== null ? 1 : 0];

        [$title, $titleStyle] = $this->fitTitle($post->title, $width, ...[...$f['title'], $titleBottom - $topLimit - $tagsBlock]);
        $titleTop = $titleBottom - $this->renderer->inkHeight($title, $titleStyle);
        $this->renderer->draw($card, $title, $left, $titleTop, $titleStyle);

        if ($tags !== '') {
            $this->renderer->draw($card, $tags, $left, $titleTop - $tagsBlock, $tagStyle);
        }

        return $card->toJpeg(quality: 88)->toString();
    }

    /** Обложка на всю картинку: чуть притемнена целиком, сверху — под логотип, снизу — сильно, под текст. */
    private function coverBackground(string $bytes, array $f): ImageInterface
    {
        [$band, $from, $to] = $f['dim'];
        $card = Image::read($bytes)->cover($f['width'], $f['height']);
        $gd = $card->core()->native();
        imagealphablending($gd, true);

        for ($y = 0; $y < $f['height']; $y++) {
            $top = 0.55 * max(0, 1 - $y / $band);                         // под логотипом
            $t = min(1, max(0, ($y - $from) / ($to - $from)));              // плавно в почти сплошной под текстом
            $bottom = 0.72 * $t * $t * (3 - 2 * $t);
            $alpha = min(0.94, 0.22 + $top + $bottom);
            imageline($gd, 0, $y, $f['width'] - 1, $y, imagecolorallocatealpha($gd, ...[...self::DARK, 127 - (int) round($alpha * 127)]));
        }

        return $card;
    }

    /** Без обложки — темный фон и справа два значка Serdal — бирюзовый и желтый (звезда из фавикона), выглядывают из-за краев. */
    private function plainBackground(array $f): ImageInterface
    {
        $scale = 2; // рисуем в двойном размере, чтобы края были гладкими
        $big = imagecreatetruecolor($f['width'] * $scale, $f['height'] * $scale);
        imagefill($big, 0, 0, imagecolorallocate($big, ...self::DARK));

        foreach ($f['stars'] as [$cx, $cy, $size, $color]) {
            $points = [];
            foreach (self::starOutline() as [$x, $y]) {
                $points[] = (int) round(($cx + ($x / 32 - 0.5) * $size) * $scale);
                $points[] = (int) round(($cy + ($y / 32 - 0.5) * $size) * $scale);
            }
            imagefilledpolygon($big, $points, imagecolorallocate($big, ...$color));
        }

        $gd = imagecreatetruecolor($f['width'], $f['height']);
        imagecopyresampled($gd, $big, 0, 0, 0, 0, $f['width'], $f['height'], $f['width'] * $scale, $f['height'] * $scale);

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
    private function placeLogo(ImageInterface $card, array $f): void
    {
        $logo = Image::read(public_path('images/logo.png'));
        imagefilter($logo->core()->native(), IMG_FILTER_NEGATE);
        imagefilter($logo->core()->native(), IMG_FILTER_BRIGHTNESS, 255);
        $card->place($logo->scale(height: $f['logo'][0]), 'top-left', $f['pad'], $f['logo'][1]);
    }

    private function avatar(BlogPost $post, int $size): ImageInterface
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
            return $this->initials($author->name, $size);
        }

        try {
            return CircleImage::make($bytes, $size);
        } catch (\Throwable) {
            return $this->initials($author?->name ?? 'Serdal', $size);
        }
    }

    /** Нет фото — инициалы на желтом круге, как на сайте. */
    private function initials(string $name, int $size): ImageInterface
    {
        $parts = preg_split('/\s+/u', trim($name));
        $text = mb_strtoupper(mb_substr($parts[0] ?? '', 0, 1) . mb_substr($parts[1] ?? '', 0, 1));

        $square = Image::create($size * 2, $size * 2)->fill(self::YELLOW);
        $square->text($text, $size, $size, function ($font) use ($size) {
            $font->filename(resource_path('fonts/Inter-SemiBold.ttf'));
            $font->size((int) round($size * 0.78));
            $font->color('202323');
            $font->align('center');
            $font->valign('middle');
        });

        return CircleImage::make($square->toPng()->toString(), $size);
    }

    /**
     * Самый крупный кегль, при котором заголовок помещается в $height по высоте и длинное слово не вылезает за край.
     * Не влезает и самым мелким — укорачиваем по словам с многоточием.
     *
     * @return array{string, TextStyle}
     */
    private function fitTitle(string $title, int $width, int $max, int $min, int $height): array
    {
        $title = trim(preg_replace('/\s+/u', ' ', $title));
        $style = new TextStyle(resource_path('fonts/Inter-SemiBold.ttf'), $max, 'ffffff', self::TITLE_LINE_HEIGHT, $width);
        $fits = fn (string $text, TextStyle $style) => $this->renderer->inkHeight($text, $style) <= $height;

        for ($size = $max; $size >= $min; $size -= 2) {
            $candidate = $style->withSize($size);
            if ($this->renderer->widestWord($title, $candidate) <= $width && $fits($title, $candidate)) {
                return [$title, $candidate];
            }
        }

        $style = $style->withSize($min);
        if ($this->renderer->widestWord($title, $style) > $width) {
            $style = $style->withSize(max(24, (int) floor($min * $width / $this->renderer->widestWord($title, $style))));
        }
        $words = preg_split('/\s+/u', $title);
        while (count($words) > 1 && ! $fits(implode(' ', $words) . '…', $style)) {
            array_pop($words);
        }

        return [rtrim(implode(' ', $words), ' .,:;—-') . '…', $style];
    }

    /** Темы статьи через точку — сколько поместится в одну строку. */
    private function tagsLine(BlogPost $post, TextStyle $style, int $width): string
    {
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
