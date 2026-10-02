<?php

namespace App\Services;

use App\Models\BlogPost;
use App\Models\HelpCategory;
use App\Models\User;
use App\Support\Seo;

/**
 * Список публичных адресов сайта для sitemap.xml и отправки в IndexNow.
 * Закрытые от индексации страницы (сочетания с одним учителем) сюда не попадают.
 */
class SitemapService
{
    public function __construct(private TutorCatalogService $catalog)
    {
    }

    /** @return array<int, array{loc: string, lastmod: ?string, changefreq: string, priority: string}> */
    public function urls(): array
    {
        $urls = [];
        $add = function (string $path, ?string $lastmod, string $changefreq, string $priority) use (&$urls) {
            $urls[] = [
                'loc' => str_starts_with($path, 'http') ? $path : Seo::url($path),
                'lastmod' => $lastmod,
                'changefreq' => $changefreq,
                'priority' => $priority,
            ];
        };

        $catalog = $this->catalog->catalog();
        $tutorsUpdated = $catalog['updated_at'];

        $add('/', $tutorsUpdated, 'daily', '1.0');
        $add(route('catalog.index', [], false), $tutorsUpdated, 'weekly', '0.9');

        foreach (collect($catalog['subjects'])->sortByDesc('count') as $page) {
            $add($page['url'], $tutorsUpdated, 'weekly', '0.9');
        }
        foreach (collect($catalog['directs'])->sortByDesc('count') as $page) {
            $add($page['url'], $tutorsUpdated, 'weekly', '0.8');
        }
        foreach (collect($catalog['combos'])->where('indexable', true)->sortByDesc('count') as $page) {
            $add($page['url'], $tutorsUpdated, 'weekly', '0.8');
        }

        $add(route('about', [], false), null, 'monthly', '0.7');
        $add(route('tariffs', [], false), null, 'monthly', '0.7');
        $add(route('become-tutor', [], false), null, 'monthly', '0.6');
        $add(route('reviews', [], false), null, 'weekly', '0.6');
        $add(route('help.index', [], false), null, 'weekly', '0.6');

        // Новости с отметкой «На сайте»
        $news = \App\Models\Announcement::onSite()->latest('published_at')->get(['slug', 'updated_at']);
        if ($news->isNotEmpty()) {
            $add(route('news.index', [], false), $news->max('updated_at')?->toDateString(), 'weekly', '0.5');
            foreach ($news as $item) {
                $add(route('news.show', $item->slug, false), $item->updated_at?->toDateString(), 'monthly', '0.5');
            }
        }

        $posts = BlogPost::published()->latest('published_at')->get(['slug', 'published_at', 'updated_at']);
        $add(route('blog.index', [], false), $posts->max('updated_at')?->toDateString(), 'weekly', '0.7');
        $blog = app(\App\Services\BlogService::class);
        foreach ($blog->popularTags(500)->where('published_count', '>=', \App\Services\BlogService::TAG_MIN_POSTS) as $tag) {
            $add(route('blog.tag', $tag->slug, false), null, 'weekly', '0.5');
        }
        foreach ($blog->activeAuthors(500) as $author) {
            $add(route('blog.author', $author->username, false), null, 'weekly', '0.5');
        }
        foreach ($posts as $post) {
            $add(route('blog.show', $post->slug, false), $post->updated_at?->toDateString(), 'monthly', '0.6');
        }
        $add(route('privacy', [], false), null, 'yearly', '0.2');
        $add(route('terms', [], false), null, 'yearly', '0.2');
        $add(route('offer', [], false), null, 'yearly', '0.2');

        $categories = HelpCategory::published()
            ->with(['publishedArticles' => fn ($q) => $q->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get();

        foreach ($categories->groupBy('audience') as $audience => $group) {
            $slug = HelpCategory::AUDIENCE_SLUGS[$audience] ?? null;
            if ($slug) {
                $add(route('help.section', $slug, false), $group->max('updated_at')?->toDateString(), 'weekly', '0.5');
            }
        }

        foreach ($categories as $category) {
            $add(
                route('help.category', [$category->audience_slug, $category->slug], false),
                $category->updated_at?->toDateString(),
                'weekly',
                '0.4'
            );

            foreach ($category->publishedArticles as $article) {
                $add(
                    route('help.article', [$category->audience_slug, $category->slug, $article->slug], false),
                    $article->updated_at?->toDateString(),
                    'monthly',
                    '0.4'
                );
            }
        }

        $this->catalog->publicTutorsQuery()
            ->orderBy('id')
            ->get(['id', 'username', 'updated_at'])
            ->each(function (User $tutor) use ($add) {
                $add(route('tutors.show', $tutor, false), $tutor->updated_at?->toDateString(), 'weekly', '0.8');
            });

        return $urls;
    }
}
