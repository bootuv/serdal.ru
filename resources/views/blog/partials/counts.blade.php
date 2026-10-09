{{-- Нижняя строка карточки ленты: дата (если не поместилась в подпись), лайки и комментарии (только ненулевые) --}}
<div class="blog-counts">
    @if($date ?? null)<span>{{ $date }}</span>@endif
    @if($post->likes_count)
        <span title="Лайки"><svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path d="M12 20s-7-4.4-7-10a4 4 0 0 1 7-2.6A4 4 0 0 1 19 10c0 5.6-7 10-7 10z"/></svg>{{ $post->likes_count }}<span class="sr-only"> {{ plural_ru($post->likes_count, 'лайк', 'лайка', 'лайков', false) }}</span></span>
    @endif
    @if($post->comments_count)
        <span title="Комментарии"><svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path d="M5 5h14a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H9l-5 4V6a1 1 0 0 1 1-1z"/></svg>{{ $post->comments_count }}<span class="sr-only"> {{ plural_ru($post->comments_count, 'комментарий', 'комментария', 'комментариев', false) }}</span></span>
    @endif
</div>
