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

Пуш в `main` запускает [.github/workflows/deploy.yml](.github/workflows/deploy.yml).
Ручной запуск — вкладка **Actions → Deploy to production → Run workflow**.

Что происходит:

1. **Раннер** ставит PHP-зависимости (`composer install --no-dev --no-scripts`) и собирает
   фронтенд (`npm ci && npm run build`) — на проде node не нужен. Composer на раннере нужен
   потому, что `tailwind.config.js` тянет пресет из `vendor/filament` и сканирует его
   blade-шаблоны; собранный CSS проверяется на минимальный размер, чтобы поймать ситуацию,
   когда стили Filament молча вычистились;
2. готовый `public/build` заливается через `rsync` в `/var/www/serdal.ru/builds/<sha>/`;
3. раннер по SSH запускает [deploy.sh](deploy.sh), передавая его через stdin — значит на
   сервере всегда исполняется версия скрипта из деплоящегося коммита;
4. `deploy.sh` делает `git fetch` + `git reset --hard origin/main` (локальные правки на
   сервере затираются, неотслеживаемые файлы вроде `.env` не трогаются);
5. `composer install` — только если менялись `composer.json/lock`;
6. `public/build` переключается симлинком на `builds/<sha>` через `rename(2)` — атомарно,
   без окна, когда `manifest.json` отсутствует и страницы отдают 500. Старые сборки чистятся,
   последние 3 остаются (можно откатить ассеты одним `ln -sfn`);
7. `php artisan migrate --force` (всегда, безопасно);
8. сброс и пересборка кэшей: config, route, view, event;
9. рестарт `php8.4-fpm`, `serdal-queue`, `serdal-reverb`, `serdal-pulse` + проверка статуса;
10. workflow дергает `https://serdal.ru/` и падает, если ответ не 200.

Если в релизе появилась новая переменная окружения — допишите её в серверный `.env`
**до** деплоя: `config:cache` запечёт текущее содержимое, и отсутствующая переменная
станет `null` молча, без ошибки.

### Настройка на сервере (один раз)

```bash
# 1. Пользователь деплоя (например, deploy) с доступом к каталогу приложения
sudo usermod -aG www-data deploy
sudo chown -R deploy:www-data /var/www/serdal.ru
sudo chmod -R g+w /var/www/serdal.ru/storage /var/www/serdal.ru/bootstrap/cache

# 2. Ключ для входа из GitHub Actions (генерируем локально, БЕЗ пароля)
ssh-keygen -t ed25519 -C "github-actions-deploy" -f ~/.ssh/serdal_deploy
ssh-copy-id -i ~/.ssh/serdal_deploy.pub deploy@serdal.ru

# 3. Разрешить рестарт сервисов без пароля.
#    ВАЖНО: путь к systemctl должен совпадать с фактическим — проверьте `which systemctl`.
sudo tee /etc/sudoers.d/deploy-serdal >/dev/null <<'EOF'
deploy ALL=(root) NOPASSWD: /usr/bin/systemctl restart php8.4-fpm, \
  /usr/bin/systemctl restart serdal-queue.service, \
  /usr/bin/systemctl restart serdal-reverb.service, \
  /usr/bin/systemctl restart serdal-pulse.service
EOF
sudo visudo -c
```

Проверьте, что у пользователя `deploy` работает `git fetch` (нужен deploy key на GitHub),
доступны `php`, `composer` и `rsync`. Node на сервере больше не требуется — можно снести
`node_modules` (~133 МБ), он нужен только для ручной сборки на месте.

Nginx должен ходить в `public/build` **по симлинку**: если в конфиге есть `disable_symlinks on`,
уберите или смените на `disable_symlinks if_not_owner from=$document_root`.

### Secrets и variables в GitHub

**Settings → Secrets and variables → Actions → Secrets:**

| Secret | Значение |
|--------|----------|
| `SSH_PRIVATE_KEY` | содержимое `~/.ssh/serdal_deploy` целиком, включая `-----BEGIN…END-----` |
| `SSH_HOST` | `serdal.ru` (или IP) |
| `SSH_USER` | `deploy` |
| `SSH_PORT` | порт SSH, если не 22 (необязательно) |
| `SSH_KNOWN_HOSTS` | вывод `ssh-keyscan -p 22 serdal.ru` |

**Variables** (необязательно): `PHP_FPM` = `php8.4-fpm`. Путь приложения задан в
`env.APP_DIR` самого workflow.

### Ручной запуск и откат

```bash
# Полный деплой руками (соберёт фронтенд на сервере — нужен node)
cd /var/www/serdal.ru && ./deploy.sh

# Деплой с уже залитыми ассетами
ASSETS_SHA=<sha> ./deploy.sh

# Откатить только ассеты на предыдущую сборку
ls -1dt /var/www/serdal.ru/builds/*/
ln -sfn /var/www/serdal.ru/builds/<sha> /var/www/serdal.ru/public/build
```

## Сервер конференций (BigBlueButton)

Отдельная машина, к serdal.ru отношения не имеет.

- **Хост**: `room.serdal.ru` → `84.38.184.203` (hostname `Descartes`)
- **Версия**: BigBlueButton 3.0
- **Платформа общается с ним** через API `https://room.serdal.ru/bigbluebutton/api`
  (пакет `joisarjignesh/bigbluebutton`, запросы подписываются секретом из `.env`)

### Greenlight отключён (21.08.2026)

Учителя переведены на нашу платформу, старая веб-морда Greenlight выключена.
Любой её путь теперь отдаёт 302 на `https://serdal.ru/login` — включая старые
закладки вида `/rooms`, `/signin`, `/b/signin`.

**Как это устроено.** В `/etc/nginx/sites-enabled/bigbluebutton` блок `location /`
отдаёт статику из `/var/www/bigbluebutton-default/assets`, а если файла нет —
проваливается в именованный `location @bbb-fe`. Это «фронтенд» в терминологии BBB.
Раньше `@bbb-fe` объявлял Greenlight, теперь — наша заглушка с редиректом.

| Что | Где |
|---|---|
| Заглушка с редиректом | `/usr/share/bigbluebutton/nginx/redirect-fe.nginx` |
| Отключённый конфиг Greenlight | `/root/greenlight-v3.nginx.off` |
| Контейнеры (остановлены) | `/root/greenlight-v3`, `docker compose stop` |
| Дамп БД перед отключением | `/root/greenlight-<дата>.sql.gz` |
| Список пользователей | `/root/gl_users.csv` |

База Greenlight называется `greenlight-v3-production` (с дефисами), пользователь
`postgres`, пароль — в `/root/greenlight-v3/.env`.

**Две мины, о которых легко забыть:**

1. Не запускайте `bbb-install.sh` с флагом `-g` — он поставит Greenlight заново
   и перезапишет `redirect-fe.nginx` своим конфигом.
2. Не включайте `default-fe.nginx.disabled` — он объявляет тот же `@bbb-fe`,
   что и наша заглушка. Nginx откажется стартовать из-за дубликата.

**Откат** (если Greenlight понадобится снова):

```bash
rm /usr/share/bigbluebutton/nginx/redirect-fe.nginx
mv /root/greenlight-v3.nginx.off /usr/share/bigbluebutton/nginx/greenlight-v3.nginx
nginx -t && systemctl reload nginx
cd /root/greenlight-v3 && docker compose start
```

**Проверка, что конференции живы** (после любых работ с nginx на этой машине):

```bash
curl -sI https://room.serdal.ru/ | head -1                   # 302 на serdal.ru
curl -s  https://room.serdal.ru/bigbluebutton/api | head -3  # SUCCESS
curl -sI https://room.serdal.ru/html5client/ | head -1       # 200
```

Пути конференции — `/html5client`, `/bigbluebutton`, `/graphql`, `/playback`, `/pad`,
`/learning-analytics-dashboard` — имеют собственные блоки в `/usr/share/bigbluebutton/nginx/`
и `@bbb-fe` не используют, поэтому работы с фронтендом их не задевают. Но проверять
всё равно нужно живым уроком: curl не покрывает цепочку `create → join → recording`.
