@extends('layout')

@section('title', $article->title . ' - Центр помощи Serdal')

@section('meta')
    <meta name="description" content="{{ $article->excerpt ?: $article->title . ' — инструкция по платформе Serdal' }}">
    <meta property="og:title" content="{{ $article->title }} - Центр помощи Serdal">
    @if($article->excerpt)
        <meta property="og:description" content="{{ $article->excerpt }}">
    @endif
@endsection

@section('styles')
    <link href="/css/help.css" rel="stylesheet" type="text/css">
@endsection

@section('content')

    <div class="content help-content reviews-content help-inner">
        <div>
            <div class="help-breadcrumbs">
                <a href="{{ route('help.index') }}">Центр помощи</a>
                <span>›</span>
                <a href="{{ $category->audience_url }}">{{ $category->audience_label }}</a>
                <span>›</span>
                <a href="{{ $category->url }}">{{ $category->name }}</a>
                <span>›</span>
                <span>{{ $article->title }}</span>
            </div>
            <a href="{{ $category->url }}" class="help-crumb-back">← {{ $category->name }}</a>
        </div>

        <div class="help-layout" x-data="{ navOpen: false }">
            <div class="help-sidebar-overlay" :class="{ 'open': navOpen }" @click="navOpen = false"></div>
            @include('help.partials.sidebar', [
                'sidebarCategories' => $sidebarCategories,
                'currentCategory' => $category,
                'currentArticle' => $article,
            ])

            <main class="help-main">
                <button type="button" class="help-nav-toggle" @click="navOpen = true">☰&nbsp; Содержание</button>
                <h1 class="help-main-title">{{ $article->title }}</h1>
                @if($article->excerpt)
                    <p class="p18 help-main-description">{{ $article->excerpt }}</p>
                @endif

                @if($article->video_file)
                    <div class="help-article-video">
                        <video controls playsinline preload="metadata"
                            src="{{ $article->video_file_url }}"></video>
                    </div>
                @elseif($article->embed_url)
                    <div class="help-article-video">
                        <iframe src="{{ $article->embed_url }}"
                            allow="autoplay; fullscreen; picture-in-picture; encrypted-media; gyroscope; accelerometer; clipboard-write; screen-wake-lock;"
                            allowfullscreen loading="lazy"></iframe>
                    </div>
                @endif

                @if($article->content)
                    <div class="help-article-body">
                        {!! $article->content !!}
                    </div>
                @endif

                @if($nextArticle)
                    <div class="help-next-article">
                        <div class="help-next-article-label">Следующая статья</div>
                        <a href="{{ $nextArticle->url }}" class="help-next-article-link">{{ $nextArticle->title }} →</a>
                    </div>
                @endif
            </main>
        </div>
    </div>
@endsection
