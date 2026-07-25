@extends('layout')

@section('title', $category->name . ' - Центр помощи Serdal')

@section('meta')
    <meta name="description"
        content="{{ $category->description ?: 'Инструкции по использованию платформы Serdal: ' . $category->name }}">
    <meta property="og:title" content="{{ $category->name }} - Центр помощи Serdal">
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
                <span>{{ $category->name }}</span>
            </div>
            <a href="{{ $category->audience_url }}" class="help-crumb-back">← {{ $category->audience_label }}</a>
        </div>

        <div class="help-layout" x-data="{ navOpen: false }">
            <div class="help-sidebar-overlay" :class="{ 'open': navOpen }" @click="navOpen = false"></div>
            @include('help.partials.sidebar', [
                'sidebarCategories' => $sidebarCategories,
                'currentCategory' => $category,
                'currentArticle' => null,
            ])

            <main class="help-main">
                <button type="button" class="help-nav-toggle" @click="navOpen = true">☰&nbsp; Содержание</button>
                <h1 class="help-main-title">{{ $category->icon ? $category->icon . ' ' : '' }}{{ $category->name }}</h1>
                @if($category->description)
                    <p class="p18 help-main-description">{{ $category->description }}</p>
                @endif

                @if($articles->isEmpty())
                    <p class="p18" style="color: var(--gray); margin-top: 24px;">В этой категории пока нет инструкций.</p>
                @else
                    <div class="help-articles-list">
                        @foreach($articles as $article)
                            <a href="{{ $article->url }}" class="help-article-item">
                                <div class="help-article-item-text">
                                    <div class="p18-medium">@if($article->has_video)🎥 @endif{{ $article->title }}</div>
                                    @if($article->excerpt)
                                        <div class="p18 help-article-item-excerpt">{{ $article->excerpt }}</div>
                                    @endif
                                </div>
                                <div class="help-article-item-arrow">→</div>
                            </a>
                        @endforeach
                    </div>
                @endif
            </main>
        </div>
    </div>
@endsection
