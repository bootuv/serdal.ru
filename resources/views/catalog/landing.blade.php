@extends('layout')

@section('title', $title)
@section('description', $description)
@if($robots)
  @section('robots', $robots)
@endif

@push('jsonld')
  {!! \App\Support\Seo::jsonLd(\App\Support\Seo::breadcrumbs($breadcrumbs)) !!}
  {!! \App\Support\Seo::jsonLd(\App\Support\Seo::tutorCollection($heading . ' онлайн', $description, $tutors, $stats)) !!}
@endpush

@section('content')
  <section class="catalog-head">
    @include('partials.breadcrumbs', ['items' => $breadcrumbs])
    <h1 class="h1 catalog-head__title">{{ $heading }} онлайн</h1>
    <p class="catalog-head__facts p24">{{ implode(' · ', $facts) }}</p>
    <p class="catalog-head__lead p18">
      Занятия проходят в браузере: видеосвязь, интерактивная доска, демонстрация экрана и запись урока.
      Выберите учителя, откройте его страницу и договоритесь о занятиях напрямую.
    </p>
  </section>

  <section class="specialists catalog-list">
    <div class="specialists-list">
      @foreach($tutors as $specialist)
        @include('partials.specialist-item', ['specialist' => $specialist, 'lessonFormats' => []])
      @endforeach
    </div>
  </section>

  @if($related)
    <section class="catalog-links">
      @foreach($related as $group)
        <div class="catalog-links__group">
          <h2 class="catalog-links__title p24">{{ $group['title'] }}</h2>
          <ul class="catalog-links__list" role="list">
            @foreach($group['links'] as $link)
              <li><a href="{{ $link['url'] }}" class="direction-tag catalog-links__link p18">{{ $link['name'] }} <span class="catalog-links__count">{{ $link['count'] }}</span></a></li>
            @endforeach
          </ul>
        </div>
      @endforeach
    </section>
  @endif

  @include('partials.faq', ['faq' => $faq])
@endsection
