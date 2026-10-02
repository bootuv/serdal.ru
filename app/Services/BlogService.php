<?php

namespace App\Services;

use App\Models\BlogPost;
use App\Models\User;
use App\Support\RichText;

/**
 * Блог на сайте (serdal.ru/blog): статьи для поиска. Админка «Блог» пишет (блочный редактор x-ui.block-editor),
 * сайт показывает опубликованные (BlogController). Опубликованные попадают в sitemap и IndexNow (SitemapService) и в llms.txt.
 */
class BlogService
{
    public const IMAGE_DIR = 'blog';

    public const PER_PAGE = 12;

    /**
     * Сохранить статью. $data: title, slug, excerpt, cover_url, body, published_at (null — черновик).
     * Адрес — из заголовка, если не задан; у опубликованной статьи адрес меняется только вручную.
     */
    public function save(?BlogPost $post, array $data, ?User $author = null): BlogPost
    {
        $post ??= new BlogPost(['created_by' => $author?->id]);

        $title = trim((string) $data['title']);
        $slug = BlogPost::toSlug(trim((string) ($data['slug'] ?? '')));
        $slug = $slug !== '' ? BlogPost::slugFrom($slug, $post->id) : ($post->slug ?: BlogPost::slugFrom($title, $post->id));

        $post->fill([
            'title' => $title,
            'slug' => $slug,
            'excerpt' => trim((string) ($data['excerpt'] ?? '')) ?: null,
            'cover_url' => trim((string) ($data['cover_url'] ?? '')) ?: null,
            'body' => RichText::clean($data['body'] ?? null),
            'published_at' => $data['published_at'] ?? null,
        ])->save();

        return $post;
    }

    public function unpublish(BlogPost $post): void
    {
        $post->update(['published_at' => null]);
    }

    public function delete(BlogPost $post): void
    {
        $post->delete();
    }

    /** Ещё статьи под текущей: свежие, кроме этой. */
    public function more(BlogPost $post, int $limit = 3)
    {
        return BlogPost::published()->whereKeyNot($post->id)->latest('published_at')->limit($limit)->get();
    }

    /** Картинка для статьи (обложка или в текст) — на CDN, как картинки базы знаний. */
    public function storeImage(\Illuminate\Http\UploadedFile $file): string
    {
        return app(HelpCenterService::class)->storeImage($file, self::IMAGE_DIR);
    }
}
