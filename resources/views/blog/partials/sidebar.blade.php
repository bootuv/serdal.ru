{{-- Боковая колонка ленты: популярные теги и самые активные авторы --}}
<aside class="blog-sidebar" aria-label="Темы и авторы">
    @if($popularTags->isNotEmpty())
        <section class="blog-side-block" aria-labelledby="blog-side-tags">
            <h2 id="blog-side-tags" class="blog-side-title">Популярные темы</h2>
            <div class="blog-tags">
                @foreach($popularTags as $item)
                    <a href="{{ $item->url }}" @class(['blog-tag', 'active' => ($tag ?? null)?->id === $item->id])>{{ $item->name }}<span>{{ $item->published_count }}</span></a>
                @endforeach
            </div>
        </section>
    @endif

    @if($activeAuthors->isNotEmpty())
        <section class="blog-side-block" aria-labelledby="blog-side-authors">
            <h2 id="blog-side-authors" class="blog-side-title">Авторы</h2>
            <div class="blog-authors">
                @foreach($activeAuthors as $item)
                    <a href="{{ route('blog.author', $item->username) }}" @class(['blog-author', 'active' => ($author ?? null)?->id === $item->id])>
                        @include('partials.userpic', ['user' => $item, 'class' => 'blog-author-photo'])
                        <span class="blog-author-text">
                            <span class="blog-author-name">{{ $item->name }}</span>
                            <span class="blog-author-count">{{ plural_ru($item->published_count, 'статья', 'статьи', 'статей') }}</span>
                        </span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    <section class="blog-side-block blog-side-cta">
        <h2 class="blog-side-title">Пишете об образовании?</h2>
        <p>Учителя Serdal публикуют статьи в блоге с именем и ссылкой на свою страницу.</p>
        <a href="{{ route('become-tutor') }}">Стать учителем Serdal →</a>
    </section>
</aside>
