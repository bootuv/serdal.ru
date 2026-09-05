<!DOCTYPE html>
<html lang="ru" class="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Сердал — Вход в систему</title>
    @include('partials.favicon')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @filamentStyles
    <style>
        body {
            background-color: rgb(var(--gray-50));
            font-family: var(--font-family, ui-sans-serif, system-ui, sans-serif);
        }

        .dark body {
            background: linear-gradient(135deg, rgb(30, 41, 59) 0%, rgb(15, 23, 42) 100%);
        }

        .welcome-container {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }

        .welcome-logo {
            margin-bottom: 2rem;
        }

        .welcome-logo img {
            height: 48px;
            filter: brightness(0) invert(1);
        }

        .welcome-title {
            font-size: 1.875rem;
            font-weight: 700;
            color: white;
            margin-bottom: 0.5rem;
            text-align: center;
        }

        .welcome-subtitle {
            font-size: 1rem;
            color: rgb(148, 163, 184);
            margin-bottom: 3rem;
            text-align: center;
        }

        .welcome-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 1.5rem;
            max-width: 720px;
            width: 100%;
        }

        .welcome-card {
            background: white;
            border-radius: 1rem;
            padding: 2rem;
            text-decoration: none;
            color: inherit;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1), 0 1px 2px rgba(0, 0, 0, 0.06);
            transition: all 0.15s ease;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            border: 2px solid transparent;
        }

        .dark .welcome-card {
            background: rgb(30, 41, 59);
            border-color: rgb(51, 65, 85);
        }

        .welcome-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }

        .dark .welcome-card:hover {
            border-color: rgb(251, 191, 36);
        }

        .welcome-card-new .welcome-badge {
            background: linear-gradient(135deg, rgb(251, 191, 36), rgb(245, 158, 11));
            color: rgb(30, 41, 59);
            font-size: 0.75rem;
            font-weight: 600;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            margin-bottom: 1rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .welcome-card-icon {
            width: 4rem;
            height: 4rem;
            border-radius: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.25rem;
            font-size: 1.75rem;
        }

        .welcome-card-new .welcome-card-icon {
            background: rgb(51, 65, 85);
        }

        .welcome-card-old .welcome-card-icon {
            background: rgb(51, 65, 85);
        }

        .welcome-card-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .dark .welcome-card-title {
            color: white;
        }

        .welcome-card-description {
            font-size: 0.875rem;
            color: rgb(100, 116, 139);
            line-height: 1.5;
            margin-bottom: 1.5rem;
        }

        .dark .welcome-card-description {
            color: rgb(148, 163, 184);
        }

        .welcome-card-button {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            font-weight: 600;
            transition: all 0.15s ease;
        }

        .welcome-card-new .welcome-card-button {
            background: linear-gradient(135deg, rgb(251, 191, 36), rgb(245, 158, 11));
            color: rgb(30, 41, 59);
        }

        .welcome-card-new:hover .welcome-card-button {
            background: linear-gradient(135deg, rgb(245, 158, 11), rgb(217, 119, 6));
        }

        .welcome-card-old .welcome-card-button {
            background: rgb(51, 65, 85);
            color: white;
        }

        .welcome-card-old:hover .welcome-card-button {
            background: rgb(71, 85, 105);
        }

        .welcome-footer {
            margin-top: 3rem;
            color: rgb(148, 163, 184);
            font-size: 0.875rem;
            text-align: center;
        }

        .welcome-footer a {
            color: rgb(251, 191, 36);
            text-decoration: none;
        }

        .welcome-footer a:hover {
            text-decoration: underline;
        }

        @media (max-width: 640px) {
            .welcome-cards {
                grid-template-columns: 1fr;
            }

            .welcome-title {
                font-size: 1.5rem;
            }
        }
    </style>
</head>

<body>
    <div class="welcome-container">
        <div class="welcome-logo">
            <img src="/images/Logo.svg" alt="Сердал">
        </div>

        <h1 class="welcome-title">Добро пожаловать!</h1>
        <p class="welcome-subtitle">Выберите панель для входа</p>

        <div class="welcome-cards">
            <a href="/login" class="welcome-card welcome-card-new">
                <span class="welcome-badge">Рекомендуем</span>
                <div class="welcome-card-icon">
                    ✨
                </div>
                <h2 class="welcome-card-title">Новая платформа</h2>
                <p class="welcome-card-description">
                    Расписание занятий, управление учениками, неограниченные записи уроков, успеваемость учеников и
                    многое другое
                </p>
                <span class="welcome-card-button">
                    Войти
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                        <polyline points="12 5 19 12 12 19"></polyline>
                    </svg>
                </span>
            </a>

            <a href="https://room.serdal.ru/signin" class="welcome-card welcome-card-old">
                <div class="welcome-card-icon">
                    📚
                </div>
                <h2 class="welcome-card-title">Старая панель</h2>
                <p class="welcome-card-description">
                    Привычный интерфейс управления комнатами и записями уроков
                </p>
                <span class="welcome-card-button">
                    Войти
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                        <polyline points="12 5 19 12 12 19"></polyline>
                    </svg>
                </span>
            </a>
        </div>

        <div class="welcome-footer">
            <p>Есть вопросы? Напишите нам: <a href="mailto:info@serdal.ru">info@serdal.ru</a></p>
        </div>
    </div>
</body>

</html>