@extends('layout')

@section('title', 'Публичная оферта - Serdal')

@section('meta')
    <meta name="description"
        content="Публичная оферта на оказание услуг платформы онлайн-обучения Serdal: условия подписки, порядок оплаты и возврата средств, реквизиты.">
    <meta property="og:title" content="Публичная оферта - Serdal">
    <meta property="og:description" content="Публичная оферта на оказание услуг платформы онлайн-обучения Serdal.">
@endsection

@section('content')
    <section class="page-title-section">
        <h1 class="h1">Публичная оферта</h1>
        <p class="p30 page-descriptions">Договор-оферта на оказание услуг доступа к платформе Serdal</p>
    </section>

    <div class="content" style="max-width: 900px; margin: 0 auto; padding: 40px 20px;">
        <div style="line-height: 1.8;">

            <h2 class="p30" style="margin-top: 40px; margin-bottom: 20px; font-weight: 600;">1. Общие положения</h2>
            <p class="p24">
                Настоящий документ является публичной офертой
                {{ $legal['legal_name'] ?? 'владельца платформы Serdal' }}
                (далее — «Исполнитель») и содержит условия оказания услуг доступа к онлайн-платформе Serdal
                (далее — «Платформа»), размещённой по адресу serdal.ru. Оплата подписки означает полное и безоговорочное
                принятие условий настоящей оферты (акцепт) в соответствии со ст. 437, 438 ГК РФ.
            </p>

            <h2 class="p30" style="margin-top: 40px; margin-bottom: 20px; font-weight: 600;">2. Предмет договора</h2>
            <p class="p24">
                Исполнитель предоставляет Заказчику (преподавателю или образовательному центру) доступ к функциональности
                Платформы для организации онлайн-обучения: виртуальные комнаты для занятий, расписание, домашние задания,
                записи занятий, учёт оплат и иные возможности согласно выбранному тарифу. Состав и объём услуг по каждому
                тарифу опубликованы на странице <a href="{{ route('tariffs') }}" style="color: #0066cc;">«Тарифы»</a>.
            </p>

            <h2 class="p30" style="margin-top: 40px; margin-bottom: 20px; font-weight: 600;">3. Тарифы и порядок оплаты</h2>
            <ul class="p24" style="margin-left: 20px; margin-top: 10px;">
                <li style="margin-bottom: 10px;">Стоимость услуг определяется выбранным тарифом:
                    @foreach($tariffs as $tariff)
                        «{{ $tariff->name }}» — {{ number_format($tariff->price, 0, ',', ' ') }} ₽ за
                        {{ $tariff->period_days }} дней{{ !$loop->last ? ';' : '.' }}
                    @endforeach
                </li>
                <li style="margin-bottom: 10px;">Оплата производится банковской картой через интернет-эквайринг
                    Альфа-Банка на защищённой платёжной странице банка.</li>
                <li style="margin-bottom: 10px;">Услуга считается оказанной с момента предоставления доступа к
                    функциональности выбранного тарифа. Доступ активируется автоматически после подтверждения оплаты.</li>
                <li style="margin-bottom: 10px;">Подписка действует в течение оплаченного периода и не продлевается
                    автоматически без согласия Заказчика.</li>
            </ul>

            <h2 class="p30" style="margin-top: 40px; margin-bottom: 20px; font-weight: 600;">4. Возврат денежных средств
            </h2>
            <ul class="p24" style="margin-left: 20px; margin-top: 10px;">
                <li style="margin-bottom: 10px;">Заказчик вправе отказаться от услуги в течение 14 календарных дней с
                    момента оплаты при условии, что услуга не была использована (не проведено ни одного занятия в
                    оплаченном периоде), — в этом случае возвращается полная стоимость подписки.</li>
                <li style="margin-bottom: 10px;">При невозможности оказания услуги по вине Исполнителя средства
                    возвращаются пропорционально неиспользованному периоду.</li>
                <li style="margin-bottom: 10px;">Возврат производится на банковскую карту, с которой была произведена
                    оплата, в течение 10 рабочих дней с момента одобрения заявки на возврат.</li>
                <li style="margin-bottom: 10px;">Заявка на возврат направляется на e-mail
                    <a href="mailto:{{ $legal['legal_email'] ?? 'info@serdal.ru' }}"
                        style="color: #0066cc;">{{ $legal['legal_email'] ?? 'info@serdal.ru' }}</a>.
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
                @if(!empty($legal['legal_name']))
                    {{ $legal['legal_name'] }}<br>
                @endif
                @if(!empty($legal['legal_inn']))
                    ИНН: {{ $legal['legal_inn'] }}<br>
                @endif
                @if(!empty($legal['legal_ogrn']))
                    ОГРН/ОГРНИП: {{ $legal['legal_ogrn'] }}<br>
                @endif
                @if(!empty($legal['legal_address']))
                    Адрес: {{ $legal['legal_address'] }}<br>
                @endif
                E-mail: <a href="mailto:{{ $legal['legal_email'] ?? 'info@serdal.ru' }}"
                    style="color: #0066cc;">{{ $legal['legal_email'] ?? 'info@serdal.ru' }}</a>
                @if(!empty($legal['legal_phone']))
                    <br>Телефон: {{ $legal['legal_phone'] }}
                @endif
            </p>

        </div>
    </div>
@endsection
