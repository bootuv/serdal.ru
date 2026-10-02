@extends('layout')

@php
  $tutorName = \Illuminate\Support\Str::squish($user->name);
  $tutorSubjects = $user->subjects->pluck('name')->all();
  $tutorTopics = $user->directs->pluck('name')->all();
  // Страницы каталога по предметам и направлениям учителя — для ссылок и хлебных крошек
  $catalogPages = app(\App\Services\TutorCatalogService::class)->catalog();
  $subjectPages = collect($catalogPages['subjects'])->keyBy('id');
  $directPages = collect($catalogPages['directs'])->keyBy('id');
  $mainSubjectPage = $user->subjects->map(fn ($subject) => $subjectPages->get($subject->id))->filter()->first();
  $tutorOffers = collect([
      'Индивидуальные занятия' => $lessonTypeIndividual,
      'Групповые занятия' => $lessonTypeGroup,
  ])->filter(fn ($lesson) => $lesson && $lesson->price)->map(fn ($lesson, $label) => [
      '@type' => 'Offer',
      'name' => $label,
      'price' => (int) $lesson->price,
      'priceCurrency' => 'RUB',
      'priceSpecification' => [
          '@type' => 'UnitPriceSpecification',
          'price' => (int) $lesson->price,
          'priceCurrency' => 'RUB',
          'unitText' => $lesson->payment_type === 'monthly' ? 'месяц' : 'занятие',
      ],
      'itemOffered' => array_filter([
          '@type' => 'Service',
          'name' => $label . ($user->subjectsList ? ': ' . mb_strtolower($user->subjectsList) : ''),
          'serviceType' => 'Онлайн-занятия с репетитором',
      ]),
  ])->values()->all();
  $tutorTitle = $tutorName . ($user->subjectsList ? ' — репетитор: ' . mb_strtolower($user->subjectsList) : ' — ' . mb_strtolower($user->displayRole)) . ' | Serdal';
  $tutorDescription = \App\Support\Seo::text(implode(' ', array_filter([
      $tutorName . ' — ' . mb_strtolower($user->displayRole) . ' на платформе Serdal.',
      $user->subjectsList ? 'Предметы: ' . $user->subjectsList . '.' : null,
      $tutorTopics ? 'Направления: ' . implode(', ', $tutorTopics) . '.' : null,
      $user->displayGrade ? 'Ученики: ' . $user->displayGrade . '.' : null,
      $ratingAvg !== null ? 'Оценка ' . number_format($ratingAvg, 1, ',', '') . ' по ' . plural_ru($reviewsTotal, 'отзыву', 'отзывам', 'отзывам') . '.' : null,
      'Онлайн-занятия, цены и контакты.',
  ])), 300);
@endphp

@section('title', $tutorTitle)
@section('description', $tutorDescription)
@section('og_type', 'profile')
@section('og_image', $user->avatarJpgUrl)

@section('meta')
  <meta property="profile:username" content="{{ $user->username }}">
@endsection

@push('jsonld')
  {!! \App\Support\Seo::jsonLd(\App\Support\Seo::breadcrumbs(array_values(array_filter([
      ['name' => 'Репетиторы', 'url' => \App\Support\Seo::url(route('catalog.index', [], false))],
      $mainSubjectPage ? ['name' => $mainSubjectPage['name'], 'url' => $mainSubjectPage['url']] : null,
      ['name' => $tutorName, 'url' => \App\Support\Seo::canonical()],
  ])))) !!}
  {!! \App\Support\Seo::jsonLd([
      '@type' => 'ProfilePage',
      'url' => \App\Support\Seo::canonical(),
      'inLanguage' => 'ru-RU',
      'dateModified' => optional($user->updated_at)->toAtomString(),
      'isPartOf' => ['@id' => \App\Support\Seo::url('#website')],
      'mainEntity' => array_filter([
          '@type' => 'Person',
          '@id' => \App\Support\Seo::canonical() . '#person',
          'name' => $tutorName,
          'url' => \App\Support\Seo::canonical(),
          'image' => $user->avatarJpgUrl,
          'jobTitle' => 'Репетитор',
          'description' => $tutorDescription,
          'knowsAbout' => array_values(array_unique(array_merge($tutorSubjects, $tutorTopics))) ?: null,
          'knowsLanguage' => 'ru',
          'memberOf' => ['@id' => \App\Support\Seo::url('#organization')],
          'makesOffer' => $tutorOffers ?: null,
      ]),
  ]) !!}
  @if($ratingAvg !== null)
    {{-- Оценки учеников — на услуге учителя: у Person по schema.org рейтинга нет --}}
    {!! \App\Support\Seo::jsonLd([
        '@type' => 'Service',
        'name' => 'Онлайн-занятия: ' . $tutorName,
        'serviceType' => 'Онлайн-занятия с репетитором',
        'url' => \App\Support\Seo::canonical(),
        'provider' => ['@id' => \App\Support\Seo::canonical() . '#person'],
        'areaServed' => ['@type' => 'Country', 'name' => 'Россия'],
        'aggregateRating' => [
            '@type' => 'AggregateRating',
            'ratingValue' => round($ratingAvg, 1),
            'bestRating' => 5,
            'worstRating' => 1,
            'ratingCount' => $reviewsTotal,
            'reviewCount' => $reviewsTotal,
        ],
        'review' => $reviews->take(5)->map(fn ($review) => array_filter([
            '@type' => 'Review',
            'author' => ['@type' => 'Person', 'name' => \Illuminate\Support\Str::squish($review->user->name)],
            'datePublished' => optional($review->created_at)->toDateString(),
            'reviewBody' => \App\Support\Seo::text($review->text, 500),
            'reviewRating' => ['@type' => 'Rating', 'ratingValue' => (int) $review->rating, 'bestRating' => 5, 'worstRating' => 1],
        ]))->values()->all(),
    ]) !!}
  @endif
@endpush

@section('content')

  <section class="profile">
    <div class="profile-pic-wrapper">
      @include('partials.userpic', ['user' => $user, 'class' => 'profile-pic', 'thumb' => false, 'alt' => $tutorName . ' — репетитор', 'attrs' => 'width="280" height="280" sizes="280px" fetchpriority="high"'])
    </div>
    <h1 class="h3 tutor-name">{{ $tutorName }}</h1>
    @if($ratingAvg !== null)
      <a href="#teacher-reviews" class="profile-rating">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2.5l2.95 6.1 6.7.9-4.9 4.7 1.2 6.7L12 17.7l-5.95 3.2 1.2-6.7-4.9-4.7 6.7-.9L12 2.5z"/></svg>
        <span class="profile-rating__value">{{ number_format($ratingAvg, 1, ',', '') }}</span>
        <span class="profile-rating__count">{{ plural_ru($reviewsTotal, 'отзыв', 'отзыва', 'отзывов') }}</span>
      </a>
    @endif
    @if($user->subjects->isNotEmpty())
      {{-- Предметы ссылаются на подборки «Репетитор по …» --}}
      <div class="tutor-subjects p24">
        @foreach($user->subjects as $subject)
          @php($subjectName = $loop->first ? $subject->name : \Illuminate\Support\Str::lower($subject->name))
          @if($subjectPages->has($subject->id))<a href="{{ $subjectPages[$subject->id]['url'] }}">{{ $subjectName }}</a>@else{{ $subjectName }}@endif{{ $loop->last ? '' : ', ' }}
        @endforeach
      </div>
    @endif
    @if($user->directs && $user->directs->count() > 0)
      <div class="direction-tags-list tutor-page">
        @foreach($user->directs as $direct)
          @if($directPages->has($direct->id))
            <a href="{{ $directPages[$direct->id]['url'] }}" class="direction-tag tutor-page">
              <div class="p24">{{ $direct->name }}</div>
            </a>
          @else
            <div class="direction-tag tutor-page">
              <div class="p24">{{ $direct->name }}</div>
            </div>
          @endif
        @endforeach
      </div>
    @endif
    @if(!empty($user->displayGrade) && trim(strip_tags($user->displayGrade)) !== '')
      <div class="grades p24">{{ $user->displayGrade }}</div>
    @endif
    <a href="javascript:void(0)" onclick="shareProfile()" class="main-button share-button w-inline-block">
      <img src="images/share-01.svg" loading="lazy" width="32" height="32" alt="">
      <div class="p24">Поделиться страницей</div>
    </a>
    <script>
      function shareProfile() {
        const shareData = {
          title: '{{ $user->name }} - Преподаватель Serdal',
          url: window.location.href
        };

        if (navigator.share) {
          navigator.share(shareData)
            .catch((error) => console.log('Error sharing:', error));
        } else {
          navigator.clipboard.writeText(window.location.href)
            .then(() => alert('Ссылка скопирована в буфер обмена'))
            .catch(() => alert('Не удалось скопировать ссылку'));
        }
      }
    </script>
  </section>
  <section class="content">
    <div class="col-50 vertical">
      @if($user->about)
        <div class="content-card">
          <h4 class="h4">Обо мне</h4>
          {!! $user->about !!}
        </div>
      @endif
      @if($user->extra_info)
        <div class="content-card">
          <h4 class="h4">Дополнительная информация</h4>
          {!! $user->extra_info !!}
        </div>
      @endif
    </div>
    <div class="col-50 horizontal">
      <div class="col-25">
        @if($lessonTypeGroup || $lessonTypeIndividual)
          <div class="content-card">
            <h4 class="h4">Занятия</h4>
            @if($lessonTypeGroup)
              <div class="group-classes">
                <div class="class-info-title">
                  <div class="p24">Групповые</div>
                </div>
                <div class="param-list">
                  @if($lessonTypeGroup->price)
                    <div class="param-list-item">
                      <div class="param-item-label p18">Цена</div>
                      <div class="param-item-data">
                        <div class="price">
                          <div class="p24-medium">{{ $lessonTypeGroup->price }} ₽</div>
                          <div class="p18">{{ $lessonTypeGroup->payment_type === 'monthly' ? '/ в месяц' : '/ за урок' }}</div>
                        </div>
                      </div>
                    </div>
                  @endif
                  @if($lessonTypeGroup->payment_type === 'monthly' && $lessonTypeGroup->count_per_week)
                    <div class="param-list-item">
                      <div class="param-item-label p18">Занятий в неделю</div>
                      <div class="param-item-data">
                        <div class="param-list-item-text">
                          <div class="p18-medium">{{ $lessonTypeGroup->count_per_week }}</div>
                        </div>
                      </div>
                    </div>
                  @endif
                  @if($lessonTypeGroup->duration)
                    <div class="param-list-item last">
                      <div class="param-item-label p18">Длина занятия</div>
                      <div class="param-item-data">
                        <div class="param-list-item-text">
                          <div class="p18-medium">{{ $lessonTypeGroup->duration }} минут</div>
                        </div>
                      </div>
                    </div>
                  @endif
                </div>
              </div>
            @endif
            @if($lessonTypeIndividual)
              <div class="individual-classes">
                <div class="class-info-title">
                  <div class="p24">Индивидуальные</div>
                </div>
                <div class="param-list">
                  @if($lessonTypeIndividual->price)
                    <div class="param-list-item">
                      <div class="param-item-label p18">Цена</div>
                      <div class="param-item-data">
                        <div class="price">
                          <div class="p24-medium">{{ $lessonTypeIndividual->price }} ₽</div>
                          <div class="p18">{{ $lessonTypeIndividual->payment_type === 'monthly' ? '/ в месяц' : '/ за урок' }}</div>
                        </div>
                      </div>
                    </div>
                  @endif
                  @if($lessonTypeIndividual->payment_type === 'monthly' && $lessonTypeIndividual->count_per_week)
                    <div class="param-list-item">
                      <div class="param-item-label p18">Занятий в неделю</div>
                      <div class="param-item-data">
                        <div class="param-list-item-text">
                          <div class="p18-medium">{{ $lessonTypeIndividual->count_per_week }}</div>
                        </div>
                      </div>
                    </div>
                  @endif
                  @if($lessonTypeIndividual->duration)
                    <div class="param-list-item last">
                      <div class="param-item-label p18">Длина занятия</div>
                      <div class="param-item-data">
                        <div class="param-list-item-text">
                          <div class="p18-medium">{{ $lessonTypeIndividual->duration }} минут</div>
                        </div>
                      </div>
                    </div>
                  @endif
                </div>
              </div>
            @endif
          </div>
        @endif
      </div>
      <div class="col-25">
        @if($user->phone || $user->whatsup || $user->telegram)
          @php($lastContact = $user->telegram ? 'telegram' : ($user->whatsup ? 'whatsup' : 'phone'))
          <div class="content-card">
            <h4 class="h4">Способы связи</h4>
            <div class="contacts">
              <div class="param-list">
                @if($user->phone)
                  <div class="param-list-item icon{{ $lastContact === 'phone' ? ' last' : '' }}">
                    <div class="param-item-label p18">Телефон</div>
                    <div class="param-item-data">
                      <div class="price">
                        <a href="tel:{{ $user->phone }}" class="text-link w-inline-block">
                          <div class="p18-medium">{{ $user->phone }}</div>
                        </a>
                      </div>
                    </div>
                    <div class="w-layout-blockcontainer contact-icon w-container"><img src="images/Phone.svg" loading="lazy"
                        alt="" class="icon-svg"></div>
                  </div>
                @endif
                @if($user->whatsup)
                  <div class="param-list-item icon{{ $lastContact === 'whatsup' ? ' last' : '' }}">
                    <div class="param-item-label p18">WhatsApp</div>
                    <div class="param-item-data">
                      <div class="param-list-item-text">
                        <a href="https://wa.me/{{ $user->whatsup }}" class="text-link w-inline-block">
                          <div class="p18-medium">{{ $user->whatsup }}</div>
                        </a>
                      </div>
                    </div>
                    <div class="w-layout-blockcontainer contact-icon w-container"><img src="images/WhatsApp.svg"
                        loading="lazy" alt="" class="icon-svg"></div>
                  </div>
                @endif
                @if($user->telegram)
                  <div class="param-list-item icon{{ $lastContact === 'telegram' ? ' last' : '' }}">
                    <div class="param-item-label p18">Telegram</div>
                    <div class="param-item-data">
                      <div class="param-list-item-text">
                        <a href="https://t.me/{{ $user->telegram }}" class="text-link w-inline-block">
                          <div class="p18-medium">{{ "@" . $user->telegram }}</div>
                        </a>
                      </div>
                    </div>
                    <div class="w-layout-blockcontainer contact-icon w-container"><img src="images/Telegram.svg"
                        loading="lazy" alt="" class="icon-svg"></div>
                  </div>
                @endif
              </div>
            </div>
          </div>
        @endif
      </div>
    </div>
  </section>
  @if($blogPosts->isNotEmpty())
    @section('styles')
      <link href="/css/blog.css?v={{ filemtime(public_path('css/blog.css')) }}" rel="stylesheet" type="text/css">
    @endsection
    {{-- Статьи учителя в блоге: последние четыре и ссылка на все --}}
    @include('blog.partials.section', [
      'posts' => $blogPosts,
      'heading' => 'Статьи в блоге',
      'id' => 'teacher-blog',
      'allUrl' => $user->username ? route('blog.author', $user->username) : null,
      'allLabel' => $blogTotal > 4 ? 'Все статьи · ' . $blogTotal : 'Все статьи',
    ])
  @endif
  @if($reviews->isNotEmpty())
    <section class="content reviews-content">
      <h2 class="h2">Отзывы</h2>
      <div class="reviews" id="teacher-reviews">
        @foreach($reviews as $review)
          @include('partials.review-item', ['review' => $review, 'hideTeacherMention' => true])
        @endforeach
      </div>
      @if($reviewsHasMore)
        <div id="reviews-load-trigger" data-offset="20" style="height: 1px;"></div>
      @endif
    </section>
    <script>
      document.addEventListener('DOMContentLoaded', function () {
        const container = document.getElementById('teacher-reviews');
        const loadTrigger = document.getElementById('reviews-load-trigger');
        if (!loadTrigger) return;

        let isLoading = false;

        const loadMore = () => {
          if (isLoading) return;
          isLoading = true;

          const offset = parseInt(loadTrigger.getAttribute('data-offset'));

          fetch(`{{ route('reviews.load-more') }}?offset=${offset}&teacher={{ $user->id }}`)
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
      });
    </script>
  @endif
@endsection