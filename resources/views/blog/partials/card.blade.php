{{-- Карточка статьи в ленте: с обложкой — картинка (увеличивается при наведении), под ней заголовок, автор и дата;
     без обложки — цветная карточка с крупным заголовком и описанием: заливка, рамка, мятная — по очереди в ленте ($style),
     иначе — закрепленный за статьей BlogPost::cardStyle. Подпись — фото и имя автора, под именем его предметы (у статей команды — только дата).
     Есть предметы — дата уходит в нижнюю строку к лайкам и комментариям, иначе под именем вместо предметов. --}}
@php($tag = $titleTag ?? 'h2')
@php($date = \App\Support\HumanDate::date($post->published_at))
@php($dateBelow = $post->author && $post->author->subjects->isNotEmpty())
@php($footer = $dateBelow || $post->likes_count || $post->comments_count)
@if($post->cover_url)
    <a href="{{ $post->url }}" class="blog-card blog-card--image">
        <div class="blog-card-media"><img src="{{ $post->cover_url }}" alt="" loading="lazy"></div>
        <{{ $tag }} class="blog-card-title">{{ $post->title }}</{{ $tag }}>
        @include('blog.partials.byline', ['post' => $post, 'date' => $dateBelow ? null : $date])
        @if($footer)@include('blog.partials.counts', ['post' => $post, 'date' => $dateBelow ? $date : null])@endif
    </a>
@else
    <a href="{{ $post->url }}" class="blog-card blog-card--{{ $style ?? $post->cardStyle() }}">
        <{{ $tag }} class="blog-card-big">{{ $post->title }}</{{ $tag }}>
        @if($post->description(220))<p class="blog-card-excerpt">{{ $post->description(220) }}</p>@endif
        @include('blog.partials.byline', ['post' => $post, 'date' => $dateBelow ? null : $date])
        @if($footer)@include('blog.partials.counts', ['post' => $post, 'date' => $dateBelow ? $date : null])@endif
    </a>
@endif
