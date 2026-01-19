@extends('layout')

@section('content')
  <section class="header underline">
    <a href="/" class="logo-wrapper w-inline-block"><img src="images/Logo.svg" loading="lazy" width="Auto" height="32"
        alt="" class="logo"></a>
    <div class="menu-wrapper">
      <div class="main-menu">
        <a href="#" target="_blank" class="p24">О нас</a>
        <a href="{{ route('reviews') }}" class="p24">Отзывы</a>
        <a href="https://room.serdal.ru/signin" target="_blank" class="p24">Войти</a>
      </div>
      <div data-w-id="a8ac7203-c22a-a2cb-1d14-2d129698914f" class="burger-menu-wrapper"><img src="images/burger.svg"
          loading="lazy" width="32" height="32" alt="" class="burger-menu"></div>
    </div>
  </section>
  <section class="page-title-section">
    <h1 class="h1">Отзывы</h1>
    <p class="p30 page-descriptions">Учителя, менторы и ученики рассказывают о своем опыте преподавания и обучения на
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
        <a href="#" class="tab w-inline-block" data-filter="mentor">
          <div class="p24">Менторы</div>
        </a>
      </div>
    </div>
    <div r-masonry-gap="16" r-masonry-layout="1" r-masonry-column-min="500" class="reviews">
      @foreach($reviews as $review)
        @if($review->user->role === App\Models\User::ROLE_STUDENT)
          <div class="review-item" data-role="student">
            <div class="review-item-user">
              <img src="{{ $review->user->avatarUrl }}" loading="lazy" alt="" class="list-item-userpic">
              <div class="list-item-name-bio">
                <div class="user-type">{{ $review->user->displayRole }}</div>
                <div class="p24-medium">{{ $review->user->name }}</div>
              </div>
            </div>
            <p class="p24">{{ $review->text }}</p>
          </div>
        @endif
      @endforeach
    </div>
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', function () {
      const tabs = document.querySelectorAll('.tab[data-filter]');
      const reviewItems = document.querySelectorAll('.review-item[data-role]');

      tabs.forEach(tab => {
        tab.addEventListener('click', function (e) {
          e.preventDefault();

          // Убираем активный класс со всех табов
          tabs.forEach(t => t.classList.remove('active'));
          // Добавляем активный класс к текущему табу
          this.classList.add('active');

          const filter = this.getAttribute('data-filter');

          reviewItems.forEach(item => {
            const role = item.getAttribute('data-role');

            if (filter === 'all') {
              // "Все отзывы" - показываем только учеников (т.к. преподаватели и менторы не реализованы)
              item.style.display = role === 'student' ? '' : 'none';
            } else if (filter === 'student') {
              // Ученики
              item.style.display = role === 'student' ? '' : 'none';
            } else {
              // Преподаватели и Менторы - пока пусто
              item.style.display = 'none';
            }
          });
        });
      });
    });
  </script>
@endsection