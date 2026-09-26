@extends('layout')

@section('title', 'Тарифы для репетиторов — Serdal')
@section('description', 'Тарифы платформы онлайн-обучения Serdal для репетиторов и образовательных центров: бесплатный старт, цены на подписку, состав пакетов, порядок оплаты и возврата.')

@push('jsonld')
    @php
        $tariffOffers = $tariffs->map(fn ($tariff) => array_filter(array: [
            '@type' => 'Offer',
            'name' => 'Тариф «' . $tariff->name . '»',
            'description' => \App\Support\Seo::text($tariff->short_description, 200) ?: null,
            'price' => (string) (int) $tariff->price,
            'priceCurrency' => 'RUB',
            'url' => \App\Support\Seo::canonical() . '#' . $tariff->slug,
            'availability' => 'https://schema.org/InStock',
            'eligibleDuration' => ['@type' => 'QuantitativeValue', 'value' => $tariff->period_days ?? 30, 'unitCode' => 'DAY'],
            'seller' => ['@id' => \App\Support\Seo::url('#organization')],
        ], callback: fn ($value) => $value !== null && $value !== ''))->values()->all();
    @endphp
    {!! \App\Support\Seo::jsonLd(\App\Support\Seo::breadcrumbs([['name' => 'Тарифы', 'url' => \App\Support\Seo::canonical()]])) !!}
    {!! \App\Support\Seo::jsonLd([
        '@type' => 'Service',
        'name' => 'Подписка ' . $platform['name'] . ' для преподавателей',
        'serviceType' => 'Платформа для онлайн-занятий',
        'description' => 'Доступ к виртуальным комнатам, интерактивной доске, записям занятий, расписанию, домашним заданиям и учёту оплат.',
        'provider' => ['@id' => \App\Support\Seo::url('#organization')],
        'areaServed' => 'RU',
        'url' => \App\Support\Seo::canonical(),
        'offers' => $tariffOffers,
    ]) !!}
@endpush

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

        /* Сворачиваемый список возможностей */
        .tariff-details {
            border-top: 1px solid #eee;
            padding-top: 12px;
            margin-bottom: 24px;
        }

        .tariff-details__toggle {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            cursor: pointer;
            list-style: none;
            font-size: 15px;
            font-weight: 500;
            color: #222;
            user-select: none;
        }

        .tariff-details__toggle::-webkit-details-marker {
            display: none;
        }

        .tariff-details__toggle:hover {
            color: #000;
        }

        .tariff-details__label::before {
            content: attr(data-closed);
        }

        .tariff-details[open] .tariff-details__label::before {
            content: attr(data-open);
        }

        .tariff-details__chevron {
            width: 18px;
            height: 18px;
            flex-shrink: 0;
            color: #999;
            transition: transform .2s ease;
        }

        .tariff-details[open] .tariff-details__chevron {
            transform: rotate(180deg);
        }

        .tariff-details .tariff-features {
            margin: 14px 0 0;
        }

        .tariff-details .tariff-extras {
            margin-top: 14px;
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

        .tariff-step {
            display: flex;
            flex-direction: column;
            background: var(--bg1, #ebf5f4);
            border-radius: 24px;
            padding: 28px;
        }

        .tariff-step__number {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 48px;
            height: 48px;
            margin-bottom: 20px;
            border-radius: 14px;
            background: var(--brand-main, #ffe500);
            font-size: 22px;
            font-weight: 600;
            line-height: 1;
            color: var(--black, #202323);
        }

        .tariff-step p {
            margin: 0;
            font-size: 16px;
            line-height: 1.55;
            color: var(--gray, #5f6262);
        }

        /* Порядок оплаты — докупка занятий */
        .tariff-addon {
            display: grid;
            grid-template-columns: auto 1fr;
            align-items: center;
            gap: 20px 24px;
            margin-top: 24px;
            background: var(--brand-main-light, #fffbd6);
            border: 1px solid var(--brand-main, #ffe500);
            border-radius: 24px;
            padding: 28px 32px;
        }

        .tariff-addon__icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 56px;
            height: 56px;
            border-radius: 18px;
            background: var(--brand-main, #ffe500);
        }

        .tariff-addon__icon svg {
            width: 28px;
            height: 28px;
            color: var(--black, #202323);
        }

        .tariff-addon h3 {
            margin: 0 0 6px;
            font-size: 20px;
            font-weight: 600;
            color: var(--black, #202323);
        }

        .tariff-addon p {
            margin: 0;
            font-size: 16px;
            line-height: 1.55;
            color: var(--gray, #5f6262);
        }

        .tariff-addon strong {
            font-weight: 600;
            color: var(--black, #202323);
            white-space: nowrap;
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

            .tariff-step {
                padding: 24px;
            }

            .tariff-addon {
                grid-template-columns: 1fr;
                padding: 24px;
            }
        }
    </style>
@endsection

@section('content')
    <section class="page-title-section">
        <h1 class="h1">Тарифы</h1>
        <p class="p30 page-descriptions">Подписка для репетиторов и образовательных центров.<br>
            Все тарифы включают полный доступ к платформе онлайн-занятий {{ $platform['name'] }}.</p>
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
                    @if($tariff->hasYearly())
                        <div style="margin-top: -12px; margin-bottom: 16px; font-size: 14px; line-height: 1.45; color: #666;">
                            или {{ number_format($tariff->yearly_price, 0, ',', ' ') }} ₽ при оплате за год
                            @if($tariff->yearlyDiscountPercent() > 0)
                                <span style="color: #2fb344; font-weight: 600;">(−{{ $tariff->yearlyDiscountPercent() }}%)</span>
                            @endif
                        </div>
                    @endif
                    <div class="tariff-specs">
                        <div><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"> <path d="M10 9a3 3 0 1 0 0-6 3 3 0 0 0 0 6ZM6 8a2 2 0 1 1-4 0 2 2 0 0 1 4 0ZM1.49 15.326a.78.78 0 0 1-.358-.442 3 3 0 0 1 4.308-3.516 6.484 6.484 0 0 0-1.905 3.959c-.023.222-.014.442.025.654a4.97 4.97 0 0 1-2.07-.655ZM16.44 15.98a4.97 4.97 0 0 0 2.07-.654.78.78 0 0 0 .357-.442 3 3 0 0 0-4.308-3.517 6.484 6.484 0 0 1 1.907 3.96 2.32 2.32 0 0 1-.026.654ZM18 8a2 2 0 1 1-4 0 2 2 0 0 1 4 0ZM5.304 16.19a.844.844 0 0 1-.277-.71 5 5 0 0 1 9.947 0 .843.843 0 0 1-.277.71A6.975 6.975 0 0 1 10 18a6.974 6.974 0 0 1-4.696-1.81Z"/> </svg> {{ $tariff->participants_label }}</div>
                        <div><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"> <path d="M5.25 12a.75.75 0 0 1 .75-.75h.01a.75.75 0 0 1 .75.75v.01a.75.75 0 0 1-.75.75H6a.75.75 0 0 1-.75-.75V12ZM6 13.25a.75.75 0 0 0-.75.75v.01c0 .414.336.75.75.75h.01a.75.75 0 0 0 .75-.75V14a.75.75 0 0 0-.75-.75H6ZM7.25 12a.75.75 0 0 1 .75-.75h.01a.75.75 0 0 1 .75.75v.01a.75.75 0 0 1-.75.75H8a.75.75 0 0 1-.75-.75V12ZM8 13.25a.75.75 0 0 0-.75.75v.01c0 .414.336.75.75.75h.01a.75.75 0 0 0 .75-.75V14a.75.75 0 0 0-.75-.75H8ZM9.25 10a.75.75 0 0 1 .75-.75h.01a.75.75 0 0 1 .75.75v.01a.75.75 0 0 1-.75.75H10a.75.75 0 0 1-.75-.75V10ZM10 11.25a.75.75 0 0 0-.75.75v.01c0 .414.336.75.75.75h.01a.75.75 0 0 0 .75-.75V12a.75.75 0 0 0-.75-.75H10ZM9.25 14a.75.75 0 0 1 .75-.75h.01a.75.75 0 0 1 .75.75v.01a.75.75 0 0 1-.75.75H10a.75.75 0 0 1-.75-.75V14ZM12 9.25a.75.75 0 0 0-.75.75v.01c0 .414.336.75.75.75h.01a.75.75 0 0 0 .75-.75V10a.75.75 0 0 0-.75-.75H12ZM11.25 12a.75.75 0 0 1 .75-.75h.01a.75.75 0 0 1 .75.75v.01a.75.75 0 0 1-.75.75H12a.75.75 0 0 1-.75-.75V12ZM12 13.25a.75.75 0 0 0-.75.75v.01c0 .414.336.75.75.75h.01a.75.75 0 0 0 .75-.75V14a.75.75 0 0 0-.75-.75H12ZM13.25 10a.75.75 0 0 1 .75-.75h.01a.75.75 0 0 1 .75.75v.01a.75.75 0 0 1-.75.75H14a.75.75 0 0 1-.75-.75V10ZM14 11.25a.75.75 0 0 0-.75.75v.01c0 .414.336.75.75.75h.01a.75.75 0 0 0 .75-.75V12a.75.75 0 0 0-.75-.75H14Z"/> <path fill-rule="evenodd" d="M5.75 2a.75.75 0 0 1 .75.75V4h7V2.75a.75.75 0 0 1 1.5 0V4h.25A2.75 2.75 0 0 1 18 6.75v8.5A2.75 2.75 0 0 1 15.25 18H4.75A2.75 2.75 0 0 1 2 15.25v-8.5A2.75 2.75 0 0 1 4.75 4H5V2.75A.75.75 0 0 1 5.75 2Zm-1 5.5c-.69 0-1.25.56-1.25 1.25v6.5c0 .69.56 1.25 1.25 1.25h10.5c.69 0 1.25-.56 1.25-1.25v-6.5c0-.69-.56-1.25-1.25-1.25H4.75Z" clip-rule="evenodd"/> </svg> {{ $tariff->lessons_label }}</div>
                        <div><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"> <path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm.75-13a.75.75 0 0 0-1.5 0v5c0 .414.336.75.75.75h4a.75.75 0 0 0 0-1.5h-3.25V5Z" clip-rule="evenodd"/> </svg> {{ $tariff->duration_label }}</div>
                        <div><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"> <path d="M3.25 4A2.25 2.25 0 0 0 1 6.25v7.5A2.25 2.25 0 0 0 3.25 16h7.5A2.25 2.25 0 0 0 13 13.75v-7.5A2.25 2.25 0 0 0 10.75 4h-7.5ZM19 4.75a.75.75 0 0 0-1.28-.53l-3 3a.75.75 0 0 0-.22.53v4.5c0 .199.079.39.22.53l3 3a.75.75 0 0 0 1.28-.53V4.75Z"/> </svg> {{ $tariff->recording_label }}</div>
                    </div>
                    @php($featuresCount = count($tariff->features ?? []) + count($tariff->extra_features ?? []))
                    @if($featuresCount > 0)
                        {{-- Список возможностей свёрнут по умолчанию (нативный details, работает без JS) --}}
                        <details class="tariff-details">
                            <summary class="tariff-details__toggle">
                                <span class="tariff-details__label" data-closed="Все возможности ({{ $featuresCount }})" data-open="Скрыть возможности"></span>
                                <svg class="tariff-details__chevron" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd"/></svg>
                            </summary>
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
                        </details>
                    @endif
                    <a href="{{ auth()->check() ? url('/tutor/subscription') : route('become-tutor', ['tariff' => $tariff->slug]) }}" class="tariff-button">
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
            <h2 class="tariff-info__heading tariff-about__heading">Что такое подписка {{ $platform['name'] }}</h2>
            <p class="p24">
                {{ $platform['name'] }} — платформа для проведения онлайн-занятий.
                @if(count($periodDays) === 1)
                    Подписка оформляется на {{ plural_ru($periodDays[0], 'день', 'дня', 'дней') }} и даёт доступ
                @else
                    Подписка даёт доступ
                @endif
                ко всем возможностям выбранного тарифа в течение оплаченного периода.
            </p>
            <div class="tariff-about__chips">
                <span class="tariff-chip"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"> <path stroke-linecap="round" stroke-linejoin="round" d="m15.75 10.5 4.72-4.72a.75.75 0 0 1 1.28.53v11.38a.75.75 0 0 1-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 0 0 2.25-2.25v-9a2.25 2.25 0 0 0-2.25-2.25h-9A2.25 2.25 0 0 0 2.25 7.5v9a2.25 2.25 0 0 0 2.25 2.25Z"/> </svg> Виртуальные комнаты для занятий</span>
                <span class="tariff-chip"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"> <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10"/> </svg> Интерактивная доска и демонстрация экрана</span>
                <span class="tariff-chip"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"> <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5m-9-6h.008v.008H12v-.008ZM12 15h.008v.008H12V15Zm0 2.25h.008v.008H12v-.008ZM9.75 15h.008v.008H9.75V15Zm0 2.25h.008v.008H9.75v-.008ZM7.5 15h.008v.008H7.5V15Zm0 2.25h.008v.008H7.5v-.008Zm6.75-4.5h.008v.008h-.008v-.008Zm0 2.25h.008v.008h-.008V15Zm0 2.25h.008v.008h-.008v-.008Zm2.25-4.5h.008v.008H16.5v-.008Zm0 2.25h.008v.008H16.5V15Z"/> </svg> Расписание занятий и напоминания</span>
                <span class="tariff-chip"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"> <path stroke-linecap="round" stroke-linejoin="round" d="M11.35 3.836c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m8.9-4.414c.376.023.75.05 1.124.08 1.131.094 1.976 1.057 1.976 2.192V16.5A2.25 2.25 0 0 1 18 18.75h-2.25m-7.5-10.5H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V18.75m-7.5-10.5h6.375c.621 0 1.125.504 1.125 1.125v9.375m-8.25-3 1.5 1.5 3-3.75"/> </svg> Домашние задания и проверка работ</span>
                <span class="tariff-chip"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"> <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 9.776c.112-.017.227-.026.344-.026h15.812c.117 0 .232.009.344.026m-16.5 0a2.25 2.25 0 0 0-1.883 2.542l.857 6a2.25 2.25 0 0 0 2.227 1.932H19.05a2.25 2.25 0 0 0 2.227-1.932l.857-6a2.25 2.25 0 0 0-1.883-2.542m-16.5 0V6A2.25 2.25 0 0 1 6 3.75h3.879a1.5 1.5 0 0 1 1.06.44l2.122 2.12a1.5 1.5 0 0 0 1.06.44H18A2.25 2.25 0 0 1 20.25 9v.776"/> </svg> Материалы для учеников</span>
                <span class="tariff-chip"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"> <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z"/> </svg> Успеваемость учеников</span>
                <span class="tariff-chip"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"> <path stroke-linecap="round" stroke-linejoin="round" d="M3.375 19.5h17.25m-17.25 0a1.125 1.125 0 0 1-1.125-1.125M3.375 19.5h1.5C5.496 19.5 6 18.996 6 18.375m-3.75 0V5.625m0 12.75v-1.5c0-.621.504-1.125 1.125-1.125m18.375 2.625V5.625m0 12.75c0 .621-.504 1.125-1.125 1.125m1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125m0 3.75h-1.5A1.125 1.125 0 0 1 18 18.375M20.625 4.5H3.375m17.25 0c.621 0 1.125.504 1.125 1.125M20.625 4.5h-1.5C18.504 4.5 18 5.004 18 5.625m3.75 0v1.5c0 .621-.504 1.125-1.125 1.125M3.375 4.5c-.621 0-1.125.504-1.125 1.125M3.375 4.5h1.5C5.496 4.5 6 5.004 6 5.625m-3.75 0v1.5c0 .621.504 1.125 1.125 1.125m0 0h1.5m-1.5 0c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125m1.5-3.75C5.496 8.25 6 7.746 6 7.125v-1.5M4.875 8.25C5.496 8.25 6 8.754 6 9.375v1.5m0-5.25v5.25m0-5.25C6 5.004 6.504 4.5 7.125 4.5h9.75c.621 0 1.125.504 1.125 1.125m1.125 2.625h1.5m-1.5 0A1.125 1.125 0 0 1 18 7.125v-1.5m1.125 2.625c-.621 0-1.125.504-1.125 1.125v1.5m2.625-2.625c.621 0 1.125.504 1.125 1.125v1.5c0 .621-.504 1.125-1.125 1.125M18 5.625v5.25M7.125 12h9.75m-9.75 0A1.125 1.125 0 0 1 6 10.875M7.125 12C6.504 12 6 12.504 6 13.125m0-2.25C6 11.496 5.496 12 4.875 12M18 10.875c0 .621-.504 1.125-1.125 1.125M18 10.875c0 .621.504 1.125 1.125 1.125m-2.25 0c.621 0 1.125.504 1.125 1.125m-12 5.25v-5.25m0 5.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125m-12 0v-1.5c0-.621-.504-1.125-1.125-1.125M18 18.375v-5.25m0 5.25v-1.5c0-.621.504-1.125 1.125-1.125M18 13.125v1.5c0 .621.504 1.125 1.125 1.125M18 13.125c0-.621.504-1.125 1.125-1.125M6 13.125v1.5c0 .621-.504 1.125-1.125 1.125M6 13.125C6 12.504 5.496 12 4.875 12m-1.5 0h1.5m-1.5 0c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125M19.125 12h1.5m0 0c.621 0 1.125.504 1.125 1.125v1.5c0 .621-.504 1.125-1.125 1.125m-17.25 0h1.5m14.25 0h1.5"/> </svg> Записи уроков</span>
                <span class="tariff-chip"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"> <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z"/> </svg> Учёт оплат учеников</span>
                <span class="tariff-chip"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"> <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 8.511c.884.284 1.5 1.128 1.5 2.097v4.286c0 1.136-.847 2.1-1.98 2.193-.34.027-.68.052-1.02.072v3.091l-3-3c-1.354 0-2.694-.055-4.02-.163a2.115 2.115 0 0 1-.825-.242m9.345-8.334a2.126 2.126 0 0 0-.476-.095 48.64 48.64 0 0 0-8.048 0c-1.131.094-1.976 1.057-1.976 2.192v4.286c0 .837.46 1.58 1.155 1.951m9.345-8.334V6.637c0-1.621-1.152-3.026-2.76-3.235A48.455 48.455 0 0 0 11.25 3c-2.115 0-4.198.137-6.24.402-1.608.209-2.76 1.614-2.76 3.235v6.226c0 1.621 1.152 3.026 2.76 3.235.577.075 1.157.14 1.74.194V21l4.155-4.155"/> </svg> Чат с учениками</span>
                <span class="tariff-chip"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"> <path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0 .475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 0 0-.182.557l1.285 5.385a.562.562 0 0 1-.84.61l-4.725-2.885a.562.562 0 0 0-.586 0L6.982 20.54a.562.562 0 0 1-.84-.61l1.285-5.386a.562.562 0 0 0-.182-.557l-4.204-3.602a.562.562 0 0 1 .321-.988l5.518-.442a.563.563 0 0 0 .475-.345L11.48 3.5Z"/> </svg> Отзывы учеников</span>
                <span class="tariff-chip"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"> <path stroke-linecap="round" stroke-linejoin="round" d="M17.982 18.725A7.488 7.488 0 0 0 12 15.75a7.488 7.488 0 0 0-5.982 2.975m11.963 0a9 9 0 1 0-11.963 0m11.963 0A8.966 8.966 0 0 1 12 21a8.966 8.966 0 0 1-5.982-2.275M15 9.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/> </svg> Личные страницы преподавателей</span>
                <span class="tariff-chip"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"> <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0"/> </svg> Уведомления о занятиях и сообщениях</span>
            </div>
        </div>

        {{-- Порядок оплаты --}}
        <h2 class="tariff-info__heading">Порядок оплаты</h2>
        <div class="tariff-steps">
            <div class="tariff-step">
                <div class="tariff-step__number">1</div>
                <p>Оплата {{ $offer['payment_methods'] }} с помощью сервиса {{ $offer['payment_provider'] }}
                    в личном кабинете преподавателя.</p>
            </div>
            <div class="tariff-step">
                <div class="tariff-step__number">2</div>
                <p>Платёж обрабатывается на защищённой платёжной странице {{ $offer['payment_provider'] }} — данные карты
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
        <div class="tariff-addon">
            <div class="tariff-addon__icon"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"> <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v6m3-3H9m12 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/> </svg></div>
            <div>
                <h3>Докупка занятий</h3>
                <p>Если занятия по тарифу закончились раньше конца месяца, можно докупить нужное количество
                    по <strong>{{ number_format($extraLessonPrice, 0, ',', ' ') }} ₽ за занятие</strong>.
                    Докупленные занятия не сгорают и сохраняются при смене тарифа.</p>
            </div>
        </div>

        {{-- Гарантии и возврат --}}
        <h2 class="tariff-info__heading">Гарантийные условия и возврат</h2>
        <div class="tariff-guarantees">
            <div class="tariff-guarantee">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"> <path stroke-linecap="round" stroke-linejoin="round" d="M9 15 3 9m0 0 6-6M3 9h12a6 6 0 0 1 0 12h-3"/> </svg>
                <h3>{{ plural_ru($offer['refund_days'], 'день', 'дня', 'дней') }} на возврат</h3>
                <p>Если сервис не был использован (не проведено ни одного занятия в оплаченном периоде),
                    вы можете отказаться от подписки в течение {{ plural_ru($offer['refund_days'], 'дня', 'дней', 'дней') }}
                    с момента оплаты и получить полный возврат средств.</p>
            </div>
            <div class="tariff-guarantee">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"> <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z"/> </svg>
                <h3>Гарантия работы сервиса</h3>
                <p>При технической невозможности оказания услуги по нашей вине производится возврат средств
                    пропорционально неиспользованному периоду подписки.</p>
            </div>
            <div class="tariff-guarantee">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"> <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z"/> </svg>
                <h3>Возврат на ту же карту</h3>
                <p>Возврат осуществляется на ту же банковскую карту, с которой была произведена оплата,
                    в срок до {{ plural_ru($offer['refund_processing_days'], 'рабочего дня', 'рабочих дней', 'рабочих дней') }}.</p>
            </div>
            <div class="tariff-guarantee">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"> <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75"/> </svg>
                <h3>Как оформить возврат</h3>
                <p>Напишите на <a href="mailto:{{ $legal['legal_email'] }}">{{ $legal['legal_email'] }}</a> с указанием e-mail
                    учётной записи и даты платежа.</p>
            </div>
        </div>

        <p class="tariff-info__footnote">
            Полные условия — в <a href="{{ route('offer') }}">публичной оферте</a>.
        </p>
    </div>
@endsection
