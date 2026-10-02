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

@if($author)
    @section('livewire', '1')
    @if($posts->total() === 0)
        @section('robots', 'noindex, nofollow')
    @endif
@endif

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
                    @include('partials.userpic', ['user' => $author, 'class' => 'blog-author-head-photo blog-author-head-photo--big', 'thumb' => false, 'alt' => $author->name])
                    <div class="blog-author-head-text">
                        <h1 class="blog-title">{{ $author->name }}</h1>
                        {{-- Факты одной строкой, ниже — только действия --}}
                        @php($followers = app(\App\Services\BlogService::class)->followersCount($author))
                        <div class="blog-author-head-meta">
                            <span>{{ plural_ru($posts->total(), 'статья', 'статьи', 'статей') }}</span>
                            <span x-data="{ label: @js(plural_ru($followers, 'подписчик', 'подписчика', 'подписчиков')) }" x-on:blog-followers.window="label = $event.detail.label" x-text="label">{{ plural_ru($followers, 'подписчик', 'подписчика', 'подписчиков') }}</span>
                            @if($authorProfile)<span><a href="{{ $authorProfile }}">Страница учителя</a></span>@endif
                        </div>
                        {{-- Свой блог: «Написать статью» и «Мои статьи и черновики» — в личной карточке справа --}}
                        @unless(auth()->id() === $author->id)
                            <livewire:blog.follow-button :author-id="$author->id" :return-url="url($base)" />
                        @endunless
                    </div>
                </div>
            @else
                <h1 class="blog-title">{{ $tag ? '#' . $tag->name : $heading }}</h1>
                @unless($tag)
                    <p class="blog-lead">Опыт, который не найти в учебниках</p>
                @endunless
            @endif
        </div>

        {{-- Есть подписки — «Моя лента» первая; по умолчанию открывается она, если в ней есть свежее, иначе «Популярные» --}}
        <nav class="blog-tabs" aria-label="Порядок статей">
            @if($hasFeed)
                <a href="{{ url($base) }}{{ $defaultSort === 'feed' ? '' : '?sort=feed' }}" @class(['active' => $sort === 'feed']) {!! $sort === 'feed' ? 'aria-current="page"' : '' !!}>Моя лента</a>
            @endif
            <a href="{{ url($base) }}{{ $defaultSort === 'popular' ? '' : '?sort=popular' }}" @class(['active' => $sort === 'popular']) {!! $sort === 'popular' ? 'aria-current="page"' : '' !!}>Популярные</a>
            <a href="{{ url($base) }}?sort=new" @class(['active' => $sort === 'new']) {!! $sort === 'new' ? 'aria-current="page"' : '' !!}>Новые</a>
        </nav>

        <div class="blog-layout">
            <div class="blog-main">
                @if($posts->isEmpty())
                    <p class="blog-empty">{{ ($author && auth()->id() === $author->id) ? 'У вас пока нет опубликованных статей. Напишите первую — после проверки она появится здесь.' : ($sort === 'feed' ? 'Авторы, на которых вы подписаны, пока ничего не опубликовали. Загляните в «Популярные».' : 'Скоро здесь появятся первые статьи.') }}</p>
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
