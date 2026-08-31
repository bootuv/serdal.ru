@extends('layout')

@section('title', 'О платформе — Serdal')

@section('meta')
    <meta name="description"
        content="Serdal — платформа для онлайн-занятий с репетиторами и менторами: видеоуроки в браузере, интерактивная доска, записи занятий, расписание, домашние задания и материалы в одном кабинете.">
    <meta property="og:title" content="О платформе — Serdal">
    <meta property="og:description"
        content="Видеозанятия в браузере, интерактивная доска, записи уроков, расписание, домашние задания и материалы — всё в одном месте.">
@endsection

@section('styles')
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

        .about-manifest,
        .about-final {
            background-color: var(--bg1);
            border-radius: 32px;
            padding: 64px;
        }

        .about-final .about-heading {
            margin-bottom: 24px;
        }

        .about-final .about-cta__secondary {
            background-color: var(--white);
        }

        .about-manifest p {
            margin: 0;
            color: var(--black);
        }

        .about-manifest p + p {
            margin-top: 24px;
        }

        .about-manifest__body {
            max-width: 820px;
            margin-top: 24px;
        }

        .about-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 24px;
        }

        .about-card {
            background-color: var(--bg1);
            border-radius: 24px;
            padding: 40px 32px;
        }

        .about-card__title {
            margin: 0 0 12px;
            font-size: 30px;
            font-weight: 500;
            line-height: 38px;
            letter-spacing: -.5px;
        }

        .about-card__text {
            margin: 0;
            color: var(--gray);
        }

        .about-steps {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 32px;
        }

        .about-step__number {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 64px;
            height: 64px;
            margin-bottom: 24px;
            border-radius: 20px;
            background-color: var(--brand-main);
            font-size: 30px;
            font-weight: 500;
            line-height: 1;
        }

        .about-step__text {
            margin: 0;
            color: var(--gray);
        }

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
            background-color: var(--bg2);
        }

        .about-footer-space {
            height: 104px;
        }

        /* --- Заглушки под скриншоты --------------------------------------- */
        .about-shot {
            margin: 0;
        }

        .about-shot__img,
        .about-shot__frame {
            display: block;
            width: 100%;
            border-radius: 24px;
        }

        .about-shot__img {
            height: auto;
            border: 1px solid var(--line-light);
        }

        .about-shot__frame {
            display: flex;
            flex-direction: column;
            overflow: hidden;
            background-color: var(--bg1);
            border: 1px solid var(--line-light);
        }

        .about-shot--16-9 .about-shot__frame {
            aspect-ratio: 16 / 9;
        }

        .about-shot--4-3 .about-shot__frame {
            aspect-ratio: 4 / 3;
        }

        .about-shot__bar {
            display: flex;
            align-items: center;
            gap: 8px;
            flex: none;
            height: 44px;
            padding: 0 20px;
            background-color: var(--bg2);
        }

        .about-shot__bar i {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background-color: var(--white);
        }

        .about-shot__body {
            display: flex;
            gap: 16px;
            flex: 1;
            min-height: 0;
            padding: 20px;
        }

        .about-shot__side {
            display: flex;
            flex-direction: column;
            gap: 10px;
            flex: none;
            width: 22%;
        }

        .about-shot__side span {
            flex: none;
            height: 14px;
            border-radius: 6px;
            background-color: var(--bg3);
        }

        .about-shot__side span:first-child {
            width: 70%;
            background-color: var(--brand-main);
        }

        .about-shot__main {
            display: flex;
            flex-direction: column;
            gap: 12px;
            flex: 1;
            min-width: 0;
        }

        .about-shot__title {
            width: 45%;
            height: 20px;
            border-radius: 6px;
            background-color: var(--bg3);
        }

        .about-shot__tiles {
            display: flex;
            gap: 12px;
            flex: 0 0 32%;
            min-height: 40px;
        }

        .about-shot__rows {
            display: flex;
            flex-direction: column;
            gap: 14px;
            flex: 1;
            min-height: 0;
        }

        .about-shot__tiles div {
            flex: 1;
            border-radius: 12px;
            background-color: var(--white);
        }

        .about-shot__tiles div:nth-child(2) {
            background-color: var(--brand-main-light);
        }

        .about-shot__line {
            flex: none;
            height: 12px;
            border-radius: 6px;
            background-color: var(--bg3);
        }

        .about-shot__line--mid {
            width: 82%;
        }

        .about-shot__line--short {
            width: 58%;
        }

        .about-shot__caption {
            margin-top: 16px;
            text-align: left;
            color: var(--gray);
            font-size: 20px;
            line-height: 28px;
        }

        /* --- Список возможностей с синхронной графикой ---------------------- */
        .about-tabs {
            display: grid;
            grid-template-columns: minmax(0, .85fr) minmax(0, 1fr);
            gap: 64px;
            align-items: stretch;
            margin-top: 48px;
        }

        .about-tabs__list {
            border-left: 1px solid var(--line-light);
        }

        .about-tab {
            position: relative;
            display: block;
            width: 100%;
            padding: 24px 0 24px 40px;
            border: 0;
            background: none;
            font-family: inherit;
            text-align: left;
            cursor: pointer;
        }

        .about-tab__bar {
            position: absolute;
            top: 0;
            bottom: 0;
            left: -1px;
            width: 4px;
            overflow: hidden;
            border-radius: 4px;
        }

        .about-tab__fill {
            display: block;
            width: 100%;
            height: 0;
            border-radius: 4px;
            background-color: var(--brand-main);
        }

        .about-tab__title {
            display: block;
            color: var(--icon);
            font-size: 36px;
            font-weight: 500;
            line-height: 44px;
            letter-spacing: -.5px;
            transition: color .2s;
        }

        .about-tab:hover .about-tab__title {
            color: var(--gray);
        }

        .about-tab.is-active .about-tab__title {
            color: var(--black);
        }

        /* Раскрытие через grid-template-rows 0fr -> 1fr: высота анимируется
           без магических max-height, текст при этом обрезается overflow. */
        .about-tab__reveal {
            display: grid;
            grid-template-rows: 0fr;
            opacity: 0;
            transition: grid-template-rows .45s cubic-bezier(.4, 0, .2, 1), opacity .3s;
        }

        .about-tab.is-active .about-tab__reveal {
            grid-template-rows: 1fr;
            opacity: 1;
        }

        /* Отступ живёт на внутреннем элементе: padding самой grid-ячейки
           не схлопывается вместе с min-height и оставлял бы полоску в 16px. */
        .about-tab__clip {
            display: block;
            min-height: 0;
            overflow: hidden;
        }

        .about-tab__text {
            display: block;
            padding-top: 16px;
            color: var(--gray);
        }

        /* Панель без внутренних отступов и без overflow: hidden — слайды не едут лентой,
           а лежат друг на друге и проявляются (см. .about-tabs__slide), поэтому тень
           у видео нечему обрезать. Воздух вокруг видео задаёт его собственная ширина. */
        .about-tabs__media {
            display: flex;
            align-items: center;
            /* Панель всегда квадратная: высота = ширине. align-self: start нужен,
               иначе stretch сетки растянет её по высоте списка и пропорция пропадёт. */
            aspect-ratio: 1 / 1;
            align-self: start;
            padding: 0;
            border-radius: 32px;
            /* Фирменная «искра» одна, по центру, крупнее панели: концы лучей уходят
               за края и обрезаются скруглением — фон не выглядит сплошной заливкой. */
            background-color: var(--bg2);
            background-image: url('/images/about/sparkle.svg');
            background-repeat: no-repeat;
            background-size: 160% 160%;
            background-position: center;
        }

        .about-tabs__viewport {
            width: 100%;
        }

        /* Все слайды в одной ячейке сетки: высота — по самому высокому,
           активный проявляется поверх остальных */
        .about-tabs__track {
            display: grid;
            width: 100%;
        }

        .about-tabs__slide {
            grid-area: 1 / 1;
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
            transition: opacity .55s cubic-bezier(.4, 0, .2, 1), visibility 0s .55s;
        }

        .about-tabs__slide.is-active {
            opacity: 1;
            visibility: visible;
            pointer-events: auto;
            transition-delay: 0s;
        }

        /* Видео/кадр уже панели — отступ от краёв вместо внутреннего padding */
        .about-tabs .about-tabs__slide > * {
            width: 84%;
            margin: 0 auto;
        }

        /* Кадр на цветной панели делаем белым, иначе сливается с фоном */
        .about-tabs__media .about-shot__frame {
            background-color: var(--white);
            border-color: #0000;
        }

        .about-tabs__media .about-shot__bar,
        .about-tabs__media .about-shot__tiles div {
            background-color: var(--bg1);
        }

        .about-tabs__media .about-shot__tiles div:nth-child(2) {
            background-color: var(--brand-main-light);
        }

        .about-tabs__video {
            display: block;
            width: 100%;
            height: auto;
            border-radius: 16px;
            background-color: var(--white);
            /* Мягкая рассеянная тень: два слоя — широкая лёгкая и короткая у края.
               Её размах (~36px) укладывается в отступы слайда и запас viewport. */
            box-shadow:
                0 20px 48px -12px rgba(20, 32, 40, .16),
                0 2px 6px rgba(20, 32, 40, .05);
        }



        /* --- Крупный блок с картинкой -------------------------------------- */
        .about-spotlight {
            display: grid;
            grid-template-columns: minmax(0, 1.05fr) minmax(0, 1fr);
            gap: 56px;
            align-items: center;
            padding: 56px;
            border-radius: 32px;
            background-color: var(--bg1);
        }

        .about-spotlight h3 {
            margin: 0 0 20px;
        }

        .about-spotlight p {
            margin: 0;
            color: var(--gray);
        }

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

        @media screen and (max-width: 991px) {
            .about-section {
                margin-top: 72px;
            }

            .about-manifest,
            .about-final {
                padding: 48px 40px;
            }

            .about-grid,
            .about-steps,
            .about-directions {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .about-card__title {
                font-size: 24px;
                line-height: 32px;
            }

            .about-tabs {
                grid-template-columns: minmax(0, 1fr);
                gap: 40px;
            }

            .about-tab__title {
                font-size: 30px;
                line-height: 38px;
            }


            .about-spotlight {
                gap: 40px;
                padding: 40px;
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

            .about-manifest,
            .about-final {
                border-radius: 24px;
                padding: 32px 24px;
            }

            .about-grid,
            .about-steps,
            .about-columns {
                grid-template-columns: minmax(0, 1fr);
            }

            .about-card {
                padding: 32px 24px;
            }

            .about-column {
                padding: 32px 24px;
            }

            .about-cta .main-button {
                width: 100%;
            }

            .about-footer-space {
                height: 64px;
            }

            .about-tabs {
                gap: 32px;
                margin-top: 32px;
            }

            .about-tab {
                padding: 20px 0 20px 24px;
            }

            .about-tab__title {
                font-size: 26px;
                line-height: 34px;
            }

            .about-tabs__media {
                border-radius: 24px;
            }

            .about-shot__caption {
                font-size: 18px;
                line-height: 26px;
            }

            .about-spotlight {
                grid-template-columns: minmax(0, 1fr);
                gap: 32px;
                padding: 32px 24px;
                border-radius: 24px;
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
        }
    </style>
@endsection

@section('content')

    <section class="page-title-section">
        <h1 class="h1">О платформе</h1>
        <p class="p30 page-descriptions">
            Serdal — платформа для онлайн-занятий с репетиторами и менторами. Мы собрали в одном месте всё,
            из чего складывается учёба: сами уроки, расписание, домашние задания, материалы и записи занятий.
        </p>
    </section>

    <section class="about-section">
        @include('partials.about-shot', ['label' => 'Кабинет преподавателя: расписание, ученики и ближайшие занятия'])
    </section>

    <section class="about-section">
        <div class="about-manifest">
            <p class="p30">Онлайн-урок — это не только видеозвонок.</p>
            <div class="about-manifest__body">
            <p class="p24">
                Вокруг каждого занятия есть договорённость о времени, конспекты и презентации, домашнее задание
                и его проверка, история встреч и вопрос оплаты. Обычно всё это расползается по мессенджерам,
                облачным дискам и заметкам — и рано или поздно теряется.
            </p>
            <p class="p24">
                Serdal держит учебный процесс в одном месте. Преподаватель ведёт расписание и своих учеников,
                ученик видит ближайшие занятия, задания и записи уроков в личном кабинете. Ничего не нужно
                пересылать вручную и искать в переписке спустя месяц.
            </p>
            </div>
        </div>
    </section>

    <section class="about-section">
        <h2 class="h2 about-heading" style="padding-left: 0; padding-right: 0;">Возможности</h2>
        <div class="about-grid">
            <div class="about-card">
                <h3 class="about-card__title">Занятия в браузере</h3>
                <p class="p24 about-card__text">
                    Урок идёт прямо на платформе — скачивать и устанавливать ничего не нужно.
                    Работает на компьютере, планшете и телефоне.
                </p>
            </div>
            <div class="about-card">
                <h3 class="about-card__title">Интерактивная доска</h3>
                <p class="p24 about-card__text">
                    Общая доска, презентации и демонстрация экрана. Разбирать задачи можно так же,
                    как у настоящей доски в классе.
                </p>
            </div>
            <div class="about-card">
                <h3 class="about-card__title">Записи уроков</h3>
                <p class="p24 about-card__text">
                    Занятие можно записать целиком. Ученик пересматривает объяснение столько раз,
                    сколько нужно, а пропущенный урок не выпадает из программы.
                </p>
            </div>
            <div class="about-card">
                <h3 class="about-card__title">Расписание</h3>
                <p class="p24 about-card__text">
                    Календарь занятий у преподавателя и ученика. Синхронизируется с Google Calendar,
                    о начале урока напоминает уведомление в браузере.
                </p>
            </div>
            <div class="about-card">
                <h3 class="about-card__title">Домашние задания</h3>
                <p class="p24 about-card__text">
                    Преподаватель выдаёт задание с файлами и сроком, ученик сдаёт работу на платформе.
                    Проверка и комментарии остаются в истории.
                </p>
            </div>
            <div class="about-card">
                <h3 class="about-card__title">Материалы</h3>
                <p class="p24 about-card__text">
                    Конспекты, презентации и файлы к занятиям лежат в одном месте и всегда доступны
                    ученику — не только в момент урока.
                </p>
            </div>
            <div class="about-card">
                <h3 class="about-card__title">Чат</h3>
                <p class="p24 about-card__text">
                    Переписка с учеником прямо на платформе. Вся история рядом с занятиями,
                    а не в отдельном мессенджере.
                </p>
            </div>
            <div class="about-card">
                <h3 class="about-card__title">Учёт оплат</h3>
                <p class="p24 about-card__text">
                    Видно, какие занятия оплачены, а какие нет — поштучно или помесячно.
                    Без ручных подсчётов и неловких напоминаний.
                </p>
            </div>
            <div class="about-card">
                <h3 class="about-card__title">Успеваемость</h3>
                <p class="p24 about-card__text">
                    Платформа отслеживает прогресс каждого ученика: посещаемость, сданные работы
                    и оценки. Преподаватель и ученик видят динамику, а не ощущения.
                </p>
            </div>
            <div class="about-card">
                <h3 class="about-card__title">Отзывы и личная страница</h3>
                <p class="p24 about-card__text">
                    Ученики оставляют отзывы после занятий, а преподаватель собирает их
                    на личной странице — она работает как визитка для новых учеников.
                </p>
            </div>
            <div class="about-card">
                <h3 class="about-card__title">Уведомления</h3>
                <p class="p24 about-card__text">
                    Напоминания о начале занятия, новых домашних заданиях и сообщениях
                    приходят прямо в браузер — ничего не теряется.
                </p>
            </div>
            <div class="about-card">
                <h3 class="about-card__title">Индивидуально и группами</h3>
                <p class="p24 about-card__text">
                    Занятие можно провести один на один или пригласить нескольких учеников —
                    и то и другое работает одинаково.
                </p>
            </div>
        </div>
    </section>

    <section class="about-section" id="screens">
        <h2 class="h2 about-heading" style="padding-left: 0; padding-right: 0;">Как это выглядит</h2>
        <p class="p24 about-text-limit" style="color: var(--gray); margin: 0;">
            Пять экранов, в которых проходит почти вся работа на платформе.
        </p>

        @php
            $screens = [
                // Четвёртый элемент — путь к видео: тогда вместо заглушки с подписью
                // показывается ролик, который стартует при переходе на этот экран.
                ['Расписание', 'Календарь занятий у преподавателя и ученика: видно, что уже прошло и что впереди. Синхронизируется с Google Calendar, а о начале урока напоминает уведомление.', 'Расписание занятий на неделю', '/videos/about/calendar.mp4'],
                ['Занятие', 'Урок идёт прямо в браузере: общая доска, презентации и демонстрация экрана. При необходимости занятие записывается целиком.', 'Занятие с доской и презентацией', '/videos/about/lesson.mp4'],
                ['Домашние задания', 'Преподаватель выдаёт задание с файлами и сроком, ученик сдаёт работу на платформе. Проверка и комментарии остаются в истории.', 'Домашнее задание и проверка работы', '/videos/about/homework.mp4'],
                ['Записи уроков', 'Все записи собраны в кабинете ученика. К объяснению можно вернуться перед контрольной или экзаменом — столько раз, сколько нужно.', 'Записи прошедших уроков', '/videos/about/recordings.mp4'],
                ['Ученики и оплаты', 'Список учеников, типы занятий и учёт оплат: видно, кто и за какие уроки заплатил, без ручных подсчётов.', 'Список учеников и оплаты', '/videos/about/payments.mp4'],
            ];
        @endphp

        {{-- Alpine подключён в layout, отдельный скрипт слайдеру не нужен --}}
        <div class="about-tabs" x-data="aboutTabs({{ count($screens) }})">

            <div class="about-tabs__list">
                @foreach ($screens as $i => [$title, $text, $label])
                    <button type="button" class="about-tab" :class="{ 'is-active': active === {{ $i }} }"
                        @click="go({{ $i }})" :aria-expanded="active === {{ $i }}">
                        {{-- Полоска слева одновременно отмечает активный пункт
                             и показывает, сколько осталось до автопереключения --}}
                        <span class="about-tab__bar" aria-hidden="true">
                            <span class="about-tab__fill"
                                :style="active === {{ $i }} ? 'height: ' + progress + '%' : 'height: 0%'"></span>
                        </span>
                        <span class="about-tab__title">{{ $title }}</span>
                        <span class="about-tab__reveal">
                            <span class="about-tab__clip">
                                <span class="p24 about-tab__text">{{ $text }}</span>
                            </span>
                        </span>
                    </button>
                @endforeach
            </div>

            <div class="about-tabs__media" @mouseenter="paused = true" @mouseleave="paused = false">
                <div class="about-tabs__viewport">
                <div class="about-tabs__track">
                    @foreach ($screens as $i => $screen)
                        @php [, , $label] = $screen; $video = $screen[3] ?? null; @endphp
                        <div class="about-tabs__slide" :class="{ 'is-active': active === {{ $i }} }"
                            :aria-hidden="active !== {{ $i }}">
                            @if ($video)
                                {{-- Ролик без звука стартует с начала, когда экран становится активным,
                                     и останавливается, когда уходит. Таймер автопереключения на этом
                                     экране подстраивается под длину ролика (см. aboutTabs). --}}
                                <video class="about-tabs__video" src="{{ $video }}" muted playsinline loop
                                    preload="auto" aria-label="{{ $label }}"
                                    x-effect="syncVideo($el, {{ $i }})"
                                    @loadedmetadata="setDuration({{ $i }}, $el.duration)"></video>
                            @else
                                @include('partials.about-shot', ['label' => $label])
                            @endif
                        </div>
                    @endforeach
                </div>
                </div>
            </div>
        </div>
    </section>

    <section class="about-section">
        <h2 class="h2 about-heading" style="padding-left: 0; padding-right: 0;">Как проходит занятие</h2>
        <div class="about-steps">
            <div class="about-step">
                <div class="about-step__number">1</div>
                <p class="p24 about-step__text">
                    Преподаватель ставит занятие в расписание и добавляет учеников.
                </p>
            </div>
            <div class="about-step">
                <div class="about-step__number">2</div>
                <p class="p24 about-step__text">
                    Ученик видит урок в своём кабинете и получает напоминание к началу.
                </p>
            </div>
            <div class="about-step">
                <div class="about-step__number">3</div>
                <p class="p24 about-step__text">
                    Занятие идёт с доской, презентациями и демонстрацией экрана — при желании с записью.
                </p>
            </div>
            <div class="about-step">
                <div class="about-step__number">4</div>
                <p class="p24 about-step__text">
                    После урока запись, материалы и домашнее задание остаются в кабинете.
                </p>
            </div>
        </div>
    </section>

    <section class="about-section">
        {{-- Тема намеренно не из слайдера выше: записи, ДЗ и расписание там уже показаны.
             Публичная страница преподавателя — единственное, чего нет ни на одном экране,
             и она подводит к «Направлениям» и кнопке «Найти специалиста» ниже. --}}
        <div class="about-spotlight">
            <div>
                <h3 class="h3">Преподавателя видно ещё до первого урока</h3>
                <p class="p24">
                    У каждого преподавателя на Serdal — своя открытая страница: предметы
                    и направления, опыт, формат занятий и отзывы учеников, которые у него
                    занимались. Ученик выбирает осознанно, а преподавателю не нужен отдельный
                    сайт — ссылку на профиль можно дать в любой соцсети или мессенджере.
                </p>
            </div>
            @include('partials.about-shot', ['label' => 'Страница преподавателя с отзывами учеников', 'ratio' => '4-3'])
        </div>
    </section>

    <section class="about-section">
        <div class="about-columns">
            <div class="about-column">
                <h3 class="h3">Ученикам<br>и родителям</h3>
                <ul class="about-list p24">
                    <li>Занятия с опытными преподавателями — профиль каждого открыт заранее</li>
                    <li>Уроки, задания и записи собраны в личном кабинете</li>
                    <li>Расписание с напоминанием перед началом занятия</li>
                    <li>Понятно видно, какие занятия оплачены, а какие ещё нет</li>
                </ul>
            </div>
            <div class="about-column about-column--accent">
                <h3 class="h3">Преподавателям</h3>
                <ul class="about-list p24">
                    <li>Своя страница на платформе, по которой вас находят ученики</li>
                    <li>Ученики, расписание и типы занятий в одном кабинете</li>
                    <li>Домашние задания, материалы и проверка работ</li>
                    <li>Учёт оплат по занятиям или по месяцам</li>
                    <li>Отзывы учеников на публичном профиле</li>
                </ul>
            </div>
        </div>
    </section>

    @php
        $directs = App\Models\Direct::withCount([
            'users' => fn ($query) => $query->isSpecialist()->where('is_active', true),
        ])->orderByDesc('users_count')->orderBy('name')->get();

        // Русская форма слова для счётчика преподавателей.
        $teacherWord = function (int $count): string {
            $mod100 = $count % 100;
            $mod10 = $count % 10;

            if ($mod100 >= 11 && $mod100 <= 14) return 'преподавателей';
            if ($mod10 === 1) return 'преподаватель';
            if ($mod10 >= 2 && $mod10 <= 4) return 'преподавателя';

            return 'преподавателей';
        };
    @endphp
    @if($directs->isNotEmpty())
        <section class="about-section">
            <h2 class="h2 about-heading" style="padding-left: 0; padding-right: 0;">Направления</h2>
            <p class="p24 about-text-limit" style="color: var(--gray); margin: 0;">
                Преподаватели платформы готовят к экзаменам, ведут школьные предметы и наставничество.
            </p>

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
                Ученикам — подобрать специалиста под свою цель. Преподавателям — оставить заявку,
                мы свяжемся и поможем перенести занятия на платформу.
            </p>
            <div class="about-cta">
                <a href="/#specialists" class="main-button w-button">Найти специалиста</a>
                <a href="{{ route('tariffs') }}" class="main-button about-cta__secondary w-button">Стать преподавателем</a>
            </div>
        </div>
    </section>

    <script>
        document.addEventListener('alpine:init', function () {
            Alpine.data('aboutTabs', function (count) {
                return {
                    active: 0,
                    progress: 0,     // 0..100 — заполнение полоски и таймер автопереключения
                    paused: false,
                    auto: true,
                    duration: 7000,  // мс на один экран
                    durations: {},   // переопределения по индексу (экраны с видео — длина ролика)
                    last: 0,

                    init() {
                        // Уважаем системную настройку «уменьшить движение»:
                        // без автоперелистывания полоску держим заполненной.
                        this.auto = !window.matchMedia('(prefers-reduced-motion: reduce)').matches;

                        if (!this.auto) {
                            this.progress = 100;
                            return;
                        }

                        this.last = performance.now();
                        this.tick();
                    },

                    tick() {
                        requestAnimationFrame(function (now) {
                            var dt = now - this.last;
                            this.last = now;

                            if (!this.paused) {
                                this.progress += (dt / (this.durations[this.active] || this.duration)) * 100;

                                if (this.progress >= 100) {
                                    this.progress = 0;
                                    this.active = (this.active + 1) % count;
                                }
                            }

                            this.tick();
                        }.bind(this));
                    },

                    go(i) {
                        this.active = i;
                        this.progress = this.auto ? 0 : 100;
                        this.last = performance.now();
                    },

                    setDuration(i, seconds) {
                        if (seconds && isFinite(seconds)) {
                            this.durations[i] = seconds * 1000;
                        }
                    },

                    // Вызывается через x-effect: перезапускается при каждой смене active
                    syncVideo(el, i) {
                        if (this.active === i) {
                            el.currentTime = 0;
                            var p = el.play();
                            if (p && p.catch) p.catch(function () {});
                        } else {
                            el.pause();
                        }
                    },
                };
            });
        });
    </script>

    <div class="about-footer-space"></div>

@endsection
