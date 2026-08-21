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

## Автодеплой через GitHub Actions

Пуш в `main` запускает [.github/workflows/deploy.yml](.github/workflows/deploy.yml):
раннер подключается к серверу по SSH и выполняет [deploy.sh](deploy.sh) (скрипт
передаётся из репозитория через stdin, поэтому на сервере всегда исполняется
актуальная версия). Ручной запуск — вкладка **Actions → Deploy to production → Run workflow**.

Что делает `deploy.sh`:

1. `git fetch` + `git reset --hard origin/main` (локальные правки на сервере затираются);
2. `composer install` — только если менялись `composer.json/lock`;
3. `npm ci` — только если менялся `package-lock.json`; `npm run build` — если менялись
   `resources/`, `vite/tailwind/postcss` конфиги или зависимости;
4. `php artisan migrate --force` (всегда, безопасно);
5. сброс и пересборка кэшей: config, route, view, event;
6. рестарт `php8.4-fpm`, `serdal-queue`, `serdal-reverb`, `serdal-pulse` и проверка статуса;
7. в конце workflow дергает `https://serdal.ru/` и падает, если ответ не 200.

### Настройка на сервере (один раз)

```bash
# 1. Пользователь деплоя (например, deploy) с доступом к каталогу приложения
sudo usermod -aG www-data deploy
sudo chown -R deploy:www-data /var/www/serdal.ru
sudo chmod -R g+w /var/www/serdal.ru/storage /var/www/serdal.ru/bootstrap/cache

# 2. Ключ для входа из GitHub Actions (генерируем локально, БЕЗ пароля)
ssh-keygen -t ed25519 -C "github-actions-deploy" -f ~/.ssh/serdal_deploy
# публичную часть — на сервер:
ssh-copy-id -i ~/.ssh/serdal_deploy.pub deploy@serdal.ru

# 3. Разрешить рестарт сервисов без пароля
sudo tee /etc/sudoers.d/deploy-serdal >/dev/null <<'EOF'
deploy ALL=(root) NOPASSWD: /bin/systemctl restart php8.4-fpm, \
  /bin/systemctl restart serdal-queue.service, \
  /bin/systemctl restart serdal-reverb.service, \
  /bin/systemctl restart serdal-pulse.service
EOF
sudo visudo -c
```

Проверить, что у пользователя `deploy` работает `git fetch` (нужен доступ к репозиторию —
deploy key GitHub или ssh-agent forwarding), а также доступны `composer`, `php`, `npm`.

### Secrets и variables в GitHub

**Settings → Secrets and variables → Actions → Secrets:**

| Secret | Значение |
|--------|----------|
| `SSH_PRIVATE_KEY` | содержимое `~/.ssh/serdal_deploy` целиком, включая `-----BEGIN…END-----` |
| `SSH_HOST` | `serdal.ru` (или IP) |
| `SSH_USER` | `deploy` |
| `SSH_PORT` | порт SSH, если не 22 (необязательно) |
| `SSH_KNOWN_HOSTS` | вывод `ssh-keyscan -p 22 serdal.ru` |

**Variables** (необязательно, есть значения по умолчанию): `APP_DIR` = `/var/www/serdal.ru`,
`PHP_FPM` = `php8.4-fpm`.

### Ручной запуск того же скрипта

```bash
cd /var/www/serdal.ru && ./deploy.sh
```
