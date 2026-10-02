@extends('layout')

{{-- «Новости Serdal» на сайте: новости из админки с отметкой «На сайте» (PublicNewsController) --}}
@php($pageSuffix = $news->currentPage() > 1 ? ' — страница ' . $news->currentPage() : '')
@section('title', 'Новости Serdal' . $pageSuffix)
@section('description', 'Новости платформы Serdal: новые возможности для учителей и учеников, изменения и события.')
@if($news->currentPage() > 1)
    @section('canonical', \App\Support\Seo::url(route('news.index', [], false) . '?page=' . $news->currentPage()))
@endif

@section('styles')
    <link href="/css/blog.css?v={{ filemtime(public_path('css/blog.css')) }}" rel="stylesheet" type="text/css">
@endsection

@section('content')
    <div class="content blog-content">
        <div class="blog-head">
            <nav class="blog-crumbs" aria-label="Навигационная цепочка">
                <a href="{{ url('/') }}">Главная</a><span aria-hidden="true">›</span><span>Новости</span>
            </nav>
            <h1 class="blog-title">Новости Serdal</h1>
            <p class="blog-lead">Что нового на платформе</p>
        </div>

        @if($news->isEmpty())
            <p class="blog-empty">Новостей пока нет.</p>
        @else
            <div class="news-list">
                @foreach($news as $item)
                    <a href="{{ $item->url }}" class="news-item">
                        <time class="blog-meta" datetime="{{ $item->published_at->toAtomString() }}">{{ \App\Support\HumanDate::date($item->published_at) }}</time>
                        <h2 class="news-item-title">{{ $item->title }}</h2>
                        @if($excerpt = $item->excerpt(220))<p class="news-item-text">{{ $excerpt }}</p>@endif
                    </a>
                @endforeach
            </div>
            @if($news->hasPages())
                <nav class="blog-pages" aria-label="Страницы новостей">
                    @if($news->previousPageUrl())<a href="{{ $news->previousPageUrl() }}" rel="prev">← Новее</a>@else<span></span>@endif
                    <span>Страница {{ $news->currentPage() }} из {{ $news->lastPage() }}</span>
                    @if($news->nextPageUrl())<a href="{{ $news->nextPageUrl() }}" rel="next">Раньше →</a>@else<span></span>@endif
                </nav>
            @endif
        @endif
    </div>
@endsection
