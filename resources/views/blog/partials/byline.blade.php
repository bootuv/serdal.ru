{{-- Подпись карточки статьи: фото и имя автора, под именем его предметы (нет предметов — дата). Статья команды (без автора) — только дата.
     Предметы автора — грузить вместе со статьями: with('author.subjects'). --}}
@if($post->author)
    <div class="blog-meta blog-byline">
        @include('partials.userpic', ['user' => $post->author, 'class' => 'blog-byline-photo'])
        <span class="blog-byline-text">
            <span class="blog-byline-name">{{ $post->author->name }}</span>
            @if($post->author->subjects->isNotEmpty())
                <span class="blog-byline-subjects">{{ $post->author->subjectsList }}</span>
            @elseif($date)
                <span>{{ $date }}</span>
            @endif
        </span>
    </div>
@elseif($date)
    <div class="blog-meta">{{ $date }}</div>
@endif
