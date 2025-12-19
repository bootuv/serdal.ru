# Настройка Google Calendar Integration

## Шаг 1: Создание проекта в Google Cloud Console

1. Перейдите на [Google Cloud Console](https://console.cloud.google.com/)
2. Создайте новый проект или выберите существующий
3. Включите Google Calendar API:
   - Перейдите в "APIs & Services" > "Library"
   - Найдите "Google Calendar API"
   - Нажмите "Enable"

## Шаг 2: Создание OAuth 2.0 учетных данных

1. Перейдите в "APIs & Services" > "Credentials"
2. Нажмите "Create Credentials" > "OAuth client ID"
3. Выберите тип приложения: "Web application"
4. Добавьте Authorized redirect URIs:
   ```
   http://localhost:8000/google/calendar/callback
   https://ваш-домен.ru/google/calendar/callback
   ```
5. Нажмите "Create"
6. Скачайте JSON файл с учетными данными

## Шаг 3: Настройка приложения

1. После создания OAuth credentials, скопируйте **Client ID** и **Client Secret**

2. Добавьте их в файл `.env`:
```env
GOOGLE_CLIENT_ID=ваш-client-id.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=ваш-client-secret
```

3. Убедитесь, что URL приложения правильно настроен:
```env
APP_URL=http://localhost:8000  # для локальной разработки
# или
APP_URL=https://ваш-домен.ru  # для продакшена
```

## Шаг 4: Готово!

Теперь всё готово к использованию. Перезапустите сервер приложения если он был запущен.

## Использование

После настройки пользователи (преподаватели) смогут:

1. **Подключить Google Calendar**: Нажать кнопку "Подключить Google Calendar" на странице занятий
2. **Синхронизировать расписание**: Нажать кнопку "Синхронизировать с Google Calendar" для экспорта всех занятий
3. **Отключить интеграцию**: Нажать кнопку "Отключить Google Calendar"

## Автоматическая синхронизация

Для автоматической синхронизации при создании/обновлении расписания, добавьте в модель `RoomSchedule` observer или event listener.

## Troubleshooting

### Ошибка "Invalid credentials"
- Проверьте путь к JSON файлу в `.env`
- Убедитесь, что Google Calendar API включен в проекте

### Ошибка "Redirect URI mismatch"
- Проверьте, что URL в Authorized redirect URIs совпадает с вашим доменом
- Для локальной разработки используйте `http://localhost:8000/google/calendar/callback`

### Токен истек
- Система автоматически обновляет токен при использовании refresh token
- Если возникают проблемы, переподключите Google Calendar
