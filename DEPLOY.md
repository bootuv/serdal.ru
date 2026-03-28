# Деплой Serdal

## Сервер

- **Путь**: `/var/www/serdal.ru`
- **PHP**: 8.4
- **BBB**: room.serdal.ru

## Деплой

```bash
cd /var/www/serdal.ru

# 1. Получить изменения
git pull origin main

# 2. Зависимости (если изменились)
composer install --no-dev --optimize-autoloader

# 3. Миграции (если есть новые)
php artisan migrate --force

# 4. Очистить и пересоздать кэш
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# 5. Перезапустить сервисы
sudo systemctl restart php8.4-fpm
sudo systemctl restart serdal-queue.service
sudo systemctl restart serdal-reverb.service
sudo systemctl restart serdal-pulse.service
```

## Сервисы systemd

| Сервис | Назначение |
|--------|------------|
| `php8.4-fpm` | PHP-FPM |
| `serdal-queue.service` | Очередь Laravel (jobs, VK upload) |
| `serdal-reverb.service` | WebSocket (чат, уведомления) |
| `serdal-pulse.service` | Мониторинг |

## Проверка статуса

```bash
sudo systemctl status serdal-queue serdal-reverb serdal-pulse php8.4-fpm
sudo journalctl -u serdal-queue -f
tail -f /var/www/serdal.ru/storage/logs/laravel.log
```

## Права доступа (если нужно)

```bash
chmod -R 755 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

## Возможные проблемы

### "Unable to find component"
```bash
php artisan optimize:clear
composer dump-autoload
```

### "Too many requests"
```bash
php artisan cache:clear
```

### Миграции не применяются
```bash
php artisan migrate:status
php artisan migrate --force
```

### Конфликт composer.lock
```bash
git checkout composer.lock
composer install --no-dev --optimize-autoloader
```
