<?php

namespace App\Services;

use App\Models\HelpArticle;
use App\Models\HelpCategory;
use App\Support\HelpIcons;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Статьи базы знаний из Markdown-файлов (database/help/{tutors,students}/*.md) — `php artisan help:sync`,
 * запускается при каждом деплое (deploy.sh).
 *
 * Файл — одна категория:
 *   # Название категории
 *   Иконка: home
 *   Описание: одна строка
 *
 *   ## Заголовок статьи
 *   Кратко: подзаголовок в списке
 *
 *   Текст в Markdown…
 *
 * Статья из файла помнит источник (source = «раздел/файл::заголовок») и отпечаток того, что загрузили (source_hash).
 * - новой статьи нет в базе — создаём опубликованной в конце категории;
 * - в базе она не менялась с прошлой загрузки — обновляем из файла (видео из админки сохраняем);
 * - её правили в админке — не трогаем (админка главнее), считаем в edited;
 * - статья без источника с тем же заголовком (написана в админке) — не трогаем, считаем в skipped;
 * - статью убрали из файлов (или переименовали) и в админке не правили — снимаем с публикации.
 * Категорию с тем же названием в разделе берём как есть.
 */
class HelpContentImporter
{
    public function __construct(private HelpCenterService $help) {}

    /** @return array{categories:int, created:int, updated:int, edited:int, skipped:int, unpublished:int} */
    public function sync(string $dir): array
    {
        $stats = ['categories' => 0, 'created' => 0, 'updated' => 0, 'edited' => 0, 'skipped' => 0, 'unpublished' => 0];
        $seen = [];

        foreach (HelpCategory::AUDIENCE_SLUGS as $audience => $slug) {
            foreach (glob(rtrim($dir, '/') . '/' . $slug . '/*.md') ?: [] as $file) {
                $parsed = $this->parse((string) file_get_contents($file));
                if ($parsed['name'] === '') {
                    continue;
                }

                DB::transaction(function () use ($audience, $slug, $file, $parsed, &$stats, &$seen) {
                    $category = $this->category($audience, $parsed, $stats);

                    foreach ($parsed['articles'] as $item) {
                        $source = $slug . '/' . basename($file) . '::' . $item['title'];
                        $seen[] = $source;
                        $this->syncArticle($audience, $category, $source, $item, $stats);
                    }
                });
            }
        }

        HelpArticle::whereNotNull('source')->whereNotIn('source', $seen)->where('is_published', true)->get()
            ->each(function (HelpArticle $article) use (&$stats) {
                if ($this->hash($article) === $article->source_hash) {
                    $article->update(['is_published' => false]);
                    $stats['unpublished']++;
                }
            });

        return $stats;
    }

    private function category(string $audience, array $parsed, array &$stats): HelpCategory
    {
        $category = HelpCategory::where('audience', $audience)->where('name', $parsed['name'])->first();
        if ($category) {
            return $category;
        }

        $stats['categories']++;

        return $this->help->saveCategory(null, [
            'audience' => $audience,
            'name' => $parsed['name'],
            'description' => $parsed['description'],
            'icon' => array_key_exists($parsed['icon'], HelpIcons::ICONS) ? $parsed['icon'] : HelpIcons::DEFAULT,
            'is_published' => true,
        ]);
    }

    private function syncArticle(string $audience, HelpCategory $category, string $source, array $item, array &$stats): void
    {
        $data = [
            'help_category_id' => $category->id,
            'title' => $item['title'],
            'excerpt' => $item['excerpt'],
            'content' => Str::markdown($item['body'], ['html_input' => 'strip', 'allow_unsafe_links' => false]),
            'is_published' => true,
        ];

        $article = HelpArticle::where('source', $source)->first();

        if (! $article) {
            $taken = HelpArticle::whereNull('source')->where('title', $item['title'])
                ->whereHas('category', fn ($q) => $q->where('audience', $audience))
                ->exists();
            if ($taken) {
                $stats['skipped']++;

                return;
            }

            $article = $this->help->saveArticle(null, $data + ['video_source' => 'link', 'video_url' => null]);
            $this->remember($article, $source);
            $stats['created']++;

            return;
        }

        if ($this->hash($article) !== $article->source_hash) {
            $stats['edited']++;

            return;
        }

        // Видео добавляют в админке — при обновлении текста оставляем его как есть
        $video = $article->video_file
            ? ['video_source' => 'file', 'video' => null]
            : ['video_source' => 'link', 'video_url' => $article->video_url];
        $saved = $this->help->saveArticle($article, $data + $video);

        if ($this->hash($saved) !== $article->source_hash) {
            $stats['updated']++;
        }
        $this->remember($saved, $source);
    }

    private function remember(HelpArticle $article, string $source): void
    {
        $article->forceFill(['source' => $source, 'source_hash' => $this->hash($article->fresh())])->saveQuietly();
    }

    /** Отпечаток того, что видит читатель (без видео и порядка — их меняют в админке). */
    private function hash(HelpArticle $article): string
    {
        return hash('sha256', json_encode([
            (int) $article->help_category_id,
            (string) $article->title,
            (string) $article->excerpt,
            (string) $article->content,
            (bool) $article->is_published,
        ], JSON_UNESCAPED_UNICODE));
    }

    /** @return array{name:string, icon:string, description:?string, articles:array<int, array{title:string, excerpt:?string, body:string}>} */
    public function parse(string $markdown): array
    {
        $result = ['name' => '', 'icon' => HelpIcons::DEFAULT, 'description' => null, 'articles' => []];
        $current = null;

        foreach (preg_split('/\R/u', $markdown) as $line) {
            if (preg_match('/^## (.+)$/u', $line, $m)) {
                if ($current) {
                    $result['articles'][] = $current;
                }
                $current = ['title' => trim($m[1]), 'excerpt' => null, 'body' => ''];

                continue;
            }

            if ($current === null) {
                match (true) {
                    (bool) preg_match('/^# (.+)$/u', $line, $m) => $result['name'] = trim($m[1]),
                    (bool) preg_match('/^Иконка:\s*(.+)$/u', $line, $m) => $result['icon'] = trim($m[1]),
                    (bool) preg_match('/^Описание:\s*(.+)$/u', $line, $m) => $result['description'] = trim($m[1]),
                    default => null,
                };

                continue;
            }

            if ($current['excerpt'] === null && trim($current['body']) === '' && preg_match('/^Кратко:\s*(.+)$/u', $line, $m)) {
                $current['excerpt'] = trim($m[1]);

                continue;
            }

            $current['body'] .= $line . "\n";
        }

        if ($current) {
            $result['articles'][] = $current;
        }

        $result['articles'] = array_map(fn (array $a) => ['body' => trim($a['body'])] + $a, $result['articles']);

        return $result;
    }
}
