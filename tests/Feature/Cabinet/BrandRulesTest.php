<?php

namespace Tests\Feature\Cabinet;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Автоматическая проверка правил фирменного стиля (docs/design/BRAND.md) во всех шаблонах кабинетов.
 * Если тест упал — исправь шаблон, а не тест. Новое правило или исключение — только по решению владельца.
 */
class BrandRulesTest extends TestCase
{
    /** @return array<string, string> путь => содержимое */
    private function templates(): array
    {
        $dirs = [
            resource_path('views/components/ui'),
            resource_path('views/livewire/cabinet'),
            resource_path('views/livewire/auth'),
            resource_path('views/errors'),
        ];
        $files = [resource_path('views/components/layouts/cabinet.blade.php') => null, resource_path('views/components/layouts/auth.blade.php') => null];
        foreach ($dirs as $dir) {
            if (! is_dir($dir)) {
                continue;
            }
            foreach (File::allFiles($dir) as $f) {
                $files[$f->getPathname()] = null;
            }
        }
        foreach (array_keys($files) as $path) {
            $files[$path] = File::get($path);
        }

        return $files;
    }

    /** Строки со значениями атрибутов class / :class / @class. */
    private function classValues(string $content): array
    {
        preg_match_all('/(?:class|@class)\s*=?\s*(?:"([^"]*)"|\(\[(.*?)\]\))/s', $content, $m);

        return array_filter(array_merge($m[1], $m[2]));
    }

    public function test_no_arbitrary_tailwind_values(): void
    {
        $violations = [];
        foreach ($this->templates() as $path => $content) {
            foreach ($this->classValues($content) as $value) {
                // Произвольные значения/варианты Tailwind: p-[13px], text-[#abc], [&>*]:…
                if (preg_match('/(?:^|[\s\'":])(?:[a-z-]+-\[|\[[&@:])/', $value)) {
                    $violations[] = basename($path) . ': ' . trim(mb_substr($value, 0, 120));
                }
            }
        }
        $this->assertSame([], $violations, "Произвольные значения Tailwind запрещены (BRAND.md §1):\n" . implode("\n", $violations));
    }

    public function test_no_inline_styles_and_no_filament_classes(): void
    {
        $violations = [];
        foreach ($this->templates() as $path => $content) {
            if (preg_match('/\sstyle="/', $content)) {
                $violations[] = basename($path) . ': inline style';
            }
            if (preg_match('/\bfi-[a-z]/', $content)) {
                $violations[] = basename($path) . ': класс Filament .fi-*';
            }
        }
        $this->assertSame([], $violations, "Инлайн-стили и классы Filament в кабинетах запрещены (BRAND.md §1):\n" . implode("\n", $violations));
    }

    public function test_dictionary_words(): void
    {
        $forbidden = [
            '/\bурок(а|у|ом|е|и|ов|ам|ами|ах)?\b/iu' => 'урок → занятие',
            '/\bстудент/iu' => 'студент → ученик',
            '/\bпреподавател/iu' => 'преподаватель → учитель',
            '/\bсесси[яиюей]/iu' => 'сессия → занятие',
            '/\bтьютор/iu' => 'тьютор → учитель',
            '/\bBBB\b|BigBlueButton|meeting_id/u' => 'технические названия в интерфейсе',
        ];
        $violations = [];
        foreach ($this->templates() as $path => $content) {
            // Проверяем только видимый текст: убираем Blade-комментарии, PHP и атрибуты-выражения
            $text = preg_replace(['/\{\{--.*?--\}\}/s', '/@php.*?@endphp/s', '/\{\{.*?\}\}/s', '/\{!!.*?!!\}/s'], ' ', $content);
            foreach ($forbidden as $pattern => $hint) {
                if (preg_match($pattern, strip_tags($text), $m)) {
                    $violations[] = basename($path) . ': «' . $m[0] . '» (' . $hint . ')';
                }
            }
        }
        $this->assertSame([], $violations, "Слова вне словаря (BRAND.md §7):\n" . implode("\n", $violations));
    }

    /** Названия, которые пишутся латиницей и переводу не подлежат. */
    private const LATIN_ALLOWED = ['Serdal', 'Telegram', 'WhatsApp', 'Google', 'ЮKassa', 'Kinescope', 'YouTube', 'Rutube',
        'PDF', 'JPG', 'JPEG', 'PNG', 'HEIC', 'GIF', 'Word', 'Excel', 'PowerPoint', 'MP3', 'MP4', 'WebM', 'DOC', 'XLS', 'PPT', 'IMG'];

    public function test_no_english_text(): void
    {
        $violations = [];
        foreach ($this->templates() as $path => $content) {
            $text = preg_replace(['/\{\{--.*?--\}\}/s', '/@php.*?@endphp/s', '/<script.*?<\/script>/s'], ' ', $content);
            $candidates = [];
            // Статичные подписи и подсказки
            preg_match_all('/\s(?:placeholder|label|hint|title|aria-label|sub|text|button|message)="([^"{}$]*)"/u', $text, $m);
            $candidates = array_merge($candidates, $m[1]);
            // Видимый текст: фрагменты между тегами, где есть кириллица (фраза на русском с вкраплением латиницы)
            preg_match_all('/>([^<>{}@$]*\p{Cyrillic}[^<>{}@$]*)</u', $text, $m);
            $candidates = array_merge($candidates, $m[1]);

            foreach ($candidates as $value) {
                if (! preg_match_all('/(?<![@\w.])[A-Za-z]{3,}(?![\w.@])/u', $value, $words)) {
                    continue;
                }
                foreach ($words[0] as $word) {
                    if (! in_array($word, self::LATIN_ALLOWED, true) && ! str_contains($value, $word . '.ru')) {
                        $violations[] = basename($path) . ': «' . trim(mb_substr($value, 0, 80)) . '» (' . $word . ')';
                    }
                }
            }
        }
        $this->assertSame([], array_values(array_unique($violations)), "Английский текст в интерфейсе запрещён — только русский (названия сервисов — исключение):\n" . implode("\n", array_unique($violations)));
    }

    /**
     * Условия Blade (@if, @unless, @error…) внутри атрибутов тега запрещены: Livewire окружает такие блоки
     * служебными комментариями, и они ломают тег — атрибуты выводятся на страницу текстом.
     * Вместо них — выражение: {!! $cond ? 'x-cloak' : '' !!} или attr="{{ $cond ? 'a' : 'b' }}".
     */
    public function test_no_blade_conditionals_inside_tags(): void
    {
        $violations = [];
        foreach ($this->templates() as $path => $content) {
            $text = preg_replace('/\{\{--.*?--\}\}/s', ' ', $content);
            preg_match_all('/<(?!--)([a-zA-Z][\w.:-]*)\b((?:=>|->|[^<>"\']|"[^"]*"|\'[^\']*\')*?)>/s', $text, $tags, PREG_SET_ORDER);
            foreach ($tags as $tag) {
                if (preg_match('/@(if|unless|isset|empty|foreach|auth|guest|error|else|endif)\b/', $tag[2], $m)) {
                    $violations[] = basename($path) . ': <' . $tag[1] . '> содержит @' . $m[1];
                }
            }
        }
        $this->assertSame([], $violations, "Условия Blade внутри тега ломают разметку в Livewire:\n" . implode("\n", $violations));
    }
}
