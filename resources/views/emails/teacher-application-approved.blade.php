<!DOCTYPE html>
<html>

<head>
    <title>Заявка одобрена</title>
</head>

<body>
    <h1>Здравствуйте, {{ $user->first_name }} {{ $user->middle_name }}!</h1>
    <p>Поздравляем! Ваша заявка на роль учителя в Serdal.ru была одобрена администратором.</p>
    <p>Мы создали для вас личный кабинет. Ваши данные для входа:</p>
    <ul>
        <li><strong>Email:</strong> {{ $user->email }}</li>
        <li><strong>Пароль:</strong> {{ $password }}</li>
    </ul>
    <p>Вы можете войти по ссылке: <a href="{{ url('/login') }}">{{ url('/login') }}</a></p>
    <p>Пожалуйста, смените пароль после первого входа в настройках профиля.</p>
    <br>
    <p>С уважением,<br>Команда Serdal.ru</p>
</body>

</html>