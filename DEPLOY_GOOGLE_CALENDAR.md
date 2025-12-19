# Инструкция по деплою Google Calendar Integration на продакшен

## Шаг 1: Загрузить код на сервер

Убедитесь, что все файлы загружены на сервер (через git pull или другим способом).

## Шаг 2: Установить зависимости

Выполните на сервере:

```bash
cd /path/to/your/project
composer install --no-dev --optimize-autoloader
```

Или если composer уже установлен:

```bash
composer require spatie/laravel-google-calendar
```

## Шаг 3: Опубликовать конфигурацию

```bash
php artisan vendor:publish --provider="Spatie\GoogleCalendar\GoogleCalendarServiceProvider"
```

## Шаг 4: Запустить миграции

```bash
php artisan migrate --force
```

## Шаг 5: Настроить переменные окружения

Добавьте в файл `.env` на сервере:

```env
GOOGLE_CLIENT_ID=ваш-client-id.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=ваш-client-secret
```

## Шаг 6: Очистить кэш

```bash
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
composer dump-autoload
```

## Шаг 7: Оптимизация для продакшена (опционально)

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## Шаг 8: Перезапустить очереди (если используются)

```bash
php artisan queue:restart
```

## Проверка

После выполнения всех шагов:

1. Откройте страницу расписания
2. Должна появиться кнопка "Подключить Google Calendar"
3. При нажатии не должно быть ошибки "Class Google\Client not found"

## Troubleshooting

### Ошибка "Class Google\Client not found"

Выполните:
```bash
composer dump-autoload -o
php artisan optimize:clear
```

### Ошибка при миграции

Проверьте, что база данных доступна и пользователь имеет права на создание таблиц.

### Ошибка "Invalid credentials"

Убедитесь, что:
- GOOGLE_CLIENT_ID и GOOGLE_CLIENT_SECRET правильно указаны в .env
- В Google Cloud Console добавлен правильный redirect URI для вашего домена

## Быстрая команда (всё в одном)

```bash
composer install --no-dev --optimize-autoloader && \
php artisan migrate --force && \
php artisan config:clear && \
php artisan route:clear && \
php artisan view:clear && \
php artisan cache:clear && \
composer dump-autoload -o && \
php artisan config:cache && \
php artisan route:cache && \
php artisan view:cache
```

## Откат (если что-то пошло не так)

```bash
php artisan migrate:rollback --step=1
composer remove spatie/laravel-google-calendar
composer dump-autoload
php artisan optimize:clear
```
