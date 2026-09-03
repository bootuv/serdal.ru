@extends('layout')

@section('title', 'Тарифы - Serdal')

@section('meta')
    <meta name="description"
        content="Тарифы платформы онлайн-обучения Serdal для репетиторов и образовательных центров: цены, характеристики, состав пакетов и условия оплаты.">
    <meta property="og:title" content="Тарифы - Serdal">
    <meta property="og:description" content="Тарифы платформы онлайн-обучения Serdal: цены, характеристики и состав пакетов.">
@endsection

@section('styles')
    <style>
        .tariffs-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 24px;
            margin-top: 40px;
        }

        .tariff-card {
            position: relative;
            display: flex;
            flex-direction: column;
            border: 1px solid #e4e4e4;
            border-radius: 16px;
            padding: 32px 28px;
            background: #fff;
        }

        .tariff-card.popular {
            border: 2px solid #ffc700;
            box-shadow: 0 8px 30px rgba(0, 0, 0, .06);
        }

        .tariff-badge {
            position: absolute;
            top: -14px;
            left: 50%;
            transform: translateX(-50%);
            background: #ffc700;
            color: #111;
            font-size: 14px;
            font-weight: 600;
            padding: 4px 14px;
            border-radius: 999px;
            white-space: nowrap;
        }

        .tariff-name {
            font-size: 24px;
            font-weight: 600;
            margin: 0 0 4px;
        }

        .tariff-short {
            font-size: 15px;
            line-height: 1.45;
            color: #666;
            min-height: 44px;
            margin-bottom: 16px;
        }

        .tariff-price {
            font-size: 34px;
            font-weight: 700;
            margin-bottom: 20px;
        }

        .tariff-price span {
            font-size: 16px;
            font-weight: 400;
            color: #666;
        }

        .tariff-specs {
            border-top: 1px solid #eee;
            padding-top: 16px;
            margin-bottom: 16px;
            font-size: 14px;
            line-height: 1.45;
            color: #444;
        }

        .tariff-specs div {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            margin-bottom: 8px;
            font-weight: 500;
            color: #222;
        }

        .tariff-specs svg {
            width: 16px;
            height: 16px;
            flex-shrink: 0;
            margin-top: 2px;
            color: #999;
        }

        .tariff-features {
            list-style: none;
            padding: 0;
            margin: 0 0 24px;
        }

        .tariff-features li {
            position: relative;
            padding-left: 24px;
            margin-bottom: 10px;
            font-size: 15px;
            line-height: 1.5;
        }

        .tariff-features li::before {
            content: "✓";
            position: absolute;
            left: 0;
            color: #2fb344;
            font-weight: 700;
        }

        .tariff-extras {
            border-top: 1px solid #eee;
            padding-top: 16px;
        }

        .tariff-extras li::before {
            content: "★";
            color: #ffc700;
        }

        .tariff-button {
            display: block;
            margin-top: auto;
            text-align: center;
            background: #111;
            color: #fff;
            border-radius: 10px;
            padding: 14px 20px;
            font-size: 16px;
            font-weight: 500;
            text-decoration: none;
        }

        .tariff-button:hover {
            opacity: .85;
        }

        .tariff-section {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px 40px;
        }

        .tariff-info {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px 0;
        }

        .tariff-info__heading {
            font-size: 30px;
            font-weight: 600;
            letter-spacing: -.5px;
            margin: 64px 0 28px;
        }

        /* Что такое подписка */
        .tariff-about {
            background: var(--bg1, #ebf5f4);
            border-radius: 24px;
            padding: 48px 40px;
        }

        .tariff-about__heading {
            margin: 0 0 16px;
        }

        .tariff-about p {
            max-width: 820px;
            margin: 0;
            color: var(--black, #202323);
        }

        .tariff-about__chips {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 28px;
        }

        .tariff-chip {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--white, #fff);
            border-radius: 999px;
            padding: 10px 18px;
            font-size: 15px;
            font-weight: 500;
            color: var(--black, #202323);
        }

        .tariff-chip svg {
            width: 18px;
            height: 18px;
            color: var(--gray, #5f6262);
        }

        /* Порядок оплаты — шаги */
        .tariff-steps {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 24px;
        }

        .tariff-step__number {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 56px;
            height: 56px;
            margin-bottom: 20px;
            border-radius: 18px;
            background: var(--brand-main, #ffe500);
            font-size: 26px;
            font-weight: 500;
            line-height: 1;
        }

        .tariff-step p {
            margin: 0;
            font-size: 16px;
            line-height: 1.55;
            color: var(--gray, #5f6262);
        }

        /* Гарантии и возврат */
        .tariff-guarantees {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 24px;
        }

        .tariff-guarantee {
            background: var(--bg1, #ebf5f4);
            border-radius: 24px;
            padding: 32px;
        }

        .tariff-guarantee svg {
            width: 28px;
            height: 28px;
            margin-bottom: 16px;
            color: var(--black, #202323);
        }

        .tariff-guarantee h3 {
            margin: 0 0 8px;
            font-size: 20px;
            font-weight: 600;
        }

        .tariff-guarantee p {
            margin: 0;
            font-size: 16px;
            line-height: 1.55;
            color: var(--gray, #5f6262);
        }

        .tariff-info__footnote {
            margin-top: 28px;
            font-size: 16px;
            color: var(--gray, #5f6262);
        }

        .tariff-info a {
            color: #0066cc;
        }

        .b2b-block {
            margin-top: 48px;
            border: 1px solid #e4e4e4;
            border-radius: 16px;
            padding: 32px 28px;
            background: #fff;
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 8px 48px;
            align-items: start;
        }

        .b2b-block__price {
            font-size: 34px;
            font-weight: 700;
            white-space: nowrap;
        }

        .b2b-block__price span {
            font-size: 16px;
            font-weight: 400;
            color: #666;
        }

        .b2b-block__price small {
            display: block;
            font-size: 14px;
            font-weight: 400;
            color: #666;
            margin-top: 4px;
        }

        .tariff-button.secondary {
            background: #fff;
            color: #111;
            border: 1px solid #111;
        }

        .tariff-button.secondary:hover {
            background: #111;
            color: #fff;
            opacity: 1;
        }

        .b2b-block .tariff-button {
            grid-column: 2;
            padding: 14px 32px;
        }

        @media (max-width: 767px) {
            .b2b-block {
                grid-template-columns: 1fr;
            }

            .b2b-block .tariff-button {
                grid-column: 1;
            }
        }

        @media (max-width: 991px) {
            .tariff-steps {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 767px) {
            .tariff-short {
                min-height: auto;
            }

            .tariff-about {
                padding: 32px 24px;
            }

            .tariff-steps,
            .tariff-guarantees {
                grid-template-columns: 1fr;
            }
        }
    </style>
@endsection

@section('content')
    <section class="page-title-section">
        <h1 class="h1">Тарифы</h1>
        <p class="p30 page-descriptions">Подписка для репетиторов и образовательных центров.<br>
            Все тарифы включают полный доступ к платформе онлайн-занятий Serdal.</p>
    </section>

    <div class="tariff-section">
        <div class="tariffs-grid">
            @foreach($tariffs as $tariff)
                <div class="tariff-card {{ $tariff->is_popular ? 'popular' : '' }}">
                    @if($tariff->is_popular)
                        <div class="tariff-badge">Популярный выбор</div>
                    @endif
                    <h2 class="tariff-name">{{ $tariff->name }}</h2>
                    <p class="tariff-short">{{ $tariff->short_description }}</p>
                    <div class="tariff-price">
                        {{ number_format($tariff->price, 0, ',', ' ') }} ₽<span>/мес</span>
                    </div>
                    <div class="tariff-specs">
                        <div><x-heroicon-m-user-group /> {{ $tariff->participants_label }}</div>
                        <div><x-heroicon-m-calendar-days /> {{ $tariff->lessons_label }}</div>
                        <div><x-heroicon-m-clock /> {{ $tariff->duration_label }}</div>
                        <div><x-heroicon-m-video-camera /> {{ $tariff->recording_label }}</div>
                    </div>
                    <ul class="tariff-features">
                        @foreach($tariff->features ?? [] as $feature)
                            <li>{{ $feature }}</li>
                        @endforeach
                    </ul>
                    @if(!empty($tariff->extra_features))
                        <ul class="tariff-features tariff-extras">
                            @foreach($tariff->extra_features as $feature)
                                <li>{{ $feature }}</li>
                            @endforeach
                        </ul>
                    @endif
                    <a href="{{ auth()->check() ? url('/tutor/subscription') : route('become-tutor') }}" class="tariff-button">
                        {{ $tariff->isFree() ? 'Начать бесплатно' : 'Подключить' }}
                    </a>
                </div>
            @endforeach
        </div>

        @if($b2b['enabled'])
            <div class="b2b-block">
                <div>
                    <h2 class="tariff-name">{{ $b2b['title'] }}</h2>
                    <p class="tariff-short">{{ $b2b['description'] }}</p>
                </div>
                <div class="b2b-block__price">
                    {{ $b2b['price_label'] }}<span>/мес</span>
                    @if($b2b['price_note'])
                        <small>{{ $b2b['price_note'] }}</small>
                    @endif
                </div>
                <ul class="tariff-features" style="margin-bottom: 0;">
                    @foreach($b2b['features'] as $feature)
                        <li>{{ $feature }}</li>
                    @endforeach
                </ul>
                <a href="mailto:{{ $b2b['email'] }}?subject=Подключение B2B" class="tariff-button secondary">Написать нам</a>
            </div>
        @endif
    </div>

    <div class="tariff-info">
        {{-- Что такое подписка --}}
        <div class="tariff-about">
            <h2 class="tariff-info__heading tariff-about__heading">Что такое подписка Serdal</h2>
            <p class="p24">
                Serdal — платформа для проведения онлайн-занятий. Подписка оформляется на 30 дней и даёт доступ
                ко всем возможностям выбранного тарифа в течение оплаченного периода.
            </p>
            <div class="tariff-about__chips">
                <span class="tariff-chip"><x-heroicon-o-video-camera /> Виртуальные комнаты для занятий</span>
                <span class="tariff-chip"><x-heroicon-o-pencil-square /> Интерактивная доска и демонстрация экрана</span>
                <span class="tariff-chip"><x-heroicon-o-calendar-days /> Расписание занятий и напоминания</span>
                <span class="tariff-chip"><x-heroicon-o-clipboard-document-check /> Домашние задания и проверка работ</span>
                <span class="tariff-chip"><x-heroicon-o-folder-open /> Материалы для учеников</span>
                <span class="tariff-chip"><x-heroicon-o-chart-bar /> Успеваемость учеников</span>
                <span class="tariff-chip"><x-heroicon-o-film /> Записи уроков</span>
                <span class="tariff-chip"><x-heroicon-o-banknotes /> Учёт оплат учеников</span>
                <span class="tariff-chip"><x-heroicon-o-chat-bubble-left-right /> Чат с учениками</span>
                <span class="tariff-chip"><x-heroicon-o-star /> Отзывы учеников</span>
                <span class="tariff-chip"><x-heroicon-o-user-circle /> Личные страницы преподавателей</span>
                <span class="tariff-chip"><x-heroicon-o-bell /> Уведомления о занятиях и сообщениях</span>
            </div>
        </div>

        {{-- Порядок оплаты --}}
        <h2 class="tariff-info__heading">Порядок оплаты</h2>
        <div class="tariff-steps">
            <div class="tariff-step">
                <div class="tariff-step__number">1</div>
                <p>Оплата банковской картой (МИР, Visa, Mastercard) или через СБП с помощью сервиса ЮKassa
                    в личном кабинете преподавателя.</p>
            </div>
            <div class="tariff-step">
                <div class="tariff-step__number">2</div>
                <p>Платёж обрабатывается на защищённой платёжной странице ЮKassa — данные карты
                    на нашем сервере не сохраняются.</p>
            </div>
            <div class="tariff-step">
                <div class="tariff-step__number">3</div>
                <p>Подписка активируется автоматически сразу после подтверждения оплаты.</p>
            </div>
            <div class="tariff-step">
                <div class="tariff-step__number">4</div>
                <p>Тариф можно повысить или понизить в любой момент в личном кабинете.</p>
            </div>
        </div>

        {{-- Гарантии и возврат --}}
        <h2 class="tariff-info__heading">Гарантийные условия и возврат</h2>
        <div class="tariff-guarantees">
            <div class="tariff-guarantee">
                <x-heroicon-o-arrow-uturn-left />
                <h3>14 дней на возврат</h3>
                <p>Если сервис не был использован (не проведено ни одного занятия в оплаченном периоде),
                    вы можете отказаться от подписки в течение 14 дней с момента оплаты и получить полный
                    возврат средств.</p>
            </div>
            <div class="tariff-guarantee">
                <x-heroicon-o-shield-check />
                <h3>Гарантия работы сервиса</h3>
                <p>При технической невозможности оказания услуги по нашей вине производится возврат средств
                    пропорционально неиспользованному периоду подписки.</p>
            </div>
            <div class="tariff-guarantee">
                <x-heroicon-o-credit-card />
                <h3>Возврат на ту же карту</h3>
                <p>Возврат осуществляется на ту же банковскую карту, с которой была произведена оплата,
                    в срок до 10 рабочих дней.</p>
            </div>
            <div class="tariff-guarantee">
                <x-heroicon-o-envelope />
                <h3>Как оформить возврат</h3>
                <p>Напишите на <a href="mailto:info@serdal.ru">info@serdal.ru</a> с указанием e-mail
                    учётной записи и даты платежа.</p>
            </div>
        </div>

        <p class="tariff-info__footnote">
            Полные условия — в <a href="{{ route('offer') }}">публичной оферте</a>.
        </p>
    </div>
@endsection
