@extends('layout')

{{-- Новость на сайте (PublicNewsController): текст в стилях статьи блога, видео — в нашем плеере --}}
@section('title', $item->title . ' — новости Serdal')
@section('description', $item->excerpt(160) ?: 'Новости платформы Serdal')
@section('og_type', 'article')
{{-- Превью ссылки в соцсетях: первая картинка из текста новости (фото или обложка видео), иначе — общая картинка сайта --}}
@if($ogImage = $item->firstImage())
    @section('og_image', $ogImage)
@endif
@section('meta')
    <meta property="article:published_time" content="{{ $item->published_at->toAtomString() }}">
    <meta property="article:modified_time" content="{{ $item->updated_at->toAtomString() }}">
@endsection

@push('jsonld')
    {!! \App\Support\Seo::jsonLd(\App\Support\Seo::breadcrumbs([
        ['name' => 'Новости', 'url' => \App\Support\Seo::url(route('news.index', [], false))],
        ['name' => $item->title, 'url' => \App\Support\Seo::canonical()],
    ])) !!}
    {!! \App\Support\Seo::jsonLd([
        '@type' => 'NewsArticle',
        'headline' => $item->title,
        'description' => $item->excerpt(200),
        'image' => $ogImage ?: null,
        'inLanguage' => 'ru-RU',
        'datePublished' => $item->published_at->toAtomString(),
        'dateModified' => $item->updated_at->toAtomString(),
        'author' => ['@id' => \App\Support\Seo::url('#organization')],
        'publisher' => ['@id' => \App\Support\Seo::url('#organization')],
        'mainEntityOfPage' => \App\Support\Seo::canonical(),
    ]) !!}
@endpush

@section('styles')
    <link href="/css/blog.css?v={{ filemtime(public_path('css/blog.css')) }}" rel="stylesheet" type="text/css">
    @if(str_contains((string) $item->body, '<video'))
        {{-- Видео — наш плеер записей (Alpine на странице без Livewire — с CDN, плеер регистрируется на alpine:init) --}}
        @vite(['resources/css/player.css', 'resources/js/player.js'])
    @endif
@endsection

@section('content')
    <div class="content blog-content">
        <article class="blog-article">
            <nav class="blog-crumbs" aria-label="Навигационная цепочка">
                <a href="{{ url('/') }}">Главная</a><span aria-hidden="true">›</span>
                <a href="{{ route('news.index') }}">Новости</a>
            </nav>
            <h1 class="blog-article-title">{{ $item->title }}</h1>
            <div class="blog-meta"><time datetime="{{ $item->published_at->toAtomString() }}">{{ \App\Support\HumanDate::date($item->published_at) }}</time> · Команда Serdal</div>
            @if($body = \App\Support\RichText::html($item->body))
                <div class="blog-body">{!! \App\Support\RichText::players($body) !!}</div>
            @endif
        </article>

        @if($more->isNotEmpty())
            <section class="blog-more" aria-labelledby="news-more-title">
                <h2 id="news-more-title" class="blog-more-title">Другие новости</h2>
                <div class="news-list">
                    @foreach($more as $other)
                        <a href="{{ $other->url }}" class="news-item">
                            <time class="blog-meta" datetime="{{ $other->published_at->toAtomString() }}">{{ \App\Support\HumanDate::date($other->published_at) }}</time>
                            <h3 class="news-item-title">{{ $other->title }}</h3>
                        </a>
                    @endforeach
                </div>
                <a href="{{ route('news.index') }}" class="news-all">Все новости</a>
            </section>
        @endif
    </div>
@endsection
