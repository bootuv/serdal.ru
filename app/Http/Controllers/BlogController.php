<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use App\Models\BlogTag;
use App\Models\User;
use App\Services\BlogService;
use App\Services\BlogShareImage;
use App\Services\BlogStatsService;
use App\Services\TutorCatalogService;
use App\Support\Seo;

/**
 * Блог на сайте: лента (все статьи, по тегу, по автору) с боковой колонкой — популярные теги и активные авторы —
 * и статья (App\Services\BlogService). Администратор видит и черновики — «Как на сайте» из админки.
 */
class BlogController extends Controller
{
    public function index(BlogService $blog)
    {
        return $this->feed($blog);
    }

    public function tag(string $slug, BlogService $blog)
    {
        return $this->feed($blog, tag: BlogTag::where('slug', $slug)->firstOrFail());
    }

    public function author(string $username, BlogService $blog)
    {
        // Чужим страница автора видна, когда у него есть статьи; сам учитель открывает свою и пустой («Мой блог» в меню)
        $author = User::where('username', $username)
            ->where(fn ($q) => $q->whereHas('blogPosts', fn ($q) => $q->published())
                ->orWhere(fn ($q) => $q->whereKey(auth()->id() ?? 0)->where('role', User::ROLE_TUTOR)))
            ->firstOrFail();

        return $this->feed($blog, author: $author);
    }

    /** Лента: «Популярные» (по умолчанию — вес из лайков, комментариев и возраста, BlogService::hotScore) или «Новые» (?sort=new). */
    /**
     * «Моя лента» (?sort=feed) — статьи авторов, на которых подписан вошедший; на главной блога она первая и открывается
     * по умолчанию, если подписки есть. Гости и поисковики видят «Популярные» — у них подписок нет.
     */
    private function feed(BlogService $blog, ?BlogTag $tag = null, ?User $author = null)
    {
        $followed = ! $tag && ! $author ? $blog->followedIds(auth()->user()) : [];
        $hasFeed = $followed !== [];
        // Лента открывается сама, только если в ней есть свежее (за FEED_FRESH_DAYS), — иначе по умолчанию «Популярные»
        $feedIsFresh = $hasFeed && BlogPost::published()->whereIn('author_id', $followed)
            ->where('published_at', '>=', now()->subDays(BlogService::FEED_FRESH_DAYS))->exists();
        $defaultSort = $feedIsFresh ? 'feed' : 'popular';
        $sort = match (request('sort')) {
            'new' => 'new',
            'popular' => 'popular',
            'feed' => $hasFeed ? 'feed' : 'popular',
            default => $defaultSort,
        };
        $posts = BlogPost::published()
            ->with('author.subjects')
            ->when($tag, fn ($q) => $q->whereHas('tags', fn ($t) => $t->whereKey($tag->id)))
            ->when($author, fn ($q) => $q->where('author_id', $author->id))
            ->when($sort === 'feed', fn ($q) => $q->whereIn('author_id', $followed))
            ->when($sort === 'popular', fn ($q) => $q->orderByDesc('hot_score'))
            ->latest('published_at')
            ->paginate(BlogService::PER_PAGE)
            ->withQueryString();

        return view('blog.index', [
            'posts' => $posts,
            'sort' => $sort,
            'hasFeed' => $hasFeed,
            'defaultSort' => $defaultSort,
            'tag' => $tag,
            'author' => $author,
            'authorProfile' => $author && $this->isPublicTutor($author) ? route('tutors.show', $author) : null,
            // На странице автора — его темы и без списка других авторов
            'popularTags' => $author ? $blog->authorTags($author) : $blog->popularTags(),
            'activeAuthors' => $author ? collect() : $blog->activeAuthors(),
        ]);
    }

    public function show(string $slug, BlogService $blog)
    {
        $isAdmin = auth()->user()?->role === User::ROLE_ADMIN;
        $post = ($isAdmin ? BlogPost::query() : BlogPost::published())->with(['tags', 'author.subjects'])->where('slug', $slug)->first();
        if (! $post) {
            // Адрес сменили после публикации — постоянная переадресация на новый
            $moved = $blog->findByOldSlug($slug);
            abort_unless($moved, 404);

            return redirect()->route('blog.show', $moved->slug, 301);
        }

        // Просмотры: один раз за сессию посетителя; админа, автора статьи и поисковых роботов не считаем.
        // По дням и источникам — для статистики (BlogStatsService)
        $viewed = session('blog_viewed', []);
        $isAuthor = $post->author_id && auth()->id() === $post->author_id; // у статей команды автора нет — гость не «автор»
        $counts = ! $isAdmin && ! $isAuthor && ! BlogStatsService::isBot(request()->userAgent());
        if ($counts && $post->isPublished() && ! in_array($post->id, $viewed, true)) {
            $post->increment('views_count');
            app(BlogStatsService::class)->record($post, request()->headers->get('referer'));
            session(['blog_viewed' => [...$viewed, $post->id]]);
        }

        return view('blog.show', [
            'post' => $post,
            'more' => $blog->more($post),
            'authorProfile' => $post->author && $this->isPublicTutor($post->author) ? route('tutors.show', $post->author) : null,
        ]);
    }

    /**
     * Статья в Markdown для ИИ-агентов (ссылка rel="alternate" на странице статьи и в llms.txt).
     * Сама в поиск не попадает: X-Robots-Tag noindex и canonical на HTML-страницу.
     */
    public function markdown(string $slug, BlogService $blog)
    {
        $post = BlogPost::published()->with(['tags', 'author'])->where('slug', $slug)->first();
        if (! $post) {
            $moved = $blog->findByOldSlug($slug);
            abort_unless($moved, 404);

            return redirect()->route('blog.markdown', $moved->slug, 301);
        }

        return response($blog->markdown($post), 200, [
            'Content-Type' => 'text/markdown; charset=utf-8',
            'X-Robots-Tag' => 'noindex',
            'Link' => '<' . Seo::url(route('blog.show', $post->slug, false)) . '>; rel="canonical"',
        ]);
    }

    /** Картинка статьи для соцсетей (og:image) — BlogShareImage. Версия в ?v= — соцсети кэшируют по адресу. */
    public function shareImage(string $slug, BlogShareImage $image)
    {
        return $this->shareJpeg($slug, $image, BlogShareImage::OG);
    }

    /** Вертикальная картинка статьи для сторис — «Поделиться» на странице статьи. */
    public function storyImage(string $slug, BlogShareImage $image)
    {
        return $this->shareJpeg($slug, $image, BlogShareImage::STORY, 'serdal-' . $slug . '.jpg');
    }

    private function shareJpeg(string $slug, BlogShareImage $image, string $format, ?string $filename = null)
    {
        $isAdmin = auth()->user()?->role === User::ROLE_ADMIN;
        $post = ($isAdmin ? BlogPost::query() : BlogPost::published())->with(['tags', 'author'])->where('slug', $slug)->firstOrFail();

        return response($image->jpeg($post, $format), 200, array_filter([
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'public, max-age=604800',
            'Content-Disposition' => $filename ? 'inline; filename="' . $filename . '"' : null,
        ]));
    }

    /** RSS: последние статьи с полным текстом — для Яндекса, агрегаторов и ИИ-агентов. */
    public function rss()
    {
        $posts = BlogPost::published()->with(['tags', 'author'])->latest('published_at')->limit(30)->get();

        return response()->view('blog.rss', ['posts' => $posts], 200, ['Content-Type' => 'application/rss+xml; charset=utf-8']);
    }

    /** У учителя есть открытая страница в каталоге — на нее можно ссылаться. */
    private function isPublicTutor(User $user): bool
    {
        return app(TutorCatalogService::class)->publicTutorsQuery()->whereKey($user->id)->exists();
    }
}
