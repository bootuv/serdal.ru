@extends('layout')

@section('title', 'Центр помощи - Serdal')

@section('meta')
    <meta name="description"
        content="База знаний платформы Serdal: видеоинструкции и статьи по использованию платформы для учеников и репетиторов.">
    <meta property="og:title" content="Центр помощи - Serdal">
    <meta property="og:description" content="Видеоинструкции и статьи по использованию платформы Serdal">
@endsection

@section('styles')
    <link href="/css/help.css" rel="stylesheet" type="text/css">
@endsection

@section('content')

    <div class="content help-content reviews-content">

        <div class="help-hero">
            <h1 class="help-title">Чем вам помочь?</h1>
            <p class="p18 help-subtitle">Видеоинструкции и ответы на вопросы по работе с платформой</p>
        </div>

        <form action="{{ route('help.index') }}" method="GET" class="help-search-form">
            <input type="text" name="q" value="{{ $query }}" placeholder="Поиск по инструкциям…"
                class="help-search-input" aria-label="Поиск по инструкциям">
            <button type="submit" class="help-search-button">Найти</button>
        </form>

        @if($results !== null)

            @if($results->isEmpty())
                <p class="p24 help-empty">По запросу «{{ $query }}» ничего не найдено.<br>Попробуйте изменить запрос или <a
                        href="{{ route('help.index') }}" class="text-link">посмотрите все разделы</a>.</p>
            @else
                <div class="help-articles-list" style="margin-top: 0;">
                    @foreach($results as $article)
                        <a href="{{ $article->url }}" class="help-article-item">
                            <div class="help-article-item-text">
                                <div class="p18 help-article-item-category">
                                    {{ $article->category->audience_label }} · {{ $article->category->name }}
                                </div>
                                <div class="p24-medium">@if($article->has_video)🎥 @endif{{ $article->title }}</div>
                                @if($article->excerpt)
                                    <div class="p18 help-article-item-excerpt">{{ $article->excerpt }}</div>
                                @endif
                            </div>
                            <div class="help-article-item-arrow">→</div>
                        </a>
                    @endforeach
                </div>
            @endif

        @else

            <div class="help-sections-grid">
                <a href="{{ route('help.section', 'students') }}" class="content-card help-section-card">
                    <div class="help-category-icon">🎓</div>
                    <div class="h4" style="margin-bottom: 8px;">Для учеников</div>
                    <div class="p18 help-category-description">Как заниматься на платформе: занятия, домашние задания,
                        оплата</div>
                    <div class="p18 help-section-card-link">
                        {{ trans_choice('{0} Скоро появятся|{1} :count инструкция|[2,4] :count инструкции|[5,*] :count инструкций', $stats->get('student')['articles'] ?? 0) }}
                        →
                    </div>
                </a>
                <a href="{{ route('help.section', 'tutors') }}" class="content-card help-section-card">
                    <div class="help-category-icon">🧑‍🏫</div>
                    <div class="h4" style="margin-bottom: 8px;">Для репетиторов</div>
                    <div class="p18 help-category-description">Как вести занятия: расписание, ученики, материалы и
                        записи уроков</div>
                    <div class="p18 help-section-card-link">
                        {{ trans_choice('{0} Скоро появятся|{1} :count инструкция|[2,4] :count инструкции|[5,*] :count инструкций', $stats->get('tutor')['articles'] ?? 0) }}
                        →
                    </div>
                </a>
            </div>

        @endif
    </div>
@endsection
