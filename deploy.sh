#!/usr/bin/env bash
#
# Деплой serdal.ru. Запускается на сервере (обычно по SSH из GitHub Actions).
#
# Переменные окружения:
#   APP_DIR     — каталог приложения (по умолчанию /var/www/serdal.ru)
#   BRANCH      — ветка для деплоя (по умолчанию main)
#   PHP_FPM     — имя сервиса php-fpm (по умолчанию php8.4-fpm)
#   PHP_BIN     — CLI-интерпретатор (по умолчанию /usr/bin/php8.4). Должен совпадать по версии
#                 с PHP_FPM и с ExecStart в deploy/systemd: системный /usr/bin/php на сервере —
#                 другая версия, и очередь с планировщиком работали не на том PHP, что сайт.
#   ASSETS_SHA  — если задан, ассеты берутся из $APP_DIR/builds/$ASSETS_SHA (их туда
#                 заливает CI). Если пусто — фронтенд собирается локально, на сервере.
#   BUILDS_KEEP — сколько старых сборок ассетов держать (по умолчанию 3)
#
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/serdal.ru}"
BRANCH="${BRANCH:-main}"
PHP_FPM="${PHP_FPM:-php8.4-fpm}"
PHP_BIN="${PHP_BIN:-/usr/bin/php8.4}"
ASSETS_SHA="${ASSETS_SHA:-}"
BUILDS_KEEP="${BUILDS_KEEP:-3}"

cd "$APP_DIR"

echo "==> Каталог: $APP_DIR, ветка: $BRANCH"

# Проверяем до любых изменений: если у $PHP_BIN нет расширений, которых требуют
# зависимости (bcmath, zip, ...), падаем сейчас, а не после переключения кода.
echo "==> Проверка расширений $("$PHP_BIN" -r 'echo PHP_VERSION;')"
COMPOSER_BIN="$(command -v composer)"
# (системный composer старый и под PHP 8.4 сыплет Deprecated — отфильтровываем)
"$PHP_BIN" "$COMPOSER_BIN" check-platform-reqs --no-dev --no-interaction >/dev/null 2>&1 || {
    "$PHP_BIN" "$COMPOSER_BIN" check-platform-reqs --no-dev --no-interaction 2>&1 | grep -E ' (missing|failed)' || true
    echo "!! У $PHP_BIN не хватает расширений — установите пакеты php8.4-<ext> и повторите деплой"
    exit 1
}

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
    "$PHP_BIN" "$COMPOSER_BIN" install --no-dev --optimize-autoloader --no-interaction --prefer-dist
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
"$PHP_BIN" artisan migrate --force

echo "==> Кэши"
"$PHP_BIN" artisan optimize:clear
"$PHP_BIN" artisan config:cache
"$PHP_BIN" artisan route:cache
"$PHP_BIN" artisan view:cache
"$PHP_BIN" artisan event:cache

echo "==> systemd-юниты"
bash deploy/install-systemd.sh

echo "==> Перезапуск сервисов"
# Воркер выгрузки записей (serdal-queue-recordings) перезапускаем мягко: queue:restart даёт
# дождаться конца текущей задачи, а systemctl restart оборвал бы многоминутную выгрузку в S3.
"$PHP_BIN" artisan queue:restart
sudo systemctl restart "$PHP_FPM"
sudo systemctl restart serdal-queue.service
sudo systemctl restart serdal-reverb.service
sudo systemctl restart serdal-pulse.service

sleep 3
echo "==> Статус"
systemctl is-active "$PHP_FPM" serdal-queue serdal-queue-recordings serdal-scheduler.timer serdal-reverb serdal-pulse

echo "==> Готово: $(git rev-parse --short HEAD)"
