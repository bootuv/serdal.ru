<?php

namespace App\Services;

use App\Models\BlogPost;
use App\Models\User;
use App\Support\HumanDate;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Статистика блога: просмотры статей по дням и источники (таблица blog_post_stats).
 * Просмотр записывается одним запросом (upsert) и только тот, что засчитан в views_count: раз за сессию, без админа,
 * автора статьи и поисковых роботов. Отчеты — для админа (весь блог) и учителя (его статьи).
 */
class BlogStatsService
{
    public const SOURCES = [
        'search' => 'Поиск',
        'social' => 'Соцсети и мессенджеры',
        'site' => 'С сайта Serdal',
        'other' => 'Другие сайты',
        'direct' => 'Прямые заходы',
    ];

    public const PERIODS = [7 => '7 дней', 30 => '30 дней', 90 => '90 дней'];

    private const SEARCH = ['yandex.', 'ya.ru', 'google.', 'bing.com', 'duckduckgo.', 'go.mail.ru', 'search.', 'rambler.ru', 'yahoo.'];

    private const SOCIAL = ['vk.com', 'vk.ru', 'ok.ru', 't.me', 'telegram.', 'whatsapp.', 'wa.me', 'facebook.', 'instagram.', 'twitter.', 'x.com', 'youtube.', 'dzen.ru', 'zen.yandex', 'tiktok.', 'pinterest.', 'max.ru'];

    /** Робот поисковика или сервис превью ссылок — не читатель. */
    public static function isBot(?string $userAgent): bool
    {
        return $userAgent === null || $userAgent === '' || (bool) preg_match('/bot|crawl|spider|slurp|preview|facebookexternalhit|vkshare|whatsapp|telegram|curl|wget|python|headless/i', $userAgent);
    }

    /** Откуда пришел читатель — по адресу предыдущей страницы. */
    public static function source(?string $referer): string
    {
        $host = strtolower((string) parse_url((string) $referer, PHP_URL_HOST));
        if ($host === '') {
            return 'direct';
        }
        $own = strtolower((string) parse_url(config('app.url'), PHP_URL_HOST));
        $host = preg_replace('/^(www\.|m\.)/', '', $host);
        if ($host === preg_replace('/^www\./', '', $own) || str_ends_with($host, 'serdal.ru')) {
            return 'site';
        }
        foreach (self::SEARCH as $needle) {
            if (str_contains($host, $needle)) {
                return 'search';
            }
        }
        foreach (self::SOCIAL as $needle) {
            if (str_starts_with($host, $needle) || str_contains($host, '.' . $needle) || str_contains($host, $needle)) {
                return 'social';
            }
        }

        return 'other';
    }

    /** Засчитать просмотр за сегодня. */
    public function record(BlogPost $post, ?string $referer): void
    {
        $source = self::source($referer);
        $row = ['blog_post_id' => $post->id, 'date' => now()->toDateString(), 'views' => 1] + array_fill_keys(array_keys(self::SOURCES), 0);
        $row[$source] = 1;

        DB::table('blog_post_stats')->upsert([$row], ['blog_post_id', 'date'], [
            'views' => DB::raw('blog_post_stats.views + 1'),
            $source => DB::raw("blog_post_stats.{$source} + 1"),
        ]);
    }

    /**
     * Отчет за последние $days дней: по всему блогу, по статьям автора ($author) или по одной статье ($postId).
     *
     * @return array{views: int, previous: int, days: array, sources: array, likes: int, comments: int, followers: ?int, posts: Collection, authors: Collection, since: ?string}
     */
    public function report(int $days, ?User $author = null, ?int $postId = null): array
    {
        $days = array_key_exists($days, self::PERIODS) ? $days : 30;
        $today = CarbonImmutable::today();
        $from = $today->subDays($days - 1);
        $postIds = $this->postIds($author, $postId);

        $stats = DB::table('blog_post_stats')->when($postIds !== null, fn ($q) => $q->whereIn('blog_post_id', $postIds));
        $byDay = (clone $stats)->where('date', '>=', $from->toDateString())
            ->groupBy('date')->selectRaw('date, SUM(views) as views')->pluck('views', 'date')
            ->mapWithKeys(fn ($v, $d) => [substr((string) $d, 0, 10) => (int) $v]);

        $series = [];
        for ($d = $from; $d->lte($today); $d = $d->addDay()) {
            $views = $byDay[$d->toDateString()] ?? 0;
            $series[] = [
                'label' => $d->isoFormat('D MMM'),
                'value' => $views,
                'hint' => HumanDate::date($d) . ' — ' . plural_ru($views, 'просмотр', 'просмотра', 'просмотров'),
            ];
        }

        $sums = (clone $stats)->where('date', '>=', $from->toDateString())
            ->selectRaw('COALESCE(SUM(views),0) as views, ' . implode(', ', array_map(fn ($s) => "COALESCE(SUM({$s}),0) as {$s}", array_keys(self::SOURCES))))
            ->first();
        $previous = (int) (clone $stats)->whereBetween('date', [$from->subDays($days)->toDateString(), $from->subDay()->toDateString()])->sum('views');

        $sources = collect(self::SOURCES)->map(fn ($label, $key) => ['label' => $label, 'value' => (int) ($sums->{$key} ?? 0)])
            ->filter(fn ($s) => $s['value'] > 0)->sortByDesc('value')->values()->all();

        $period = fn ($table) => DB::table($table)->where('created_at', '>=', $from->startOfDay())
            ->when($postIds !== null, fn ($q) => $q->whereIn('blog_post_id', $postIds));

        return [
            'views' => (int) ($sums->views ?? 0),
            'previous' => $previous,
            'days' => $series,
            'sources' => $sources,
            'likes' => $period('blog_post_likes')->count(),
            'comments' => $period('blog_comments')->whereNull('deleted_at')->count(),
            'followers' => $author && ! $postId ? DB::table('blog_author_follows')->where('author_id', $author->id)->where('created_at', '>=', $from->startOfDay())->count() : null,
            'posts' => $postId ? collect() : $this->topPosts($from, $postIds),
            'authors' => $author || $postId ? collect() : $this->topAuthors($from),
            'since' => ($first = DB::table('blog_post_stats')->min('date')) ? HumanDate::date(CarbonImmutable::parse($first)) : null,
        ];
    }

    /** Опубликованные статьи отчета: null — весь блог. */
    private function postIds(?User $author, ?int $postId): ?array
    {
        if ($postId) {
            return [$postId];
        }

        return $author ? BlogPost::where('author_id', $author->id)->pluck('id')->all() : null;
    }

    /** Статьи по просмотрам за период; ниже — опубликованные без просмотров за период. */
    private function topPosts(CarbonImmutable $from, ?array $postIds): Collection
    {
        $views = DB::table('blog_post_stats')->where('date', '>=', $from->toDateString())
            ->when($postIds !== null, fn ($q) => $q->whereIn('blog_post_id', $postIds))
            ->groupBy('blog_post_id')->selectRaw('blog_post_id, SUM(views) as views')->pluck('views', 'blog_post_id');

        return BlogPost::published()->when($postIds !== null, fn ($q) => $q->whereIn('id', $postIds))
            ->with('author')->get()
            ->map(fn (BlogPost $p) => [
                'id' => $p->id,
                'title' => $p->title,
                'url' => $p->url,
                'author' => $p->authorName(),
                'views' => (int) ($views[$p->id] ?? 0),
                'total' => (int) $p->views_count,
                'likes' => (int) $p->likes_count,
                'comments' => (int) $p->comments_count,
            ])
            ->sortByDesc(fn ($p) => [$p['views'], $p['total']])->values();
    }

    /** Авторы по просмотрам их статей за период (статьи команды — «Команда Serdal»). */
    private function topAuthors(CarbonImmutable $from): Collection
    {
        return DB::table('blog_post_stats')->join('blog_posts', 'blog_posts.id', '=', 'blog_post_stats.blog_post_id')
            ->leftJoin('users', 'users.id', '=', 'blog_posts.author_id')
            ->where('blog_post_stats.date', '>=', $from->toDateString())
            ->groupBy('blog_posts.author_id', 'users.name')
            ->selectRaw('blog_posts.author_id, users.name, SUM(blog_post_stats.views) as views, COUNT(DISTINCT blog_posts.id) as posts')
            ->orderByDesc('views')->limit(10)->get()
            ->map(fn ($r) => ['name' => $r->name ?: 'Команда Serdal', 'views' => (int) $r->views, 'posts' => (int) $r->posts]);
    }
}
