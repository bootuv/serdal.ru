@extends('layout')

@section('title', \App\Support\SeoSettings::get('seo_home_title'))
@section('description', \App\Support\SeoSettings::get('seo_home_description'))
@section('canonical', \App\Support\Seo::url('/'))

@section('content')

  <section class="intro-wrapper">
    <div class="intro">
      <div class="video-background-section">
        <div class="video-wrapper">
          <div class="video-embed w-embed w-iframe"><iframe
              src="https://player.vimeo.com/video/1005192725?background=1&quality=1080p" width="100%" height="100%"
              frameborder="0" allow="autoplay; fullscreen" allowfullscreen=""></iframe></div>
        </div>
      </div>
      <div class="video-gradient"></div>
      <div class="title-block">
        <div class="title-description">
          <h1 class="h1 white-text">Платформа профессиональных репетиторов</h1>
          <div class="p30 white-text">Занимайтесь онлайн с опытными репетиторами и менторами — подберём специалиста
            под вашу цель.</div>
        </div>
      </div>
      <div class="main-actions">
        <a href="#specialists" class="main-button search-tutor w-button">Найти специалиста</a>
        <a data-w-id="79cbc6fb-829e-0080-5bfd-8999f35ce137" href="#" class="trailer-button w-inline-block">
          <div class="button-play"><img src="images/thumb.png" loading="lazy" width="76" height="76" alt=""
              class="trailer-button-thumb"><img src="images/play.svg" loading="lazy" alt="" class="play-icon">
            <div class="dark-opacity"></div>
          </div>
          <div class="trailet-button-text">Трейлер</div>
        </a>
      </div>
    </div>
  </section>
  <section class="directions-row">
    <ul role="list" class="directions">
      @foreach(App\Models\Direct::all() as $direct)
        <li class="direction">
          <div class="p30">{{ $direct->name }}</div>
        </li>
      @endforeach
      {{-- Дублирование вывода записей --}}
      @foreach(App\Models\Direct::all() as $direct)
        <li class="direction">
          <div class="p30">{{ $direct->name }}</div>
        </li>
      @endforeach
    </ul>
  </section>
  <section class="steps_wrapper">
    <div class="step">
      <div class="step-count">1</div>
      <div class="step-text">
        <h3 class="h3">Выберите специалиста</h3>
        <p class="p24">У нас вы найдете самых опытных преподавателей с большим стажем и отличной
          профессиональной репутацией.</p>
      </div>
    </div>
    <div class="step">
      <div class="step-count">2</div>
      <div class="step-text">
        <h3 class="h3">Свяжитесь<br>с ним</h3>
        <p class="p24">На странице специалиста вы можете найти контакты, через которые можно связаться с ним, обсудить
          детали и записаться на занятия.</p>
      </div>
    </div>
    <div class="step">
      <div class="step-count">3</div>
      <div class="step-text">
        <h3 class="h3">Приходите на онлайн занятия</h3>
        <p class="p24">Учитесь не выходя из дома. На нашей онлайн-платформе занятия не уступают по качеству живым урокам в
          классе, а где-то даже превосходят.</p>
      </div>
    </div>
  </section>
  <section class="specialists">
    <h2 id="specialists" class="h2">Найти специалиста</h2>
    <div class="filters-sentinel" aria-hidden="true"></div>
    @php
      $gradeOptions = ['preschool' => 'Дошкольники', 1 => '1', 2 => '2', 3 => '3', 4 => '4', 5 => '5', 6 => '6', 7 => '7', 8 => '8', 9 => '9', 10 => '10', 11 => '11', 'adults' => 'Взрослые'];
      $quickGroups = [
        ['id' => 'directs', 'type' => 'direct', 'name' => 'Направления', 'options' => App\Models\Direct::all()->pluck('name', 'id')->all()],
        ['id' => 'subjects', 'type' => 'subject', 'name' => 'Предметы', 'options' => App\Models\Subject::all()->pluck('name', 'id')->all()],
        ['id' => 'grades', 'type' => 'grade', 'name' => 'Классы', 'options' => collect($gradeOptions)->map(fn ($label) => is_numeric($label) ? "$label класс" : $label)->all()],
      ];
      $lessonFormatOptions = [App\Models\LessonType::TYPE_INDIVIDUAL => 'Индивидуальные', App\Models\LessonType::TYPE_GROUP => 'Групповые'];
      $ratingOptions = ['3' => '3 и выше', '4' => '4 и выше', '4.5' => '4,5 и выше'];
    @endphp
    <div class="filters-wrapper">
      <div class="filters">
        {{-- Быстрые фильтры: на телефоне скрыты, всё доступно через окно «Фильтры» --}}
        @foreach($quickGroups as $group)
          <div id="{{ $group['id'] }}" data-hover="false" data-delay="0" class="filter filter-quick w-dropdown" data-filter-group="{{ $group['type'] }}">
            <div class="filter-select w-dropdown-toggle">
              <div class="filter-counter">0</div>
              <div class="p24 filter-name">{{ $group['name'] }}</div>
              <div class="filter-icon w-icon-dropdown-toggle"></div>
            </div>
            <nav class="dropdown-list w-dropdown-list">
              <div class="dropdown-list-wrapper">
                @foreach($group['options'] as $value => $label)
                  <label class="p24 dropdown-list-item w-dropdown-link">
                    <input type="checkbox" class="filter-checkbox" data-filter-type="{{ $group['type'] }}" data-value="{{ $value }}">
                    {{ $label }}
                  </label>
                @endforeach
              </div>
            </nav>
          </div>
        @endforeach

        <button type="button" class="filter filter-button filters-open" data-open-filters>
          <span class="filter-select">
            <span class="filter-counter">0</span>
            <svg class="filter-button-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M3.5 5h17l-6.5 7.5v5.5l-4 2v-7.5L3.5 5z"/></svg>
            <span class="p24 filter-name">Фильтры</span>
          </span>
        </button>

        <button type="button" class="filters-reset p18" hidden>Сбросить всё</button>

        <div id="sort" data-hover="false" data-delay="0" class="filter filter-sort w-dropdown @if($sort !== App\Http\Controllers\IndexController::DEFAULT_SORT) selected @endif">
          <div class="filter-select w-dropdown-toggle">
            <svg class="sort-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M8 20V4M4.5 7.5 8 4l3.5 3.5M16 4v16m3.5-3.5L16 20l-3.5-3.5"/></svg>
            <div class="p24 filter-name sort-label">{{ $sorts[$sort] }}</div>
            <div class="filter-icon w-icon-dropdown-toggle"></div>
          </div>
          <nav class="dropdown-list w-dropdown-list">
            <div class="dropdown-list-wrapper">
              @foreach($sorts as $value => $label)
                <label class="p24 dropdown-list-item w-dropdown-link">
                  <input type="radio" name="sort" class="filter-radio sort-option" value="{{ $value }}" @checked($value === $sort)>
                  {{ $label }}
                </label>
              @endforeach
            </div>
          </nav>
        </div>
      </div>
    </div>

    {{-- Окно со всеми фильтрами --}}
    <div class="filters-modal" id="filters-modal" aria-hidden="true">
      <div class="filters-modal-backdrop" data-close-filters></div>
      <div class="filters-modal-window" role="dialog" aria-modal="true" aria-labelledby="filters-modal-title">
        <div class="filters-modal-head">
          <div class="h4" id="filters-modal-title">Фильтры</div>
          <button type="button" class="filters-modal-close" data-close-filters aria-label="Закрыть">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
          </button>
        </div>
        <div class="filters-modal-body">
          @foreach([
            ['type' => 'direct', 'name' => 'Направления', 'options' => $quickGroups[0]['options']],
            ['type' => 'subject', 'name' => 'Предметы', 'options' => $quickGroups[1]['options']],
            ['type' => 'grade', 'name' => 'Классы', 'options' => $gradeOptions],
          ] as $group)
            <div class="modal-group" data-filter-group="{{ $group['type'] }}">
              <div class="modal-group-title">{{ $group['name'] }}</div>
              <div class="chips">
                @foreach($group['options'] as $value => $label)
                  <label class="chip">
                    <input type="checkbox" class="filter-checkbox chip-input" data-filter-type="{{ $group['type'] }}" data-value="{{ $value }}">
                    <span>{{ $label }}</span>
                  </label>
                @endforeach
              </div>
            </div>
          @endforeach

          <div class="modal-group" data-filter-group="lesson_format">
            <div class="modal-group-title">Формат занятий</div>
            <div class="chips">
              @foreach($lessonFormatOptions as $value => $label)
                <label class="chip">
                  <input type="checkbox" class="filter-checkbox chip-input" data-filter-type="lesson_format" data-value="{{ $value }}">
                  <span>{{ $label }}</span>
                </label>
              @endforeach
            </div>
          </div>

          <div class="modal-group" data-filter-group="price">
            <div class="modal-group-head">
              <div class="modal-group-title">Цена за урок</div>
              <div class="price-values">
                <span class="price-value-min">{{ number_format($priceSelected[0], 0, ',', ' ') }}</span> – <span class="price-value-max">{{ number_format($priceSelected[1], 0, ',', ' ') }}</span> ₽
              </div>
            </div>
            <div class="range-slider" id="price-slider"
                 data-min="{{ $priceBounds[0] }}" data-max="{{ $priceBounds[1] }}" data-step="{{ $priceStep }}">
              <div class="range-slider-track"><div class="range-slider-fill"></div></div>
              <input type="range" class="range-input range-input-min" aria-label="Цена от"
                     min="{{ $priceBounds[0] }}" max="{{ $priceBounds[1] }}" step="{{ $priceStep }}" value="{{ $priceSelected[0] }}">
              <input type="range" class="range-input range-input-max" aria-label="Цена до"
                     min="{{ $priceBounds[0] }}" max="{{ $priceBounds[1] }}" step="{{ $priceStep }}" value="{{ $priceSelected[1] }}">
            </div>
          </div>

          <div class="modal-group" data-filter-group="rating_min" data-single="true">
            <div class="modal-group-title">Рейтинг</div>
            <div class="chips">
              @foreach($ratingOptions as $value => $label)
                <label class="chip">
                  <input type="checkbox" class="filter-checkbox chip-input" data-filter-type="rating_min" data-value="{{ $value }}" @checked($ratingMin === $value)>
                  <span>{{ $label }}</span>
                </label>
              @endforeach
            </div>
          </div>
        </div>
        <div class="filters-modal-foot">
          <button type="button" class="modal-reset p18" data-reset-filters>Сбросить</button>
          <button type="button" class="modal-apply" data-close-filters>Показать <span class="modal-apply-count">{{ plural_ru($totalCount, 'специалиста', 'специалистов', 'специалистов') }}</span></button>
        </div>
      </div>
    </div>

    <div class="specialists-list" id="specialists-list">
      @forelse($specialists as $specialist)
        @include('partials.specialist-item', ['specialist' => $specialist, 'lessonFormats' => $lessonFormats])
      @empty
        @include('partials.specialists-empty')
      @endforelse
    </div>
    <div id="load-trigger" data-offset="20" style="height: 1px;"></div>
  </section>
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      // Подчёркиваем закреплённые фильтры, когда они «прилипли» к верху экрана
      const filtersWrapper = document.querySelector('.filters-wrapper');
      const filtersSentinel = document.querySelector('.filters-sentinel');
      if (filtersWrapper && filtersSentinel && 'IntersectionObserver' in window) {
        new IntersectionObserver(function (entries) {
          filtersWrapper.classList.toggle('is-stuck', !entries[0].isIntersecting);
        }, { threshold: 0 }).observe(filtersSentinel);
      }

      const trailerBtn = document.querySelector('.trailer-button');
      const popupWrapper = document.querySelector('.popup-wrapper');
      const closeBtn = document.querySelector('.close-button');
      const closeZone = document.querySelector('.close-click-zone');

      if (trailerBtn && popupWrapper) {
        const iframe = popupWrapper.querySelector('iframe');
        let initialSrc = '';

        if (iframe) {
          initialSrc = iframe.src;
        }

        trailerBtn.addEventListener('click', function (e) {
          e.preventDefault();
          if (iframe && initialSrc) {
            const separator = initialSrc.includes('?') ? '&' : '?';
            iframe.src = initialSrc + separator + 'autoplay=1';
          }
          popupWrapper.style.display = 'flex';
        });

        const closePopup = function () {
          popupWrapper.style.display = 'none';
          if (iframe && initialSrc) {
            iframe.src = initialSrc;
          }
        };

        if (closeBtn) {
          closeBtn.addEventListener('click', closePopup);
        }

        if (closeZone) {
          closeZone.addEventListener('click', closePopup);
        }
      }
    });
  </script>
@endsection