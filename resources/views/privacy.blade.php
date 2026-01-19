@extends('layout')

@section('title', 'Политика конфиденциальности - Serdal')

@section('meta')
    <meta name="description"
        content="Политика конфиденциальности платформы онлайн репетиторов Serdal. Узнайте, как мы собираем, используем и защищаем вашу персональную информацию.">
    <meta property="og:title" content="Политика конфиденциальности - Serdal">
    <meta property="og:description" content="Политика конфиденциальности платформы онлайн репетиторов Serdal">
@endsection

@section('content')


    <section class="page-title-section">
        <h1 class="h1">Политика конфиденциальности</h1>
        <p class="p30 page-descriptions">Последнее обновление: {{ date('d.m.Y') }}</p>
    </section>

    <div class="content" style="max-width: 900px; margin: 0 auto; padding: 40px 20px;">
        <div style="line-height: 1.8;">

            <h2 class="p30" style="margin-top: 40px; margin-bottom: 20px; font-weight: 600;">1. Общие положения</h2>
            <p class="p24">
                Настоящая Политика конфиденциальности определяет порядок обработки и защиты персональных данных
                пользователей платформы онлайн репетиторов Serdal (далее — «Платформа»). Используя Платформу, вы
                соглашаетесь с условиями данной Политики.
            </p>

            <h2 class="p30" style="margin-top: 40px; margin-bottom: 20px; font-weight: 600;">2. Какие данные мы собираем
            </h2>
            <p class="p24">При использовании Платформы мы можем собирать следующую информацию:</p>
            <ul class="p24" style="margin-left: 20px; margin-top: 10px;">
                <li style="margin-bottom: 10px;"><strong>Регистрационные данные:</strong> имя, фамилия, адрес электронной
                    почты, номер телефона</li>
                <li style="margin-bottom: 10px;"><strong>Профильная информация:</strong> фотография, информация о предметах
                    преподавания, классах, опыте работы</li>
                <li style="margin-bottom: 10px;"><strong>Данные об использовании:</strong> информация о занятиях,
                    расписании, посещаемости</li>
                <li style="margin-bottom: 10px;"><strong>Технические данные:</strong> IP-адрес, тип браузера, операционная
                    система</li>
                <li style="margin-bottom: 10px;"><strong>Данные Google Calendar:</strong> токены доступа для синхронизации
                    расписания (при подключении интеграции)</li>
            </ul>

            <h2 class="p30" style="margin-top: 40px; margin-bottom: 20px; font-weight: 600;">3. Как мы используем ваши
                данные</h2>
            <p class="p24">Мы используем собранную информацию для:</p>
            <ul class="p24" style="margin-left: 20px; margin-top: 10px;">
                <li style="margin-bottom: 10px;">Предоставления доступа к Платформе и её функциям</li>
                <li style="margin-bottom: 10px;">Организации и проведения онлайн-занятий через BigBlueButton</li>
                <li style="margin-bottom: 10px;">Синхронизации расписания с Google Calendar (при вашем согласии)</li>
                <li style="margin-bottom: 10px;">Отправки уведомлений о занятиях и важных событиях</li>
                <li style="margin-bottom: 10px;">Улучшения качества наших услуг</li>
                <li style="margin-bottom: 10px;">Обеспечения безопасности Платформы</li>
            </ul>

            <h2 class="p30" style="margin-top: 40px; margin-bottom: 20px; font-weight: 600;">4. Интеграция с Google Calendar
            </h2>
            <p class="p24">
                При подключении интеграции с Google Calendar мы запрашиваем доступ к вашему календарю для синхронизации
                расписания занятий. Мы храним только токены доступа, необходимые для работы интеграции. Вы можете в любой
                момент отключить интеграцию в настройках профиля.
            </p>

            <h2 class="p30" style="margin-top: 40px; margin-bottom: 20px; font-weight: 600;">5. Передача данных третьим
                лицам</h2>
            <p class="p24">
                Мы не продаём и не передаём ваши персональные данные третьим лицам, за исключением случаев, предусмотренных
                законодательством или необходимых для работы Платформы:
            </p>
            <ul class="p24" style="margin-left: 20px; margin-top: 10px;">
                <li style="margin-bottom: 10px;"><strong>BigBlueButton:</strong> для проведения видеоконференций</li>
                <li style="margin-bottom: 10px;"><strong>Google:</strong> для синхронизации с Google Calendar (при вашем
                    согласии)</li>
                <li style="margin-bottom: 10px;"><strong>Почтовые сервисы:</strong> для отправки уведомлений</li>
            </ul>

            <h2 class="p30" style="margin-top: 40px; margin-bottom: 20px; font-weight: 600;">6. Защита данных</h2>
            <p class="p24">
                Мы применяем современные технические и организационные меры для защиты ваших данных от несанкционированного
                доступа, изменения, раскрытия или уничтожения. Все данные передаются по защищённому протоколу HTTPS.
            </p>

            <h2 class="p30" style="margin-top: 40px; margin-bottom: 20px; font-weight: 600;">7. Ваши права</h2>
            <p class="p24">Вы имеете право:</p>
            <ul class="p24" style="margin-left: 20px; margin-top: 10px;">
                <li style="margin-bottom: 10px;">Получать информацию о ваших персональных данных</li>
                <li style="margin-bottom: 10px;">Исправлять неточные данные</li>
                <li style="margin-bottom: 10px;">Удалять ваши данные (право на забвение)</li>
                <li style="margin-bottom: 10px;">Ограничивать обработку данных</li>
                <li style="margin-bottom: 10px;">Отозвать согласие на обработку данных</li>
            </ul>

            <h2 class="p30" style="margin-top: 40px; margin-bottom: 20px; font-weight: 600;">8. Cookies</h2>
            <p class="p24">
                Мы используем cookies для обеспечения работы Платформы, аналитики и улучшения пользовательского опыта. Вы
                можете настроить использование cookies в настройках вашего браузера.
            </p>

            <h2 class="p30" style="margin-top: 40px; margin-bottom: 20px; font-weight: 600;">9. Изменения в Политике</h2>
            <p class="p24">
                Мы можем обновлять данную Политику конфиденциальности. О существенных изменениях мы уведомим вас по
                электронной почте или через уведомления на Платформе.
            </p>

            <h2 class="p30" style="margin-top: 40px; margin-bottom: 20px; font-weight: 600;">10. Контакты</h2>
            <p class="p24">
                По вопросам, связанным с обработкой персональных данных, вы можете обратиться к нам:
            </p>
            <p class="p24" style="margin-top: 10px;">
                Email: <a href="mailto:info@serdal.ru" style="color: #0066cc;">info@serdal.ru</a>
            </p>

        </div>
    </div>
@endsection