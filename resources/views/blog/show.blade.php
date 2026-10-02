@extends('layout')

@php($authorFeed = $post->author?->username ? route('blog.author', $post->author->username) : null)
{{-- Тема в крошках — самый наполненный тег статьи, и только если его страница открыта для поиска (не меньше TAG_MIN_POSTS статей) --}}
@php($crumbTag = $post->tags()->withCount(['posts as published_count' => fn ($q) => $q->published()])->orderByDesc('published_count')->orderBy('name')->first())
@php($crumbTag = $crumbTag && $crumbTag->published_count >= \App\Services\BlogService::TAG_MIN_POSTS ? $crumbTag : null)
@section('title', $post->title . ' — блог Serdal')
@section('description', $post->description())
@section('og_type', 'article')
@section('livewire', '1')
@if($post->cover_url)
    @section('og_image', $post->cover_url)
@endif
@if(! $post->isPublished())
    @section('robots', 'noindex, nofollow')
@endif
@section('meta')
    @if($post->published_at)<meta property="article:published_time" content="{{ $post->published_at->toAtomString() }}">@endif
    <meta property="article:modified_time" content="{{ $post->updated_at->toAtomString() }}">
    <meta property="article:author" content="{{ $post->authorName() }}">
    @foreach($post->tags as $tag)<meta property="article:tag" content="{{ $tag->name }}">
    @endforeach
    @if($post->isPublished())<link rel="alternate" type="text/markdown" title="{{ $post->title }} — Markdown" href="{{ \App\Support\Seo::url(route('blog.markdown', $post->slug, false)) }}">@endif
    <link rel="alternate" type="application/rss+xml" title="Блог Serdal" href="{{ \App\Support\Seo::url(route('blog.rss', [], false)) }}">
@endsection

@push('jsonld')
    {!! \App\Support\Seo::jsonLd(\App\Support\Seo::breadcrumbs([
        ['name' => 'Блог', 'url' => \App\Support\Seo::url(route('blog.index', [], false))],
        ...($crumbTag ? [['name' => $crumbTag->name, 'url' => \App\Support\Seo::url(route('blog.tag', $crumbTag->slug, false))]] : []),
        ['name' => $post->title, 'url' => \App\Support\Seo::canonical()],
    ])) !!}
    {!! \App\Support\Seo::jsonLd(array_filter([
        '@type' => 'BlogPosting',
        'headline' => $post->title,
        'description' => $post->description(200),
        'image' => $post->cover_url,
        'keywords' => $post->tags->pluck('name')->implode(', ') ?: null,
        'wordCount' => $post->wordCount() ?: null,
        'timeRequired' => 'PT' . $post->readingMinutes() . 'M',
        'inLanguage' => 'ru-RU',
        'datePublished' => optional($post->published_at)->toAtomString(),
        'dateModified' => $post->updated_at->toAtomString(),
        'author' => $post->author
            ? array_filter(['@type' => 'Person', 'name' => $post->author->name, 'url' => $authorProfile ?? $authorFeed])
            : ['@id' => \App\Support\Seo::url('#organization')],
        'publisher' => ['@id' => \App\Support\Seo::url('#organization')],
        'mainEntityOfPage' => \App\Support\Seo::canonical(),
        'isPartOf' => ['@id' => \App\Support\Seo::url('#website')],
    ])) !!}
@endpush

@section('styles')
    <link href="/css/blog.css?v={{ filemtime(public_path('css/blog.css')) }}" rel="stylesheet" type="text/css">
    {{-- Видео в статье — наш плеер записей (RichText::players): его стили и скрипт --}}
    @if(str_contains((string) $post->body, '<video'))
        @vite(['resources/css/player.css', 'resources/js/player.js'])
    @endif
@endsection

@section('content')
    <div class="content blog-content">
        <article class="blog-article">
            <nav class="blog-crumbs" aria-label="Навигационная цепочка">
                <a href="{{ url('/') }}">Главная</a><span aria-hidden="true">›</span>
                <a href="{{ route('blog.index') }}">Блог</a>
                @if($crumbTag)<span aria-hidden="true">›</span><a href="{{ $crumbTag->url }}">{{ $crumbTag->name }}</a>@endif
            </nav>
            <h1 class="blog-article-title">{{ $post->title }}</h1>
            <div class="blog-meta">
                @if($post->author)@if($authorFeed)<a href="{{ $authorFeed }}">{{ $post->author->name }}</a>@else{{ $post->author->name }}@endif · @endif@if($post->published_at)<time datetime="{{ $post->published_at->toAtomString() }}">{{ \App\Support\HumanDate::date($post->published_at) }}</time>@else Черновик@endif · {{ $post->readingMinutes() }} мин чтения@if($post->comments_count) · <a href="#comments">{{ plural_ru($post->comments_count, 'комментарий', 'комментария', 'комментариев') }}</a>@endif
            </div>
            @if($post->cover_url)
                <img src="{{ $post->cover_url }}" alt="{{ $post->title }}" class="blog-article-cover">
            @endif
            @if($post->body)
                <div class="blog-body">{!! \App\Support\RichText::players(\App\Support\RichText::html($post->body)) !!}</div>
            @endif

            @if($post->tags->isNotEmpty())
                <div class="blog-tags">
                    @foreach($post->tags as $item)
                        <a href="{{ $item->url }}" class="blog-tag">{{ $item->name }}</a>
                    @endforeach
                </div>
            @endif

            @if($post->isPublished())
                <div class="blog-reactions"><livewire:blog.like-button :post-id="$post->id" /></div>
            @endif

            @if($post->author)
                <div class="blog-author-card">
                    @include('partials.userpic', ['user' => $post->author, 'class' => 'blog-author-head-photo', 'thumb' => false])
                    <div class="blog-author-card-text">
                        <div class="blog-meta">Автор статьи</div>
                        <div class="blog-author-card-name">{{ $post->author->name }}</div>
                        @if($post->isPublished())<livewire:blog.follow-button :author-id="$post->author->id" :return-url="$post->url" />@endif
                        <div class="blog-author-card-links">
                            @if($authorFeed)<a href="{{ $authorFeed }}">Все статьи автора</a>@endif
                            @if($authorProfile)<a href="{{ $authorProfile }}">Записаться на занятие</a>@endif
                        </div>
                    </div>
                </div>
            @endif

            @if($post->isPublished())
                <livewire:blog.comments :post-id="$post->id" />
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
                        @php($plain = $plain ?? 0)
                        @include('blog.partials.card', ['post' => $item, 'titleTag' => 'h3', 'style' => ['fill', 'outline', 'mint'][$plain % 3]])
                        @php($plain += $item->cover_url ? 0 : 1)
                    @endforeach
                </div>
            </section>
        @endif
    </div>
@endsection
