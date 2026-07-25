@extends('layout')

@section('title', $audienceLabel . ' - Центр помощи Serdal')

@section('meta')
    <meta name="description"
        content="Центр помощи Serdal — {{ mb_strtolower($audienceLabel) }}: видеоинструкции и статьи по работе с платформой.">
    <meta property="og:title" content="{{ $audienceLabel }} - Центр помощи Serdal">
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
                <span>{{ $audienceLabel }}</span>
            </div>
            <a href="{{ route('help.index') }}" class="help-crumb-back">← Центр помощи</a>
        </div>

        <div>
            <h1 class="help-main-title">{{ $audienceLabel }}</h1>
            <p class="p18 help-main-description">Выберите категорию — или воспользуйтесь <a
                    href="{{ route('help.index') }}" class="text-link">поиском</a></p>
        </div>

        @if($categories->isEmpty())
            <p class="p24 help-empty">Инструкции скоро появятся.</p>
        @else
            <div class="help-categories-grid">
                @foreach($categories as $category)
                    @include('help.partials.category-card', ['category' => $category])
                @endforeach
            </div>
        @endif
    </div>
@endsection
