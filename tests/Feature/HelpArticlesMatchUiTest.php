<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Статьи базы знаний (database/help/*.md) не отстают от интерфейса: всё, что выделено жирным, — название кнопки,
 * раздела или надписи, пишется «**так**» — должно быть в шаблонах или коде. Переименовали кнопку — поправьте статью
 * (не этот тест). Путь «**Ещё**» → «**Оплата**» проверяется по частям, склонения («на **Главной**») — по основе слова.
 * Примеры текста в статьях пишите просто в «кавычках», без жирного.
 */
class HelpArticlesMatchUiTest extends TestCase
{
    public function test_bold_labels_in_help_articles_exist_in_ui(): void
    {
        $ui = collect([...File::allFiles(resource_path('views')), ...File::allFiles(app_path())])
            ->filter(fn ($f) => str_ends_with($f->getFilename(), '.php'))
            ->map(fn ($f) => $f->getContents())
            ->implode("\n");

        $missing = [];
        foreach (File::glob(database_path('help/*/*.md')) as $file) {
            preg_match_all('/\*\*(.+?)\*\*/u', File::get($file), $m);
            foreach ($m[1] as $bold) {
                foreach (explode('→', $bold) as $part) {
                    $label = trim(trim($part), ':.«»" ');
                    if ($label !== '' && ! $this->inUi($label, $ui)) {
                        $missing[] = basename(dirname($file)) . '/' . basename($file) . ': ' . $label;
                    }
                }
            }
        }

        $this->assertSame([], array_values(array_unique($missing)), 'В статьях есть названия, которых нет в интерфейсе');
    }

    private function inUi(string $label, string $ui): bool
    {
        if (str_contains($ui, $label)) {
            return true;
        }

        // Склонения: «Главной», «Истории оплат», «Поддержку» — сравниваем основы слов
        $pattern = collect(preg_split('/\s+/u', $label))
            ->map(fn (string $w) => mb_strlen($w) >= 5 ? preg_quote(mb_substr($w, 0, -2), '/') . '\w*' : preg_quote($w, '/'))
            ->implode('\s+');

        return (bool) preg_match('/' . $pattern . '/u', $ui);
    }
}
