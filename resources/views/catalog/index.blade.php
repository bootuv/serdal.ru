@extends('layout')

@php
  $heading = 'Репетиторы онлайн по предметам';
  $description = 'Онлайн-репетиторы ' . \App\Support\Seo::siteName() . ' по школьным предметам, языкам и подготовке к ЕГЭ, ОГЭ и олимпиадам: '
      . implode(', ', $facts) . '. Выберите предмет или направление и сравните учителей по цене и отзывам.';
  $breadcrumbs = [['name' => 'Репетиторы', 'url' => route('catalog.index')]];
@endphp

@section('title', $heading . ' — ' . plural_ru($stats['count'], 'репетитор', 'репетитора', 'репетиторов') . ' | ' . \App\Support\Seo::siteName())
@section('description', $description)

@push('jsonld')
  {!! \App\Support\Seo::jsonLd(\App\Support\Seo::breadcrumbs($breadcrumbs)) !!}
  {!! \App\Support\Seo::jsonLd([
      '@type' => 'CollectionPage',
      'name' => $heading,
      'description' => $description,
      'url' => \App\Support\Seo::canonical(),
      'inLanguage' => 'ru-RU',
      'isPartOf' => ['@id' => \App\Support\Seo::url('#website')],
      'hasPart' => collect($subjects)->merge($directs)->map(fn ($page) => [
          '@type' => 'CollectionPage',
          'name' => $page['heading'] . ' онлайн',
          'url' => $page['url'],
      ])->values()->all(),
  ]) !!}
@endpush

@section('content')
  <section class="catalog-head">
    @include('partials.breadcrumbs', ['items' => $breadcrumbs])
    <h1 class="h1 catalog-head__title">{{ $heading }}</h1>
    <p class="catalog-head__facts p24">{{ implode(' · ', $facts) }}</p>
  </section>

  <section class="catalog-links catalog-links--hub">
    @foreach(['Предметы' => $subjects, 'Направления' => $directs] as $groupTitle => $pages)
      @if($pages)
        <div class="catalog-links__group">
          <h2 class="catalog-links__title p24">{{ $groupTitle }}</h2>
          <ul class="catalog-links__list" role="list">
            @foreach($pages as $page)
              <li><a href="{{ $page['url'] }}" class="direction-tag catalog-links__link p18">{{ $page['name'] }} <span class="catalog-links__count">{{ $page['count'] }}</span></a></li>
            @endforeach
          </ul>
        </div>
      @endif
    @endforeach
    <p class="catalog-links__more p18"><a href="{{ url('/') }}#specialists">Все репетиторы с фильтрами по цене, рейтингу и классам</a></p>
  </section>

  @include('partials.faq', ['faq' => $faq])
@endsection
