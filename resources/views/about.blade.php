@extends('layout')

@section('title', 'О платформе — Serdal')

@section('description', 'Serdal — платформа для онлайн-занятий с учителями и наставниками: занятия в браузере с доской и записью, расписание, домашние задания, материалы и учёт оплат в одном кабинете.')

@push('jsonld')
    {!! \App\Support\Seo::jsonLd(\App\Support\Seo::breadcrumbs([['name' => 'О платформе', 'url' => \App\Support\Seo::canonical()]])) !!}
    {!! \App\Support\Seo::jsonLd(['@type' => 'AboutPage', 'name' => 'О платформе Serdal', 'url' => \App\Support\Seo::canonical(), 'about' => ['@id' => \App\Support\Seo::url('#organization')], 'inLanguage' => 'ru-RU']) !!}
@endpush

@section('styles')
    <link href="/css/about-laptop.css?v={{ filemtime(public_path('css/about-laptop.css')) }}" rel="stylesheet" type="text/css">
    <style>
        .about-section {
            /* width обязателен: body — flex-контейнер, а auto-margin по поперечной
               оси отменяет stretch, и без него секция сжимается по содержимому. */
            box-sizing: border-box;
            width: 100%;
            max-width: 1216px;
            margin: 104px auto 0;
            padding: 0 32px;
        }

        .about-text-limit {
            max-width: 780px;
        }

        .about-heading {
            margin-bottom: 40px;
        }

        /* --- Само занятие: текст и ролик ----------------------------------- */
        .about-lesson {
            display: grid;
            grid-template-columns: minmax(0, .8fr) minmax(0, 1.2fr);
            gap: 56px;
            align-items: center;
            padding: 56px;
            border-radius: 32px;
            background-color: var(--bg1);
        }

        .about-lesson h2 {
            margin: 0 0 20px;
            padding: 0;
        }

        .about-lesson p {
            margin: 0;
            color: var(--gray);
        }

        .about-lesson p + p {
            margin-top: 16px;
        }

        .about-lesson__video {
            display: block;
            width: 100%;
            height: auto;
            aspect-ratio: 16 / 10;
            border-radius: 16px;
            background-color: var(--white);
            box-shadow:
                0 20px 48px -12px rgba(20, 32, 40, .16),
                0 2px 6px rgba(20, 32, 40, .05);
        }

        /* --- Ученикам / учителям ------------------------------------------- */
        .about-columns {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 24px;
        }

        .about-column {
            border: 1px solid var(--line-light);
            border-radius: 24px;
            padding: 48px 40px;
        }

        .about-column--accent {
            background-color: var(--brand-main-light);
            border-color: transparent;
        }

        .about-list {
            margin: 24px 0 0;
            padding: 0;
            list-style: none;
        }

        .about-list li {
            position: relative;
            padding-left: 32px;
            color: var(--gray);
        }

        .about-list li + li {
            margin-top: 16px;
        }

        .about-list li:before {
            content: "";
            position: absolute;
            top: 12px;
            left: 0;
            width: 12px;
            height: 12px;
            border-radius: 4px;
            background-color: var(--black);
        }

        /* --- Направления --------------------------------------------------- */
        .about-directions {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 16px;
            margin-top: 40px;
        }

        .about-direction {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            gap: 40px;
            min-height: 188px;
            padding: 32px;
            border: 1px solid var(--line-light);
            border-radius: 24px;
            color: var(--black);
            text-decoration: none;
            transition: background-color .2s, border-color .2s;
        }

        a.about-direction:hover {
            background-color: var(--brand-main-light);
            border-color: transparent;
        }

        .about-direction__name {
            font-size: 30px;
            font-weight: 500;
            line-height: 38px;
            letter-spacing: -.5px;
        }

        .about-direction__foot {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 16px;
        }

        .about-direction__meta {
            color: var(--gray);
        }

        .about-direction__arrow {
            flex: none;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 12px;
            background-color: var(--bg1);
            color: var(--black);
            transition: background-color .2s, transform .2s;
        }

        a.about-direction:hover .about-direction__arrow {
            background-color: var(--white);
            transform: translate(2px, -2px);
        }

        .about-direction--empty {
            color: var(--gray);
        }

        /* --- С чего начать ------------------------------------------------- */
        .about-final {
            background-color: var(--bg1);
            border-radius: 32px;
            padding: 64px;
        }

        .about-final .about-heading {
            margin-bottom: 24px;
        }

        .about-cta {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            margin-top: 40px;
        }

        .about-cta .main-button {
            justify-content: center;
        }

        .about-cta__secondary {
            background-color: var(--white);
        }

        .about-footer-space {
            height: 104px;
        }

        @media screen and (max-width: 991px) {
            .about-section {
                margin-top: 72px;
            }

            .about-lesson {
                grid-template-columns: minmax(0, 1fr);
                gap: 40px;
                padding: 40px;
            }

            .about-final {
                padding: 48px 40px;
            }

            .about-directions {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media screen and (max-width: 767px) {
            .about-section {
                margin-top: 56px;
                padding: 0 20px;
            }

            .about-heading {
                margin-bottom: 32px;
            }

            .about-lesson {
                gap: 32px;
                padding: 32px 24px;
                border-radius: 24px;
            }

            .about-lesson__video {
                border-radius: 12px;
            }

            .about-columns {
                grid-template-columns: minmax(0, 1fr);
            }

            .about-column {
                padding: 32px 24px;
            }

            .about-directions {
                grid-template-columns: minmax(0, 1fr);
                margin-top: 32px;
            }

            .about-direction {
                min-height: 0;
                gap: 12px;
                padding: 20px 24px;
            }

            .about-direction__meta {
                font-size: 18px;
                line-height: 26px;
            }

            .about-direction__arrow {
                width: 32px;
                height: 32px;
                border-radius: 10px;
            }

            .about-direction__name {
                font-size: 24px;
                line-height: 32px;
            }

            .about-final {
                border-radius: 24px;
                padding: 32px 24px;
            }

            .about-cta .main-button {
                width: 100%;
            }

            .about-footer-space {
                height: 64px;
            }
        }
    </style>
@endsection

@section('content')

    <section class="page-title-section">
        <h1 class="h1">О платформе</h1>
        <p class="p30 page-descriptions">
            Serdal — платформа для онлайн-занятий с учителями и наставниками. Расписание, занятия,
            домашние задания, записи и оплаты — в одном кабинете, без мессенджеров и таблиц.
        </p>
    </section>

    {{-- Кабинет учителя целиком показывает демо в ноутбуке, поэтому ниже — только то,
         чего в демо нет: само занятие, что получают ученик и учитель, каталог и вход. --}}
    @include('partials.about-laptop')

    <section class="about-section">
        {{-- Демо не может показать идущее занятие (это комната видеосвязи), поэтому оно — роликом --}}
        <div class="about-lesson">
            <div>
                <h2 class="h2">Само занятие</h2>
                <p class="p24">
                    Проходит прямо в браузере — ничего не нужно устанавливать, работает на компьютере,
                    планшете и телефоне.
                </p>
                <p class="p24">
                    Общая доска, презентации и демонстрация экрана, один на один или группой.
                    Занятие можно записать — запись сама появится в кабинете ученика.
                </p>
            </div>
            {{-- Ролик без звука играет, только пока виден на экране; при «уменьшить движение» — по кнопке --}}
            <video class="about-lesson__video" data-src="/videos/about/lesson.mp4" poster="/images/about/lesson.jpg"
                muted playsinline loop preload="none" aria-label="Занятие с доской и презентацией"
                x-data="aboutLessonVideo"></video>
        </div>
    </section>

    <section class="about-section">
        <div class="about-columns">
            <div class="about-column">
                <h2 class="h3">Ученикам<br>и родителям</h2>
                <ul class="about-list p24">
                    <li>Профиль и отзывы учителя видно ещё до первого занятия</li>
                    <li>Расписание с напоминанием перед началом</li>
                    <li>Задания, материалы и записи занятий — в личном кабинете</li>
                    <li>Видно, какие занятия оплачены, а какие ещё нет</li>
                </ul>
            </div>
            <div class="about-column about-column--accent">
                <h2 class="h3">Учителям</h2>
                <ul class="about-list p24">
                    <li>Ученики, расписание, задания и материалы в одном кабинете</li>
                    <li>Учёт оплат поштучно или помесячно, без ручных подсчётов</li>
                    <li>Своя страница с отзывами — по ней вас находят новые ученики</li>
                    <li>Свои статьи в блоге Serdal — ещё один путь к новым ученикам</li>
                </ul>
            </div>
        </div>
    </section>

    @php
        $directs = App\Models\Direct::withCount([
            'users' => fn ($query) => $query->isSpecialist()->where('is_active', true),
        ])->orderByDesc('users_count')->orderBy('name')->get();

        // Русская форма слова для счётчика учителей.
        $teacherWord = function (int $count): string {
            $mod100 = $count % 100;
            $mod10 = $count % 10;

            if ($mod100 >= 11 && $mod100 <= 14) return 'учителей';
            if ($mod10 === 1) return 'учитель';
            if ($mod10 >= 2 && $mod10 <= 4) return 'учителя';

            return 'учителей';
        };
    @endphp
    @if($directs->isNotEmpty())
        <section class="about-section">
            <h2 class="h2 about-heading" style="padding-left: 0; padding-right: 0;">Направления</h2>

            {{-- Не бегущая строка, как на главной: здесь направления — точка входа в каталог.
                 Каждая карточка ведёт на список специалистов с уже применённым фильтром. --}}
            <div class="about-directions">
                @foreach($directs as $direct)
                    @if($direct->users_count > 0)
                        <a class="about-direction" href="/?direct%5B%5D={{ $direct->id }}#specialists">
                            <span class="about-direction__name">{{ $direct->name }}</span>
                            <span class="about-direction__foot">
                                <span class="p24 about-direction__meta">
                                    {{ $direct->users_count }} {{ $teacherWord($direct->users_count) }}
                                </span>
                                <span class="about-direction__arrow" aria-hidden="true">
                                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M5 15L15 5M15 5H7M15 5V13" stroke="currentColor" stroke-width="1.5"
                                            stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                </span>
                            </span>
                        </a>
                    @else
                        <div class="about-direction about-direction--empty">
                            <span class="about-direction__name">{{ $direct->name }}</span>
                        </div>
                    @endif
                @endforeach
            </div>
        </section>
    @endif

    <section class="about-section">
        <div class="about-final">
            <h2 class="h2 about-heading" style="padding-left: 0; padding-right: 0;">С чего начать</h2>
            <p class="p24 about-text-limit" style="color: var(--gray); margin: 0;">
                Ученикам — выбрать учителя или наставника в каталоге. Учителям — выбрать тариф и перенести
                своих учеников на платформу.
            </p>
            <div class="about-cta">
                <a href="/#specialists" class="main-button w-button">Найти специалиста</a>
                <a href="{{ route('tariffs') }}" class="main-button about-cta__secondary w-button">Стать учителем</a>
            </div>
        </div>
    </section>

    <script>
        document.addEventListener('alpine:init', function () {
            Alpine.data('aboutLessonVideo', function () {
                return {
                    init() {
                        var el = this.$el;

                        // Уважаем «уменьшить движение»: ролик не стартует сам, его включают кнопкой
                        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                            el.src = el.dataset.src;
                            el.controls = true;
                            return;
                        }

                        // Ролик (1 МБ) грузится, когда блок подъехал к экрану, и играет, только пока виден
                        if (!window.IntersectionObserver) {
                            el.src = el.dataset.src;
                            el.autoplay = true;
                            return;
                        }

                        new IntersectionObserver(function (entries) {
                            if (entries[0].isIntersecting) {
                                if (!el.src) el.src = el.dataset.src;
                                var p = el.play();
                                if (p && p.catch) p.catch(function () {});
                            } else {
                                el.pause();
                            }
                        }, { rootMargin: '200px 0px' }).observe(el);
                    },
                };
            });
        });
    </script>

    <div class="about-footer-space"></div>

@endsection
