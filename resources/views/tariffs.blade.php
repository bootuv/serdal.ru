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
            margin-bottom: 6px;
        }

        .tariff-features {
            list-style: none;
            padding: 0;
            margin: 0 0 24px;
            flex: 1;
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

        .tariff-button {
            display: block;
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

        .tariff-info-block {
            max-width: 900px;
            margin: 0 auto;
            padding: 20px 20px 60px;
            line-height: 1.8;
        }

        .b2b-block {
            margin-top: 48px;
            border: 1px solid #e4e4e4;
            border-radius: 16px;
            padding: 32px 28px;
            background: #f8f8f8;
        }

        @media (max-width: 767px) {
            .tariff-short {
                min-height: auto;
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
                        <div>{{ $tariff->lessons_label }}</div>
                        <div>{{ $tariff->participants_label }}</div>
                        <div>{{ $tariff->duration_label }}</div>
                        <div>{{ $tariff->recording_label }}</div>
                    </div>
                    <ul class="tariff-features">
                        @foreach($tariff->features ?? [] as $feature)
                            <li>{{ $feature }}</li>
                        @endforeach
                    </ul>
                    <a href="{{ auth()->check() ? url('/tutor/subscription') : route('become-tutor') }}" class="tariff-button">
                        {{ $tariff->isFree() ? 'Начать бесплатно' : 'Подключить' }}
                    </a>
                </div>
            @endforeach
        </div>

        <div class="b2b-block">
            <h2 class="tariff-name">Для образовательных центров (B2B)</h2>
            <p class="p24" style="margin: 10px 0 16px;">
                Пакет для онлайн-школ и образовательных центров — от <strong>14 900 ₽/мес</strong>.
            </p>
            <ul class="tariff-features" style="margin-bottom: 16px;">
                <li>5 рабочих мест преподавателей включено (дополнительное место — 1 900 ₽/мес)</li>
                <li>White-label: платформа под брендом вашего центра</li>
                <li>Административная панель для управления преподавателями и учениками</li>
                <li>Приоритетная поддержка и SLA</li>
                <li>Обучение и онбординг команды</li>
            </ul>
            <p class="p24">Для подключения напишите на
                <a href="mailto:info@serdal.ru" style="color: #0066cc;">info@serdal.ru</a> — подготовим договор и настроим
                платформу под ваш центр.
            </p>
        </div>
    </div>

    <div class="tariff-info-block">
        <h2 class="p30" style="margin-bottom: 20px; font-weight: 600;">Что такое подписка Serdal</h2>
        <p class="p24">
            Serdal — платформа для проведения онлайн-занятий: виртуальные комнаты на базе BigBlueButton с интерактивной
            доской, демонстрацией экрана и видеосвязью, расписание занятий, домашние задания, записи уроков, учёт оплат
            учеников и личные страницы преподавателей. Подписка оформляется на 30 дней и даёт доступ ко всем возможностям
            выбранного тарифа в течение оплаченного периода.
        </p>

        <h2 class="p30" style="margin-top: 40px; margin-bottom: 20px; font-weight: 600;">Порядок оплаты</h2>
        <ul class="p24" style="margin-left: 20px; margin-top: 10px;">
            <li style="margin-bottom: 10px;">Оплата производится банковской картой (МИР, Visa, Mastercard) через
                интернет-эквайринг Альфа-Банка в личном кабинете преподавателя.</li>
            <li style="margin-bottom: 10px;">Платёж обрабатывается на защищённой платёжной странице банка; данные карты на
                нашем сервере не сохраняются.</li>
            <li style="margin-bottom: 10px;">Подписка активируется автоматически сразу после подтверждения оплаты.</li>
            <li style="margin-bottom: 10px;">Тариф можно повысить или понизить в любой момент в личном кабинете.</li>
        </ul>

        <h2 class="p30" style="margin-top: 40px; margin-bottom: 20px; font-weight: 600;">Гарантийные условия и возврат</h2>
        <ul class="p24" style="margin-left: 20px; margin-top: 10px;">
            <li style="margin-bottom: 10px;">Если сервис не был использован (не проведено ни одного занятия в оплаченном
                периоде), вы можете отказаться от подписки в течение 14 дней с момента оплаты и получить полный возврат
                средств.</li>
            <li style="margin-bottom: 10px;">При технической невозможности оказания услуги по нашей вине производится
                возврат средств пропорционально неиспользованному периоду подписки.</li>
            <li style="margin-bottom: 10px;">Возврат осуществляется на ту же банковскую карту, с которой была произведена
                оплата, в срок до 10 рабочих дней.</li>
            <li style="margin-bottom: 10px;">Для оформления возврата напишите на
                <a href="mailto:info@serdal.ru" style="color: #0066cc;">info@serdal.ru</a> с указанием e-mail учётной записи
                и даты платежа.</li>
        </ul>
        <p class="p24" style="margin-top: 16px;">
            Полные условия — в <a href="{{ route('offer') }}" style="color: #0066cc;">публичной оферте</a>.
        </p>
    </div>
@endsection
