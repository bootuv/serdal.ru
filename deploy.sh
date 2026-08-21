#!/usr/bin/env bash
#
# Деплой serdal.ru. Запускается на сервере (обычно по SSH из GitHub Actions).
#
# Переменные окружения:
#   APP_DIR     — каталог приложения (по умолчанию /var/www/serdal.ru)
#   BRANCH      — ветка для деплоя (по умолчанию main)
#   PHP_FPM     — имя сервиса php-fpm (по умолчанию php8.4-fpm)
#   ASSETS_SHA  — если задан, ассеты берутся из $APP_DIR/builds/$ASSETS_SHA (их туда
#                 заливает CI). Если пусто — фронтенд собирается локально, на сервере.
#   BUILDS_KEEP — сколько старых сборок ассетов держать (по умолчанию 3)
#
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/serdal.ru}"
BRANCH="${BRANCH:-main}"
PHP_FPM="${PHP_FPM:-php8.4-fpm}"
ASSETS_SHA="${ASSETS_SHA:-}"
BUILDS_KEEP="${BUILDS_KEEP:-3}"

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

# --- Фронтенд ---------------------------------------------------------------
# public/build — симлинк на builds/<sha>. Переключаем его через rename(2): атомарно,
# без окна, в котором manifest.json отсутствует и страницы отдают 500.
activate_assets() {
    local sha="$1"
    local target="$APP_DIR/builds/$sha"

    [ -f "$target/manifest.json" ] || {
        echo "!! В $target нет manifest.json — ассеты не залиты"
        exit 1
    }

    # Наследие сборки на сервере: public/build мог быть обычным каталогом.
    if [ -e public/build ] && [ ! -L public/build ]; then
        rm -rf public/build
    fi

    local tmp="public/.build.$$"
    ln -sfn "$target" "$tmp"
    mv -T "$tmp" public/build
    echo "==> Ассеты переключены на builds/$sha"
}

prune_builds() {
    [ -d "$APP_DIR/builds" ] || return 0
    # shellcheck disable=SC2012
    { ls -1dt "$APP_DIR"/builds/*/ 2>/dev/null || true; } \
        | tail -n +"$((BUILDS_KEEP + 1))" \
        | xargs -r rm -rf
}

if [ -n "$ASSETS_SHA" ]; then
    activate_assets "$ASSETS_SHA"
    prune_builds
elif changed '^(package(-lock)?\.json|vite\.config\.js|tailwind\.config\.js|postcss\.config\.js|resources/)'; then
    echo "==> Сборка фронтенда на сервере (ASSETS_SHA не задан)"
    npm ci
    npm run build
else
    echo "==> Фронтенд не трогаем (assets не менялись)"
fi

# --- Laravel ----------------------------------------------------------------
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
