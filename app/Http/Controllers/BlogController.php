<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use App\Models\BlogTag;
use App\Models\User;
use App\Services\BlogService;
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
        $author = User::where('username', $username)->whereHas('blogPosts', fn ($q) => $q->published())->firstOrFail();

        return $this->feed($blog, author: $author);
    }

    /** Лента: «Популярные» (по умолчанию — вес из лайков, комментариев и возраста, BlogService::hotScore) или «Новые» (?sort=new). */
    private function feed(BlogService $blog, ?BlogTag $tag = null, ?User $author = null)
    {
        $sort = request('sort') === 'new' ? 'new' : 'popular';
        $posts = BlogPost::published()
            ->with('author')
            ->when($tag, fn ($q) => $q->whereHas('tags', fn ($t) => $t->whereKey($tag->id)))
            ->when($author, fn ($q) => $q->where('author_id', $author->id))
            ->when($sort === 'popular', fn ($q) => $q->orderByDesc('hot_score'))
            ->latest('published_at')
            ->paginate(BlogService::PER_PAGE)
            ->withQueryString();

        return view('blog.index', [
            'posts' => $posts,
            'sort' => $sort,
            'tag' => $tag,
            'author' => $author,
            'authorProfile' => $author && $this->isPublicTutor($author) ? route('tutors.show', $author) : null,
            'popularTags' => $blog->popularTags(),
            'activeAuthors' => $blog->activeAuthors(),
        ]);
    }

    public function show(string $slug, BlogService $blog)
    {
        $isAdmin = auth()->user()?->role === User::ROLE_ADMIN;
        $post = ($isAdmin ? BlogPost::query() : BlogPost::published())->with(['tags', 'author'])->where('slug', $slug)->first();
        if (! $post) {
            // Адрес сменили после публикации — постоянная переадресация на новый
            $moved = $blog->findByOldSlug($slug);
            abort_unless($moved, 404);

            return redirect()->route('blog.show', $moved->slug, 301);
        }

        // Просмотры: один раз за сессию посетителя, администратора не считаем
        $viewed = session('blog_viewed', []);
        if (! $isAdmin && ! in_array($post->id, $viewed, true)) {
            $post->increment('views_count');
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
