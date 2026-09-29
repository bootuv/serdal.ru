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

  @include('partials.catalog-links', [
      'groups' => [
          ['title' => 'Предметы', 'links' => $subjects, 'icons' => true],
          ['title' => 'Направления', 'links' => $directs],
      ],
      'more' => ['name' => 'Все репетиторы с фильтрами по цене, рейтингу и классам', 'url' => url('/') . '#specialists'],
  ])

  @include('partials.faq', ['faq' => $faq])
@endsection
