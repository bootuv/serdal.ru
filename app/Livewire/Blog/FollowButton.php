<?php

namespace App\Livewire\Blog;

use App\Models\User;
use App\Services\BlogService;
use Livewire\Attributes\Locked;
use Livewire\Component;

/** Подписка на автора блога (страница автора, карточка автора под статьей). Гостя — на вход и обратно. */
class FollowButton extends Component
{
    #[Locked]
    public int $authorId;

    /** Куда вернуть гостя после входа. */
    #[Locked]
    public string $returnUrl = '';

    /** Показывать число подписчиков рядом с кнопкой. */
    #[Locked]
    public bool $showCount = false;

    public function toggle(BlogService $blog)
    {
        if (! auth()->check()) {
            return $this->redirect(route('login', ['next' => $this->returnUrl ?: url()->previous()]));
        }
        $author = User::findOrFail($this->authorId);
        $blog->toggleFollow($author, auth()->user());
        // Число подписчиков в шапке страницы автора — вне компонента, обновляем событием
        $this->dispatch('blog-followers', label: plural_ru($blog->followersCount($author), 'подписчик', 'подписчика', 'подписчиков'));
    }

    public function render(BlogService $blog)
    {
        $author = User::findOrFail($this->authorId);

        return view('livewire.blog.follow-button', [
            'self' => auth()->id() === $author->id,
            'following' => $blog->isFollowing($author, auth()->user()),
            'count' => $this->showCount ? $blog->followersCount($author) : null,
        ]);
    }
}
