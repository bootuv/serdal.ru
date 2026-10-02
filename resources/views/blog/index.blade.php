@extends('layout')

@php
    $base = match (true) {
        (bool) $tag => route('blog.tag', $tag->slug, false),
        (bool) $author => route('blog.author', $author->username, false),
        default => route('blog.index', [], false),
    };
    $heading = $tag ? $tag->name : ($author ? $author->name : 'Блог Serdal');
    $pageSuffix = $posts->currentPage() > 1 ? ' — страница ' . $posts->currentPage() : '';
@endphp

@section('title', match (true) {
    (bool) $tag => $tag->name . ' — статьи в блоге Serdal',
    (bool) $author => $author->name . ' — статьи в блоге Serdal',
    default => 'Блог об образовании — Serdal',
} . $pageSuffix)
@section('description', match (true) {
    (bool) $tag => 'Статьи по теме «' . $tag->name . '» в блоге Serdal: образование, подготовка к экзаменам, занятия онлайн.',
    (bool) $author => 'Статьи учителя ' . $author->name . ' в блоге Serdal.',
    default => 'Статьи об образовании: подготовка к ОГЭ и ЕГЭ, дистанционные и дополнительные занятия, советы учителям и родителям от команды и учителей Serdal.',
})
{{-- «Новые» — та же лента в другом порядке: канонический адрес без sort (в robots.txt sort — Clean-param), страницы — со своим номером --}}
@if($posts->currentPage() > 1)
    @section('canonical', \App\Support\Seo::url($base . '?page=' . $posts->currentPage()))
@endif
{{-- Тема с одной статьей — тонкая страница, повторяет саму статью: в поиск не отдаем, ссылки обходим --}}
@if($tag && $posts->total() < \App\Services\BlogService::TAG_MIN_POSTS)
    @section('robots', 'noindex, follow')
@endif
@section('meta')
    <link rel="alternate" type="application/rss+xml" title="Блог Serdal" href="{{ \App\Support\Seo::url(route('blog.rss', [], false)) }}">
    @if($posts->currentPage() > 1)<link rel="prev" href="{{ \App\Support\Seo::url($base . ($posts->currentPage() > 2 ? '?page=' . ($posts->currentPage() - 1) : '')) }}">@endif
    @if($posts->hasMorePages())<link rel="next" href="{{ \App\Support\Seo::url($base . '?page=' . ($posts->currentPage() + 1)) }}">@endif
@endsection

@push('jsonld')
    {!! \App\Support\Seo::jsonLd(\App\Support\Seo::breadcrumbs(array_values(array_filter([
        ['name' => 'Блог', 'url' => \App\Support\Seo::url(route('blog.index', [], false))],
        ($tag || $author) ? ['name' => $heading, 'url' => \App\Support\Seo::url($base)] : null,
    ])))) !!}
    {!! \App\Support\Seo::jsonLd([
        '@type' => 'Blog',
        'name' => ($tag || $author) ? 'Блог Serdal — ' . $heading : 'Блог Serdal',
        'url' => \App\Support\Seo::url($base),
        'inLanguage' => 'ru-RU',
        'publisher' => ['@id' => \App\Support\Seo::url('#organization')],
        'blogPost' => $posts->map(fn ($p) => ['@type' => 'BlogPosting', 'headline' => $p->title, 'url' => $p->url, 'datePublished' => $p->published_at->toAtomString()])->values()->all(),
    ]) !!}
    @if($author)
        {!! \App\Support\Seo::jsonLd([
            '@type' => 'ProfilePage',
            'url' => \App\Support\Seo::url($base),
            'mainEntity' => array_filter([
                '@type' => 'Person',
                'name' => $author->name,
                'url' => $authorProfile,
                'image' => $author->avatar ? $author->avatarJpgUrl : null,
                'jobTitle' => 'Репетитор',
            ]),
        ]) !!}
    @endif
@endpush

@section('styles')
    <link href="/css/blog.css?v={{ filemtime(public_path('css/blog.css')) }}" rel="stylesheet" type="text/css">
@endsection

@section('content')
    <div class="content blog-content">
        <div class="blog-head">
            @if($tag || $author)
                <nav class="blog-crumbs" aria-label="Навигационная цепочка">
                    <a href="{{ url('/') }}">Главная</a><span aria-hidden="true">›</span>
                    <a href="{{ route('blog.index') }}">Блог</a><span aria-hidden="true">›</span>
                    <span>{{ $tag ? 'Темы' : 'Авторы' }}</span>
                </nav>
            @endif
            @if($author)
                <div class="blog-author-head">
                    @include('partials.userpic', ['user' => $author, 'class' => 'blog-author-head-photo', 'thumb' => false, 'alt' => $author->name])
                    <div>
                        <h1 class="blog-title">{{ $author->name }}</h1>
                        <div class="blog-meta">{{ plural_ru($posts->total(), 'статья', 'статьи', 'статей') }} в блоге@if($authorProfile) · <a href="{{ $authorProfile }}">Страница учителя</a>@endif</div>
                    </div>
                </div>
            @else
                <h1 class="blog-title">{{ $tag ? '#' . $tag->name : $heading }}</h1>
                @unless($tag)
                    <p class="blog-lead">Опыт, который не найти в учебниках</p>
                @endunless
            @endif
        </div>

        <nav class="blog-tabs" aria-label="Порядок статей">
            <a href="{{ url($base) }}" @class(['active' => $sort === 'popular']) {!! $sort === 'popular' ? 'aria-current="page"' : '' !!}>Популярные</a>
            <a href="{{ url($base) }}?sort=new" @class(['active' => $sort === 'new']) {!! $sort === 'new' ? 'aria-current="page"' : '' !!}>Новые</a>
        </nav>

        <div class="blog-layout">
            <div class="blog-main">
                @if($posts->isEmpty())
                    <p class="blog-empty">Скоро здесь появятся первые статьи.</p>
                @else
                    <div class="blog-grid">
                        @foreach($posts as $post)
                            @php($plain = $plain ?? 0)
                        @include('blog.partials.card', ['post' => $post, 'style' => ['fill', 'outline', 'mint'][$plain % 3]])
                        @php($plain += $post->cover_url ? 0 : 1)
                        @endforeach
                    </div>

                    @if($posts->hasPages())
                        <nav class="blog-pages" aria-label="Страницы блога">
                            @if($posts->previousPageUrl())<a href="{{ $posts->previousPageUrl() }}" rel="prev">← Новее</a>@else<span></span>@endif
                            <span>Страница {{ $posts->currentPage() }} из {{ $posts->lastPage() }}</span>
                            @if($posts->nextPageUrl())<a href="{{ $posts->nextPageUrl() }}" rel="next">Раньше →</a>@else<span></span>@endif
                        </nav>
                    @endif
                @endif
            </div>

            @include('blog.partials.sidebar')
        </div>
    </div>
@endsection
