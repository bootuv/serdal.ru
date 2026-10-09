{{-- Карточка статьи в ленте: с обложкой — картинка (увеличивается при наведении), под ней заголовок, автор и дата;
     без обложки — цветная карточка с крупным заголовком и описанием: заливка, рамка, мятная — по очереди в ленте ($style),
     иначе — закрепленный за статьей BlogPost::cardStyle. Подпись — фото и имя автора, дата (у статей команды — только дата). --}}
@php($tag = $titleTag ?? 'h2')
@php($date = \App\Support\HumanDate::date($post->published_at))
@php($reactions = ($post->likes_count || $post->comments_count))
@if($post->cover_url)
    <a href="{{ $post->url }}" class="blog-card blog-card--image">
        <div class="blog-card-media"><img src="{{ $post->cover_url }}" alt="" loading="lazy"></div>
        <{{ $tag }} class="blog-card-title">{{ $post->title }}</{{ $tag }}>
        @include('blog.partials.byline', ['post' => $post, 'date' => $date])
        @if($reactions)@include('blog.partials.counts', ['post' => $post])@endif
    </a>
@else
    <a href="{{ $post->url }}" class="blog-card blog-card--{{ $style ?? $post->cardStyle() }}">
        <{{ $tag }} class="blog-card-big">{{ $post->title }}</{{ $tag }}>
        @if($post->description(220))<p class="blog-card-excerpt">{{ $post->description(220) }}</p>@endif
        @include('blog.partials.byline', ['post' => $post, 'date' => $date])
        @if($reactions)@include('blog.partials.counts', ['post' => $post])@endif
    </a>
@endif
