@extends('layout')

@section('content')

  <section class="page-title-section">
    <h1 class="h1">Отзывы</h1>
    <p class="p30 page-descriptions">Учителя и ученики рассказывают о своем опыте преподавания и обучения на
      нашей платформе, делятся впечатлениями от образовательного процесса.</p>
  </section>
  <div class="content reviews-content">
    <div class="tabs-wrapper">
      <div class="tabs">
        <a href="#" class="tab active w-inline-block" data-filter="all">
          <div class="p24">Все отзывы</div>
        </a>
        <a href="#" class="tab w-inline-block" data-filter="student">
          <div class="p24">Ученики</div>
        </a>
        <a href="#" class="tab w-inline-block" data-filter="tutor">
          <div class="p24">Преподаватели</div>
        </a>
      </div>
    </div>
    <div class="reviews" id="reviews-container">
      @foreach($reviews as $review)
        @include('partials.review-item', ['review' => $review])
      @endforeach
    </div>

    @if($hasMore)
      <div id="load-trigger" data-offset="20" style="height: 1px;"></div>
    @endif
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', function () {
      const tabs = document.querySelectorAll('.tab[data-filter]');
      const container = document.getElementById('reviews-container');
      const loadTrigger = document.getElementById('load-trigger');
      let currentFilter = 'all';

      // Функция применения фильтра
      const applyFilter = () => {
        const reviewItems = container.querySelectorAll('.review-item[data-role]');
        reviewItems.forEach(item => {
          const role = item.getAttribute('data-role');
          if (currentFilter === 'all' || currentFilter === 'student') {
            item.style.display = role === 'student' ? '' : 'none';
          } else {
            item.style.display = 'none';
          }
        });
      };

      // Tab filtering
      tabs.forEach(tab => {
        tab.addEventListener('click', function (e) {
          e.preventDefault();
          tabs.forEach(t => t.classList.remove('active'));
          this.classList.add('active');
          currentFilter = this.getAttribute('data-filter');
          applyFilter();
        });
      });

      // Infinite scroll
      if (loadTrigger) {
        let isLoading = false;

        const loadMore = () => {
          if (isLoading) return;
          isLoading = true;

          const offset = parseInt(loadTrigger.getAttribute('data-offset'));

          fetch(`/reviews/load-more?offset=${offset}`)
            .then(response => response.json())
            .then(data => {
              container.insertAdjacentHTML('beforeend', data.html);
              applyFilter(); // Применяем фильтр к новым элементам

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