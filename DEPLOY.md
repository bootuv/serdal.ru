# Чеклист для деплоя на продакшн

## 1. Загрузка файлов
- Загрузить все файлы проекта на сервер
- Убедиться, что загружены новые файлы:
  - `app/Filament/Pages/Auth/Login.php`
  - `app/Http/Middleware/CheckUserActive.php`
  - Миграции в `database/migrations/`

## 2. Установка зависимостей

**ВАЖНО:** Если на сервере уже установлены зависимости, выполните:
```bash
composer install --no-dev --optimize-autoloader
```

**Если возникают конфликты версий:**
```bash
# Удалить composer.lock и vendor
rm -rf vendor composer.lock

# Установить заново
composer install --no-dev --optimize-autoloader --ignore-platform-reqs
```

**Альтернатива (если есть проблемы с PHP версией):**
```bash
composer update --no-dev --optimize-autoloader --with-all-dependencies
```

## 3. Выполнение миграций
```bash
php artisan migrate --force
```

Это добавит поля:
- `is_active` (для публичности профиля)
- `is_blocked` (для блокировки авторизации)

## 4. Очистка кеша
```bash
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## 5. Установка прав доступа
```bash
chmod -R 755 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

## 6. Проверка .env
Убедитесь, что в `.env` правильно настроены:
- `APP_URL=https://serdal.ru`
- `APP_ENV=production`
- `APP_DEBUG=false`

## 7. Перезапуск сервисов
```bash
# Если используется PHP-FPM
sudo systemctl restart php8.2-fpm

# Если используется очередь
php artisan queue:restart
```

## 8. Проверка работоспособности
- Откройте `/login` - должна открыться форма авторизации
- Попробуйте войти
- Проверьте редирект по ролям

## Возможные проблемы

### "Unable to find component"
**Решение:**
```bash
php artisan optimize:clear
composer dump-autoload
```

### "Too many requests"
**Решение:**
```bash
php artisan cache:clear
```
Подождите 1 минуту и попробуйте снова.

### Миграции не применяются
**Решение:**
```bash
php artisan migrate:status
php artisan migrate --force
```
