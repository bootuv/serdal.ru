<?php

namespace App\Console\Commands;

use App\Models\BlogPost;
use App\Services\BlogService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Первые статьи блога от «Команды Serdal» из database/blog/*.md — черновиками: админ проверяет, добавляет обложку
 * и публикует. Файл: заголовок между строками «---» (title, slug, excerpt, tags через запятую), ниже — текст в Markdown.
 * Статья с таким адресом уже есть — пропускаем (правки в админке не затираются).
 */
class BlogStarter extends Command
{
    protected $signature = 'blog:starter';

    protected $description = 'Добавить в блог черновики первых статей от команды Serdal';

    public function handle(BlogService $blog): int
    {
        $files = glob(database_path('blog/*.md')) ?: [];
        sort($files);

        foreach ($files as $file) {
            if (! preg_match('/\A---\n(.*?)\n---\n(.*)\z/s', str_replace("\r\n", "\n", (string) file_get_contents($file)), $m)) {
                $this->warn('Пропущен (нет заголовка): ' . basename($file));
                continue;
            }

            $meta = [];
            foreach (explode("\n", $m[1]) as $line) {
                [$key, $value] = array_pad(explode(':', $line, 2), 2, '');
                $meta[trim($key)] = trim($value);
            }

            if (BlogPost::where('slug', $meta['slug'])->exists()) {
                $this->line('Уже есть: ' . $meta['title']);
                continue;
            }

            $blog->save(null, [
                'title' => $meta['title'],
                'slug' => $meta['slug'],
                'excerpt' => $meta['excerpt'] ?? '',
                'body' => Str::markdown(trim($m[2])),
                'tags' => array_filter(array_map('trim', explode(',', $meta['tags'] ?? ''))),
                'author_id' => null,
                'published_at' => null,
            ]);
            $this->info('Черновик: ' . $meta['title']);
        }

        $this->line('Проверьте и опубликуйте: ' . route('cabinet.admin.blog'));

        return self::SUCCESS;
    }
}
