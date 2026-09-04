@extends('layout')

@section('title', 'Публичная оферта - ' . $platform['name'])

@section('meta')
    <meta name="description"
        content="Публичная оферта на оказание услуг платформы онлайн-обучения {{ $platform['name'] }}: условия подписки, порядок оплаты и возврата средств, реквизиты.">
    <meta property="og:title" content="Публичная оферта - {{ $platform['name'] }}">
    <meta property="og:description" content="Публичная оферта на оказание услуг платформы онлайн-обучения {{ $platform['name'] }}.">
@endsection

@section('content')
    @php
        $executor = $legal['legal_name'] ?: 'владельца платформы ' . $platform['name'];
        $email = $legal['legal_email'];
        $paidTariffs = $tariffs->filter(fn($t) => !$t->isFree());
        $freeTariffs = $tariffs->filter(fn($t) => $t->isFree());
    @endphp

    <section class="page-title-section">
        <h1 class="h1">Публичная оферта</h1>
        <p class="p30 page-descriptions">Договор-оферта на оказание услуг доступа к платформе {{ $platform['name'] }}</p>
    </section>

    <div class="content" style="max-width: 900px; margin: 0 auto; padding: 40px 20px;">
        <div style="line-height: 1.8;">

            @if($offer['edition_date'])
                <p class="p24" style="color: #666;">
                    Редакция от {{ \Carbon\Carbon::parse($offer['edition_date'])->format('d.m.Y') }}
                </p>
            @endif

            <h2 class="p30" style="margin-top: 40px; margin-bottom: 20px; font-weight: 600;">1. Общие положения</h2>
            <p class="p24">
                Настоящий документ является публичной офертой
                {{ $executor }}
                (далее — «Исполнитель») и содержит условия оказания услуг доступа к онлайн-платформе
                {{ $platform['name'] }} (далее — «Платформа»), размещённой по адресу {{ $platform['host'] }}.
                Оплата подписки означает полное и безоговорочное принятие условий настоящей оферты (акцепт)
                в соответствии со ст. 437, 438 ГК РФ.
            </p>

            <h2 class="p30" style="margin-top: 40px; margin-bottom: 20px; font-weight: 600;">2. Предмет договора</h2>
            <p class="p24">
                Исполнитель предоставляет Заказчику (преподавателю или образовательному центру) доступ к функциональности
                Платформы для организации онлайн-обучения: виртуальные комнаты для занятий, расписание, домашние задания,
                записи занятий, учёт оплат и иные возможности согласно выбранному тарифу. Состав и объём услуг по каждому
                тарифу опубликованы на странице <a href="{{ route('tariffs') }}" style="color: #0066cc;">«Тарифы»</a>.
            </p>

            <h2 class="p30" style="margin-top: 40px; margin-bottom: 20px; font-weight: 600;">3. Тарифы и порядок оплаты</h2>
            <p class="p24">
                Стоимость и объём услуг определяются выбранным тарифом.
                @if(count($periodDays) === 1)
                    Подписка оформляется на {{ plural_ru($periodDays[0], 'календарный день', 'календарных дня', 'календарных дней') }}.
                @endif
            </p>
            <ul class="p24" style="margin-left: 20px; margin-top: 10px;">
                @foreach($tariffs as $tariff)
                    <li style="margin-bottom: 14px;">
                        <strong>Тариф «{{ $tariff->name }}»</strong> —
                        @if($tariff->isFree())
                            предоставляется бесплатно, период доступа
                            {{ plural_ru($tariff->period_days, 'день', 'дня', 'дней') }}.
                        @else
                            {{ number_format($tariff->price, 0, ',', ' ') }} ₽ за
                            {{ plural_ru($tariff->period_days, 'день', 'дня', 'дней') }}.
                        @endif
                        @if($tariff->short_description)
                            {{ $tariff->short_description }}
                        @endif
                        <ul style="margin-left: 20px; margin-top: 6px; font-size: 0.95em;">
                            <li>{{ $tariff->participants_label }}</li>
                            <li>{{ $tariff->lessons_label }}</li>
                            <li>{{ $tariff->duration_label }}</li>
                            <li>{{ $tariff->recording_label }}</li>
                            @foreach($tariff->features ?? [] as $feature)
                                <li>{{ $feature }}</li>
                            @endforeach
                            @foreach($tariff->extra_features ?? [] as $feature)
                                <li>{{ $feature }}</li>
                            @endforeach
                        </ul>
                    </li>
                @endforeach
                @if($b2b['enabled'])
                    <li style="margin-bottom: 14px;">
                        <strong>{{ $b2b['title'] }}</strong> — {{ $b2b['price_label'] }} в месяц{{ $b2b['price_note'] ? ' (' . $b2b['price_note'] . ')' : '' }}.
                        Условия подключения согласовываются индивидуально по e-mail
                        <a href="mailto:{{ $b2b['email'] }}" style="color: #0066cc;">{{ $b2b['email'] }}</a>.
                        @if(!empty($b2b['features']))
                            <ul style="margin-left: 20px; margin-top: 6px; font-size: 0.95em;">
                                @foreach($b2b['features'] as $feature)
                                    <li>{{ $feature }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </li>
                @endif
            </ul>
            <ul class="p24" style="margin-left: 20px; margin-top: 10px;">
                <li style="margin-bottom: 10px;">Оплата производится {{ $offer['payment_methods'] }} с помощью
                    платёжного сервиса {{ $offer['payment_provider'] }} на защищённой платёжной странице сервиса.</li>
                <li style="margin-bottom: 10px;">Услуга считается оказанной с момента предоставления доступа к
                    функциональности выбранного тарифа. Доступ активируется автоматически после подтверждения оплаты.</li>
                <li style="margin-bottom: 10px;">Подписка действует в течение оплаченного периода и не продлевается
                    автоматически без согласия Заказчика.</li>
                <li style="margin-bottom: 10px;">Заказчик может по собственному желанию включить автопродление подписки,
                    сохранив способ оплаты при её оформлении. В этом случае в конце каждого оплаченного периода с
                    сохранённого способа оплаты автоматически списывается стоимость выбранного тарифа за следующий
                    аналогичный период по действующим на момент списания ценам; о предстоящем списании Заказчик
                    уведомляется заранее. Автопродление можно отключить, а сохранённый способ оплаты — удалить в любой
                    момент в личном кабинете на странице «Подписка».</li>
                @if($freeTariffs->isNotEmpty() && $paidTariffs->isNotEmpty())
                    <li style="margin-bottom: 10px;">
                        Бесплатный тариф ({{ $freeTariffs->map(fn($t) => '«' . $t->name . '»')->implode(', ') }})
                        оплаты не требует и предоставляется в пределах указанных выше ограничений.
                    </li>
                @endif
                <li style="margin-bottom: 10px;">
                    <strong>Дополнительные занятия.</strong> Если лимит занятий тарифа исчерпан до окончания
                    оплаченного периода, Заказчик вправе приобрести дополнительные занятия по цене
                    {{ number_format($extraLessonPrice, 0, ',', ' ') }} ₽ за одно занятие,
                    не более {{ plural_ru($extraLessonsMax, 'занятия', 'занятий', 'занятий') }} за одну покупку.
                    Дополнительные занятия зачисляются после подтверждения оплаты, расходуются после
                    исчерпания лимита тарифа, не имеют срока действия и сохраняются при смене тарифа,
                    продлении и истечении подписки. Для проведения занятий за счёт дополнительных занятий
                    требуется действующая подписка (в том числе бесплатный тариф).
                </li>
            </ul>

            <h2 class="p30" style="margin-top: 40px; margin-bottom: 20px; font-weight: 600;">4. Возврат денежных средств
            </h2>
            <ul class="p24" style="margin-left: 20px; margin-top: 10px;">
                <li style="margin-bottom: 10px;">Заказчик вправе отказаться от услуги в течение
                    {{ plural_ru($offer['refund_days'], 'календарного дня', 'календарных дней', 'календарных дней') }} с
                    момента оплаты при условии, что услуга не была использована (не проведено ни одного занятия в
                    оплаченном периоде), — в этом случае возвращается полная стоимость подписки.</li>
                <li style="margin-bottom: 10px;">Возврат стоимости дополнительных занятий возможен в течение
                    {{ plural_ru($offer['refund_days'], 'календарного дня', 'календарных дней', 'календарных дней') }} с
                    момента оплаты при условии, что ни одно из приобретённых этим платежом занятий не использовано.
                    Частично использованные пакеты дополнительных занятий возврату не подлежат.</li>
                <li style="margin-bottom: 10px;">При невозможности оказания услуги по вине Исполнителя средства
                    возвращаются пропорционально неиспользованному периоду.</li>
                <li style="margin-bottom: 10px;">Возврат производится на банковскую карту, с которой была произведена
                    оплата, в течение
                    {{ plural_ru($offer['refund_processing_days'], 'рабочего дня', 'рабочих дней', 'рабочих дней') }}
                    с момента одобрения заявки на возврат.</li>
                <li style="margin-bottom: 10px;">Заявка на возврат направляется на e-mail
                    <a href="mailto:{{ $email }}" style="color: #0066cc;">{{ $email }}</a>.
                </li>
            </ul>

            <h2 class="p30" style="margin-top: 40px; margin-bottom: 20px; font-weight: 600;">5. Права и обязанности сторон
            </h2>
            <p class="p24">
                Исполнитель обязуется обеспечивать работоспособность Платформы и техническую поддержку. Заказчик обязуется
                использовать Платформу в соответствии с
                <a href="{{ route('terms') }}" style="color: #0066cc;">Условиями использования</a> и законодательством РФ.
                Обработка персональных данных осуществляется согласно
                <a href="{{ route('privacy') }}" style="color: #0066cc;">Политике конфиденциальности</a>.
            </p>

            <h2 class="p30" style="margin-top: 40px; margin-bottom: 20px; font-weight: 600;">6. Реквизиты Исполнителя</h2>
            <p class="p24" style="margin-top: 10px;">
                @if($legal['legal_name'])
                    {{ $legal['legal_name'] }}<br>
                @endif
                @if($legal['legal_inn'])
                    ИНН: {{ $legal['legal_inn'] }}<br>
                @endif
                @if($legal['legal_ogrn'])
                    ОГРН/ОГРНИП: {{ $legal['legal_ogrn'] }}<br>
                @endif
                @if($legal['legal_address'])
                    Адрес: {{ $legal['legal_address'] }}<br>
                @endif
                Сайт: <a href="{{ $platform['url'] }}" style="color: #0066cc;">{{ $platform['host'] }}</a><br>
                E-mail: <a href="mailto:{{ $email }}" style="color: #0066cc;">{{ $email }}</a>
                @if($legal['legal_phone'])
                    <br>Телефон: {{ $legal['legal_phone'] }}
                @endif
            </p>

        </div>
    </div>
@endsection
