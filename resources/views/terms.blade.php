@extends('layout')

@section('title', 'Условия использования - Serdal')

@section('meta')
    <meta name="description"
        content="Условия использования платформы онлайн репетиторов Serdal. Правила и условия для преподавателей, менторов и учеников.">
    <meta property="og:title" content="Условия использования - Serdal">
    <meta property="og:description" content="Условия использования платформы онлайн репетиторов Serdal">
@endsection

@section('content')
    <section class="header underline">
        <a href="/" class="logo-wrapper w-inline-block"><img src="/images/Logo.svg" loading="lazy" width="Auto" height="32"
                alt="" class="logo"></a>
        <div class="menu-wrapper">
            <div class="main-menu">
                <a href="#" target="_blank" class="p24">О нас</a>
                <a href="{{ route('reviews') }}" class="p24">Отзывы</a>
                <a href="https://room.serdal.ru/signin" target="_blank" class="p24">Войти</a>
            </div>
            <div data-w-id="a8ac7203-c22a-a2cb-1d14-2d129698914f" class="burger-menu-wrapper"><img src="/images/burger.svg"
                    loading="lazy" width="32" height="32" alt="" class="burger-menu"></div>
        </div>
    </section>

    <section class="page-title-section">
        <h1 class="h1">Условия использования</h1>
        <p class="p30 page-descriptions">Последнее обновление: {{ date('d.m.Y') }}</p>
    </section>

    <div class="content" style="max-width: 900px; margin: 0 auto; padding: 40px 20px;">
        <div style="line-height: 1.8;">

            <h2 class="p30" style="margin-top: 40px; margin-bottom: 20px; font-weight: 600;">1. Общие положения</h2>
            <p class="p24">
                Настоящие Условия использования (далее — «Условия») регулируют использование платформы онлайн репетиторов
                Serdal (далее — «Платформа»). Регистрируясь и используя Платформу, вы соглашаетесь с данными Условиями.
            </p>

            <h2 class="p30" style="margin-top: 40px; margin-bottom: 20px; font-weight: 600;">2. Описание сервиса</h2>
            <p class="p24">
                Serdal — это платформа для организации онлайн-обучения, которая предоставляет:
            </p>
            <ul class="p24" style="margin-left: 20px; margin-top: 10px;">
                <li style="margin-bottom: 10px;">Инструменты для проведения онлайн-занятий через BigBlueButton</li>
                <li style="margin-bottom: 10px;">Систему управления расписанием и занятиями</li>
                <li style="margin-bottom: 10px;">Интеграцию с Google Calendar для синхронизации расписания</li>
                <li style="margin-bottom: 10px;">Профили преподавателей, менторов и учеников</li>
                <li style="margin-bottom: 10px;">Систему оценивания и отслеживания прогресса</li>
            </ul>

            <h2 class="p30" style="margin-top: 40px; margin-bottom: 20px; font-weight: 600;">3. Регистрация и учётная запись
            </h2>
            <p class="p24">
                Для использования Платформы необходимо создать учётную запись. При регистрации вы обязуетесь:
            </p>
            <ul class="p24" style="margin-left: 20px; margin-top: 10px;">
                <li style="margin-bottom: 10px;">Предоставлять достоверную и актуальную информацию</li>
                <li style="margin-bottom: 10px;">Поддерживать безопасность вашего пароля</li>
                <li style="margin-bottom: 10px;">Немедленно уведомлять нас о любом несанкционированном доступе</li>
                <li style="margin-bottom: 10px;">Не передавать доступ к вашей учётной записи третьим лицам</li>
            </ul>

            <h2 class="p30" style="margin-top: 40px; margin-bottom: 20px; font-weight: 600;">4. Права и обязанности
                пользователей</h2>

            <h3 class="p24" style="margin-top: 30px; margin-bottom: 15px; font-weight: 600;">4.1. Преподаватели и менторы
            </h3>
            <p class="p24">Преподаватели и менторы обязуются:</p>
            <ul class="p24" style="margin-left: 20px; margin-top: 10px;">
                <li style="margin-bottom: 10px;">Проводить занятия качественно и в соответствии с расписанием</li>
                <li style="margin-bottom: 10px;">Уважительно относиться к ученикам</li>
                <li style="margin-bottom: 10px;">Своевременно обновлять информацию о расписании</li>
                <li style="margin-bottom: 10px;">Соблюдать профессиональную этику</li>
            </ul>

            <h3 class="p24" style="margin-top: 30px; margin-bottom: 15px; font-weight: 600;">4.2. Ученики</h3>
            <p class="p24">Ученики обязуются:</p>
            <ul class="p24" style="margin-left: 20px; margin-top: 10px;">
                <li style="margin-bottom: 10px;">Посещать занятия согласно расписанию</li>
                <li style="margin-bottom: 10px;">Уважительно относиться к преподавателям и другим ученикам</li>
                <li style="margin-bottom: 10px;">Выполнять домашние задания</li>
                <li style="margin-bottom: 10px;">Соблюдать правила поведения во время занятий</li>
            </ul>

            <h2 class="p30" style="margin-top: 40px; margin-bottom: 20px; font-weight: 600;">5. Запрещённые действия</h2>
            <p class="p24">При использовании Платформы запрещается:</p>
            <ul class="p24" style="margin-left: 20px; margin-top: 10px;">
                <li style="margin-bottom: 10px;">Нарушать законодательство Российской Федерации</li>
                <li style="margin-bottom: 10px;">Публиковать оскорбительный, дискриминационный или незаконный контент</li>
                <li style="margin-bottom: 10px;">Распространять вредоносное программное обеспечение</li>
                <li style="margin-bottom: 10px;">Использовать Платформу в коммерческих целях без нашего согласия</li>
                <li style="margin-bottom: 10px;">Пытаться получить несанкционированный доступ к системе</li>
                <li style="margin-bottom: 10px;">Копировать или воспроизводить материалы Платформы без разрешения</li>
            </ul>

            <h2 class="p30" style="margin-top: 40px; margin-bottom: 20px; font-weight: 600;">6. Интеллектуальная
                собственность</h2>
            <p class="p24">
                Все материалы, размещённые на Платформе (тексты, изображения, логотипы, программное обеспечение), являются
                интеллектуальной собственностью Serdal или её партнёров и защищены законодательством об авторском праве.
            </p>

            <h2 class="p30" style="margin-top: 40px; margin-bottom: 20px; font-weight: 600;">7. Конфиденциальность</h2>
            <p class="p24">
                Обработка персональных данных осуществляется в соответствии с нашей <a href="{{ route('privacy') }}"
                    style="color: #0066cc;">Политикой конфиденциальности</a>.
            </p>

            <h2 class="p30" style="margin-top: 40px; margin-bottom: 20px; font-weight: 600;">8. Ограничение ответственности
            </h2>
            <p class="p24">
                Платформа предоставляется «как есть». Мы не несём ответственности за:
            </p>
            <ul class="p24" style="margin-left: 20px; margin-top: 10px;">
                <li style="margin-bottom: 10px;">Качество проводимых занятий</li>
                <li style="margin-bottom: 10px;">Технические сбои, вызванные действиями третьих лиц</li>
                <li style="margin-bottom: 10px;">Потерю данных в результате форс-мажорных обстоятельств</li>
                <li style="margin-bottom: 10px;">Действия пользователей Платформы</li>
            </ul>

            <h2 class="p30" style="margin-top: 40px; margin-bottom: 20px; font-weight: 600;">9. Прекращение доступа</h2>
            <p class="p24">
                Мы оставляем за собой право приостановить или прекратить ваш доступ к Платформе в случае нарушения данных
                Условий или по другим обоснованным причинам.
            </p>

            <h2 class="p30" style="margin-top: 40px; margin-bottom: 20px; font-weight: 600;">10. Изменения в Условиях</h2>
            <p class="p24">
                Мы можем изменять данные Условия. О существенных изменениях мы уведомим вас заранее. Продолжение
                использования Платформы после внесения изменений означает ваше согласие с новыми Условиями.
            </p>

            <h2 class="p30" style="margin-top: 40px; margin-bottom: 20px; font-weight: 600;">11. Применимое право</h2>
            <p class="p24">
                Данные Условия регулируются законодательством Российской Федерации. Все споры разрешаются в соответствии с
                действующим законодательством РФ.
            </p>

            <h2 class="p30" style="margin-top: 40px; margin-bottom: 20px; font-weight: 600;">12. Контакты</h2>
            <p class="p24">
                По вопросам, связанным с использованием Платформы, вы можете обратиться к нам:
            </p>
            <p class="p24" style="margin-top: 10px;">
                Email: <a href="mailto:info@serdal.ru" style="color: #0066cc;">info@serdal.ru</a>
            </p>

        </div>
    </div>
@endsection