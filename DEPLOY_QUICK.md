# Быстрая инструкция по деплою Google Calendar на продакшен

## На сервере выполните:

```bash
cd /var/www/serdal.ru

# 1. Сбросить локальные изменения
git reset --hard HEAD

# 2. Получить обновления
git pull

# 3. Установить зависимости
composer install --no-dev --optimize-autoloader --ignore-platform-reqs

# 4. Запустить миграции
php artisan migrate --force

# 5. Очистить весь кэш
php artisan optimize:clear

# 6. Кэшировать для продакшена
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 7. Перезапустить очереди (если используются)
php artisan queue:restart
```

## Проверка

После выполнения команд:

1. Откройте https://serdal.ru/app/schedule-calendar
2. Должна появиться кнопка "Подключить Google Calendar"
3. Нажмите на неё - должна открыться страница авторизации Google

## Если возникла ошибка "missing the required client identifier"

Убедитесь, что в `.env` на сервере есть:

```env
GOOGLE_CLIENT_ID=533135334645-osrdt...
GOOGLE_CLIENT_SECRET=GOCSPX-...
```

И выполните:

```bash
php artisan config:clear
php artisan config:cache
```

## Готово! 🎉
