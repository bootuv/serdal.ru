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
    @if($icon)
      <div class="catalog-head__heading">
        {!! $icon !!}
        <h1 class="h1 catalog-head__title">{{ $heading }} онлайн</h1>
      </div>
    @else
      <h1 class="h1 catalog-head__title">{{ $heading }} онлайн</h1>
    @endif
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

  @include('partials.catalog-links', ['groups' => $related])

  @include('partials.faq', ['faq' => $faq])
@endsection
