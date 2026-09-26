@extends('layout')

@section('title', 'Отзывы учеников и учителей — Serdal')
@section('description', 'Отзывы учеников о занятиях с учителями и отзывы учителей о работе на платформе Serdal. Реальный опыт онлайн-обучения: подготовка к экзаменам, школьные предметы, языки.')

@push('jsonld')
    {!! \App\Support\Seo::jsonLd(\App\Support\Seo::breadcrumbs([['name' => 'Отзывы', 'url' => \App\Support\Seo::canonical()]])) !!}
@endpush

@section('content')

  <section class="page-title-section">
    <h1 class="h1">Отзывы</h1>
    <p class="p30 page-descriptions">Учителя и ученики рассказывают о своем опыте преподавания и обучения на
      нашей платформе, делятся впечатлениями от образовательного процесса.</p>
  </section>
  <div class="content reviews-content">
    <div class="tabs-wrapper">
      <div class="tabs">
        @foreach ([null => 'Все отзывы', 'student' => 'Ученики', 'tutor' => 'Учителя'] as $key => $label)
          <a href="{{ route('reviews', $key ? ['role' => $key] : []) }}" class="tab w-inline-block {{ ($role ?? null) === ($key ?: null) ? 'active' : '' }}">
            <div class="p24">{{ $label }}</div>
          </a>
        @endforeach
      </div>
    </div>
    <div class="reviews" id="reviews-container">
      @forelse($reviews as $review)
        @include('partials.review-item', ['review' => $review])
      @empty
        <p class="p24">{{ ($role ?? null) === 'tutor' ? 'Отзывы учителей появятся здесь совсем скоро.' : 'Отзывов пока нет.' }}</p>
      @endforelse
    </div>

    @if($hasMore)
      <div id="load-trigger" data-offset="20" style="height: 1px;"></div>
    @endif
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', function () {
      const container = document.getElementById('reviews-container');
      const loadTrigger = document.getElementById('load-trigger');
      const role = @json($role ?? null);

      // Подгрузка при прокрутке — с той же вкладкой (фильтр — на сервере)
      if (loadTrigger) {
        let isLoading = false;

        const loadMore = () => {
          if (isLoading) return;
          isLoading = true;

          const offset = parseInt(loadTrigger.getAttribute('data-offset'));
          const params = new URLSearchParams({ offset });
          if (role) params.set('role', role);

          fetch(`{{ route('reviews.load-more') }}?${params}`)
            .then(response => response.json())
            .then(data => {
              container.insertAdjacentHTML('beforeend', data.html);

              if (data.hasMore) {
                loadTrigger.setAttribute('data-offset', offset + 20);
                isLoading = false;
              } else {
                loadTrigger.remove();
              }
            })
            .catch(err => {
              console.error('Error loading reviews:', err);
              isLoading = false;
            });
        };

        const observer = new IntersectionObserver((entries) => {
          if (entries[0].isIntersecting) {
            loadMore();
          }
        }, { rootMargin: '200px' });

        observer.observe(loadTrigger);
      }
    });
  </script>
@endsection