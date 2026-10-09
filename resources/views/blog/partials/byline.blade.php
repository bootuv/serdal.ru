{{-- Подпись карточки статьи: фото и имя автора, под именем дата. Статья команды (без автора) — только дата. --}}
@if($post->author)
    <div class="blog-meta blog-byline">
        @include('partials.userpic', ['user' => $post->author, 'class' => 'blog-byline-photo'])
        <span class="blog-byline-text">
            <span class="blog-byline-name">{{ $post->author->name }}</span>
            @if($date)<span>{{ $date }}</span>@endif
        </span>
    </div>
@elseif($date)
    <div class="blog-meta">{{ $date }}</div>
@endif
