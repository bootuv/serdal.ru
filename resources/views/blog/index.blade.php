@extends('layout')

@section('title', $posts->currentPage() > 1 ? 'Блог — страница ' . $posts->currentPage() . ' — Serdal' : 'Блог об образовании — Serdal')
@section('description', 'Статьи об образовании: подготовка к ОГЭ и ЕГЭ, дистанционные и дополнительные занятия, советы учителям и родителям от команды Serdal.')
@if($posts->currentPage() > 1)
    @section('canonical', \App\Support\Seo::url(route('blog.index', ['page' => $posts->currentPage()], false)))
@endif

@push('jsonld')
    {!! \App\Support\Seo::jsonLd(\App\Support\Seo::breadcrumbs([['name' => 'Блог', 'url' => \App\Support\Seo::url(route('blog.index', [], false))]])) !!}
    {!! \App\Support\Seo::jsonLd([
        '@type' => 'Blog',
        'name' => 'Блог Serdal',
        'url' => \App\Support\Seo::url(route('blog.index', [], false)),
        'inLanguage' => 'ru-RU',
        'publisher' => ['@id' => \App\Support\Seo::url('#organization')],
        'blogPost' => $posts->map(fn ($p) => ['@type' => 'BlogPosting', 'headline' => $p->title, 'url' => $p->url, 'datePublished' => $p->published_at->toAtomString()])->values()->all(),
    ]) !!}
@endpush

@section('styles')
    <link href="/css/blog.css?v={{ filemtime(public_path('css/blog.css')) }}" rel="stylesheet" type="text/css">
@endsection

@section('content')
    <div class="content blog-content">
        <div class="blog-hero">
            <h1 class="blog-title">Блог</h1>
            <p class="p18 blog-subtitle">Об образовании, подготовке к экзаменам и занятиях онлайн</p>
        </div>

        @if($posts->isEmpty())
            <p class="blog-empty">Скоро здесь появятся первые статьи.</p>
        @else
            <div class="blog-grid">
                @foreach($posts as $post)
                    <a href="{{ $post->url }}" class="blog-card">
                        @if($post->cover_url)
                            <img src="{{ $post->cover_url }}" alt="" loading="lazy" class="blog-card-cover">
                        @else
                            <div class="blog-card-cover blog-card-cover--empty"></div>
                        @endif
                        <div class="blog-card-text">
                            <div class="blog-meta">{{ \App\Support\HumanDate::date($post->published_at) }} · {{ $post->readingMinutes() }} мин чтения</div>
                            <h2 class="blog-card-title">{{ $post->title }}</h2>
                            <p class="blog-card-excerpt">{{ $post->description(180) }}</p>
                        </div>
                    </a>
                @endforeach
            </div>

            @if($posts->hasPages())
                <nav class="blog-pages" aria-label="Страницы блога">
                    @if($posts->previousPageUrl())<a href="{{ $posts->previousPageUrl() }}" rel="prev">← Новее</a>@endif
                    <span>Страница {{ $posts->currentPage() }} из {{ $posts->lastPage() }}</span>
                    @if($posts->nextPageUrl())<a href="{{ $posts->nextPageUrl() }}" rel="next">Раньше →</a>@endif
                </nav>
            @endif
        @endif
    </div>
@endsection
