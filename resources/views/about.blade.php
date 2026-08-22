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

        .about-manifest {
            background-color: var(--bg1);
            border-radius: 32px;
            padding: 64px;
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
            background-color: var(--brand-secondary);
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

        /* --- Слайдер со скриншотами ---------------------------------------- */
        .about-slider {
            display: flex;
            gap: 24px;
            margin-top: 40px;
            padding-bottom: 20px;
            overflow-x: auto;
            scroll-snap-type: x mandatory;
            -webkit-overflow-scrolling: touch;
            scrollbar-color: var(--bg3) transparent;
        }

        .about-slider::-webkit-scrollbar {
            height: 8px;
        }

        .about-slider::-webkit-scrollbar-thumb {
            border-radius: 999px;
            background-color: var(--bg3);
        }

        .about-slide {
            flex: 0 0 520px;
            scroll-snap-align: start;
        }

        .about-hint {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-top: 12px;
            color: var(--icon);
            font-size: 20px;
            line-height: 28px;
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

        .about-ticker {
            margin-top: 40px;
        }

        @media screen and (max-width: 991px) {
            .about-section {
                margin-top: 72px;
            }

            .about-manifest {
                padding: 48px 40px;
            }

            .about-grid,
            .about-steps {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .about-card__title {
                font-size: 24px;
                line-height: 32px;
            }

            .about-slide {
                flex-basis: 420px;
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

            .about-manifest {
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

            .about-slide {
                flex-basis: 84%;
            }

            .about-shot__caption,
            .about-hint {
                font-size: 18px;
                line-height: 26px;
            }

            .about-spotlight {
                grid-template-columns: minmax(0, 1fr);
                gap: 32px;
                padding: 32px 24px;
                border-radius: 24px;
            }

            .about-ticker {
                margin-top: 32px;
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
                <h3 class="about-card__title">Индивидуально и группами</h3>
                <p class="p24 about-card__text">
                    Занятие можно провести один на один или пригласить нескольких учеников —
                    и то и другое работает одинаково.
                </p>
            </div>
        </div>
    </section>

    <section class="about-section">
        <h2 class="h2 about-heading" style="padding-left: 0; padding-right: 0;">Как это выглядит</h2>
        <p class="p24 about-text-limit" style="color: var(--gray); margin: 0;">
            Несколько экранов платформы — так их видят преподаватель и ученик.
        </p>
        <div class="about-slider">
            <div class="about-slide">
                @include('partials.about-shot', ['label' => 'Расписание занятий на неделю'])
            </div>
            <div class="about-slide">
                @include('partials.about-shot', ['label' => 'Занятие с доской и презентацией'])
            </div>
            <div class="about-slide">
                @include('partials.about-shot', ['label' => 'Домашнее задание и проверка работы'])
            </div>
            <div class="about-slide">
                @include('partials.about-shot', ['label' => 'Записи прошедших уроков'])
            </div>
            <div class="about-slide">
                @include('partials.about-shot', ['label' => 'Список учеников и оплаты'])
            </div>
        </div>
        <span class="about-hint">Листайте вбок &rarr;</span>
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
        <div class="about-spotlight">
            <div>
                <h3 class="h3">Урок не заканчивается вместе со звонком</h3>
                <p class="p24">
                    Запись занятия, разобранные на доске задачи, выданное домашнее задание и все
                    материалы остаются в кабинете ученика. К ним можно вернуться через неделю
                    перед контрольной или через полгода перед экзаменом.
                </p>
            </div>
            @include('partials.about-shot', ['label' => 'Запись урока в кабинете ученика', 'ratio' => '4-3'])
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

    @php($directs = App\Models\Direct::all())
    @if($directs->isNotEmpty())
        <section class="about-section">
            <h2 class="h2 about-heading" style="padding-left: 0; padding-right: 0;">Направления</h2>
            <p class="p24 about-text-limit" style="color: var(--gray); margin: 0;">
                Преподаватели платформы готовят к экзаменам, ведут школьные предметы и наставничество.
            </p>
        </section>

        {{-- Бегущая строка: тот же компонент, что и на главной. Список дублируется,
             чтобы анимация ticker зациклилась без разрыва. --}}
        <section class="directions-row about-ticker">
            <ul role="list" class="directions">
                @foreach($directs as $direct)
                    <li class="direction">
                        <div class="p30">{{ $direct->name }}</div>
                    </li>
                @endforeach
                @foreach($directs as $direct)
                    <li class="direction">
                        <div class="p30">{{ $direct->name }}</div>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    <section class="about-section">
        <h2 class="h2 about-heading" style="padding-left: 0; padding-right: 0;">С чего начать</h2>
        <p class="p24 about-text-limit" style="color: var(--gray); margin: 0;">
            Ученикам — подобрать специалиста под свою цель. Преподавателям — оставить заявку,
            мы свяжемся и поможем перенести занятия на платформу.
        </p>
        <div class="about-cta">
            <a href="/#specialists" class="main-button w-button">Найти специалиста</a>
            <a href="{{ route('become-tutor') }}" class="main-button about-cta__secondary w-button">Стать преподавателем</a>
        </div>
    </section>

    <div class="about-footer-space"></div>

@endsection
