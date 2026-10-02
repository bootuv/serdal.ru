{{-- Карточка статьи в ленте: с обложкой — картинка (увеличивается при наведении), под ней заголовок, автор и дата;
     без обложки — цветная карточка с крупным заголовком и описанием: заливка, рамка, мятная — по очереди в ленте ($style),
     иначе — закрепленный за статьей BlogPost::cardStyle. --}}
@php($tag = $titleTag ?? 'h2')
@php($meta = implode(' · ', array_filter([$post->author?->name, \App\Support\HumanDate::date($post->published_at)])))
@php($reactions = ($post->likes_count || $post->comments_count))
@if($post->cover_url)
    <a href="{{ $post->url }}" class="blog-card blog-card--image">
        <div class="blog-card-media"><img src="{{ $post->cover_url }}" alt="" loading="lazy"></div>
        <{{ $tag }} class="blog-card-title">{{ $post->title }}</{{ $tag }}>
        <div class="blog-meta">{{ $meta }}</div>
        @if($reactions)@include('blog.partials.counts', ['post' => $post])@endif
    </a>
@else
    <a href="{{ $post->url }}" class="blog-card blog-card--{{ $style ?? $post->cardStyle() }}">
        <{{ $tag }} class="blog-card-big">{{ $post->title }}</{{ $tag }}>
        @if($post->description(220))<p class="blog-card-excerpt">{{ $post->description(220) }}</p>@endif
        <div class="blog-meta">{{ $meta }}</div>
        @if($reactions)@include('blog.partials.counts', ['post' => $post])@endif
    </a>
@endif
