<?php

namespace App\Livewire\Blog;

use App\Models\BlogPost;
use App\Services\BlogService;
use Livewire\Attributes\Locked;
use Livewire\Component;

/** Лайк статьи блога (страница статьи): вошедший ставит и снимает, гостя ведем на вход и обратно. */
class LikeButton extends Component
{
    #[Locked]
    public int $postId;

    public function toggle(BlogService $blog)
    {
        $post = BlogPost::published()->findOrFail($this->postId);
        if (! auth()->check()) {
            return $this->redirect(route('login', ['next' => $post->url]));
        }
        $blog->toggleLike($post, auth()->user());
    }

    public function render(BlogService $blog)
    {
        $post = BlogPost::findOrFail($this->postId);

        return view('livewire.blog.like-button', [
            'count' => (int) $post->likes_count,
            'liked' => $blog->likedBy($post, auth()->user()),
        ]);
    }
}
