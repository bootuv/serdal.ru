#!/usr/bin/env bash
#
# Деплой serdal.ru. Запускается на сервере (обычно по SSH из GitHub Actions).
# Переменные окружения:
#   APP_DIR  — каталог приложения (по умолчанию /var/www/serdal.ru)
#   BRANCH   — ветка для деплоя (по умолчанию main)
#   PHP_FPM  — имя сервиса php-fpm (по умолчанию php8.4-fpm)
#
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/serdal.ru}"
BRANCH="${BRANCH:-main}"
PHP_FPM="${PHP_FPM:-php8.4-fpm}"

cd "$APP_DIR"

echo "==> Каталог: $APP_DIR, ветка: $BRANCH"

OLD_REF="$(git rev-parse HEAD)"
git fetch --prune origin "$BRANCH"
git reset --hard "origin/$BRANCH"
NEW_REF="$(git rev-parse HEAD)"

echo "==> $OLD_REF -> $NEW_REF"
if [ "$OLD_REF" != "$NEW_REF" ]; then
    git --no-pager log --oneline "$OLD_REF..$NEW_REF" | sed 's/^/    /'
fi

# changed <regexp> — что-то из подходящих файлов изменилось между OLD_REF и NEW_REF.
# Если ref не изменился (ручной запуск / повторный деплой), считаем, что изменилось всё.
changed() {
    [ "$OLD_REF" = "$NEW_REF" ] && return 0
    git diff --name-only "$OLD_REF" "$NEW_REF" | grep -qE "$1"
}

if changed '^composer\.(json|lock)$'; then
    echo "==> composer install"
    composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist
else
    echo "==> composer install пропущен (зависимости не менялись)"
fi

if changed '^package(-lock)?\.json$'; then
    echo "==> npm ci"
    npm ci
fi

if changed '^(package(-lock)?\.json|vite\.config\.js|tailwind\.config\.js|postcss\.config\.js|resources/)'; then
    echo "==> npm run build"
    npm run build
else
    echo "==> сборка фронтенда пропущена (assets не менялись)"
fi

echo "==> Миграции"
php artisan migrate --force

echo "==> Кэши"
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

echo "==> Перезапуск сервисов"
sudo systemctl restart "$PHP_FPM"
sudo systemctl restart serdal-queue.service
sudo systemctl restart serdal-reverb.service
sudo systemctl restart serdal-pulse.service

sleep 3
echo "==> Статус"
systemctl is-active "$PHP_FPM" serdal-queue serdal-reverb serdal-pulse

echo "==> Готово: $(git rev-parse --short HEAD)"
