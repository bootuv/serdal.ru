@extends('layout')

@section('title', $post->title . ' — блог Serdal')
@section('description', $post->description())
@section('og_type', 'article')
@if($post->cover_url)
    @section('og_image', $post->cover_url)
@endif
@if(! $post->isPublished())
    @section('robots', 'noindex, nofollow')
@endif
@section('meta')
    @if($post->published_at)<meta property="article:published_time" content="{{ $post->published_at->toAtomString() }}">@endif
    <meta property="article:modified_time" content="{{ $post->updated_at->toAtomString() }}">
@endsection

@push('jsonld')
    {!! \App\Support\Seo::jsonLd(\App\Support\Seo::breadcrumbs([
        ['name' => 'Блог', 'url' => \App\Support\Seo::url(route('blog.index', [], false))],
        ['name' => $post->title, 'url' => \App\Support\Seo::canonical()],
    ])) !!}
    {!! \App\Support\Seo::jsonLd(array_filter([
        '@type' => 'BlogPosting',
        'headline' => $post->title,
        'description' => $post->description(200),
        'image' => $post->cover_url,
        'inLanguage' => 'ru-RU',
        'datePublished' => optional($post->published_at)->toAtomString(),
        'dateModified' => $post->updated_at->toAtomString(),
        'author' => ['@id' => \App\Support\Seo::url('#organization')],
        'publisher' => ['@id' => \App\Support\Seo::url('#organization')],
        'mainEntityOfPage' => \App\Support\Seo::canonical(),
        'isPartOf' => ['@id' => \App\Support\Seo::url('#website')],
    ])) !!}
@endpush

@section('styles')
    <link href="/css/blog.css?v={{ filemtime(public_path('css/blog.css')) }}" rel="stylesheet" type="text/css">
@endsection

@section('content')
    <div class="content blog-content">
        <article class="blog-article">
            <a href="{{ route('blog.index') }}" class="blog-back">← Блог</a>
            <h1 class="blog-article-title">{{ $post->title }}</h1>
            <div class="blog-meta">
                {{ $post->published_at ? \App\Support\HumanDate::date($post->published_at) : 'Черновик' }} · {{ $post->readingMinutes() }} мин чтения
            </div>
            @if($post->cover_url)
                <img src="{{ $post->cover_url }}" alt="{{ $post->title }}" class="blog-article-cover">
            @endif
            @if($post->body)
                <div class="blog-body">{!! \App\Support\RichText::html($post->body) !!}</div>
            @endif

            <aside class="blog-cta">
                <div class="blog-cta-title">Занятия онлайн – в одном месте</div>
                <p>Serdal – платформа для дополнительных и дистанционных занятий: класс с видео и доской в браузере, расписание, задания и материалы. Разработана в Ингушетии.</p>
                <a href="{{ route('become-tutor') }}" class="blog-cta-button">Попробовать бесплатно</a>
            </aside>
        </article>

        @if($more->isNotEmpty())
            <section class="blog-more" aria-labelledby="blog-more-title">
                <h2 id="blog-more-title" class="blog-more-title">Читайте также</h2>
                <div class="blog-grid">
                    @foreach($more as $item)
                        <a href="{{ $item->url }}" class="blog-card">
                            @if($item->cover_url)
                                <img src="{{ $item->cover_url }}" alt="" loading="lazy" class="blog-card-cover">
                            @else
                                <div class="blog-card-cover blog-card-cover--empty"></div>
                            @endif
                            <div class="blog-card-text">
                                <div class="blog-meta">{{ \App\Support\HumanDate::date($item->published_at) }}</div>
                                <h3 class="blog-card-title">{{ $item->title }}</h3>
                            </div>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif
    </div>
@endsection
