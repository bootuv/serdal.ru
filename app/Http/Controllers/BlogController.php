<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use App\Models\User;
use App\Services\BlogService;

/** Блог на сайте: список статей и статья (App\Services\BlogService). Администратор видит и черновики — «Как на сайте» из админки. */
class BlogController extends Controller
{
    public function index()
    {
        $posts = BlogPost::published()->latest('published_at')->paginate(BlogService::PER_PAGE);

        return view('blog.index', ['posts' => $posts]);
    }

    public function show(string $slug, BlogService $blog)
    {
        $isAdmin = auth()->user()?->role === User::ROLE_ADMIN;
        $post = ($isAdmin ? BlogPost::query() : BlogPost::published())->where('slug', $slug)->firstOrFail();

        // Просмотры: один раз за сессию посетителя, администратора не считаем
        $viewed = session('blog_viewed', []);
        if (! $isAdmin && ! in_array($post->id, $viewed, true)) {
            $post->increment('views_count');
            session(['blog_viewed' => [...$viewed, $post->id]]);
        }

        return view('blog.show', ['post' => $post, 'more' => $blog->more($post)]);
    }
}
