# Серверные настройки serdal.ru

Конфиги, которые живут вне каталога приложения. `deploy.sh` их **не** ставит —
они применяются вручную под root; здесь лежит эталон, чтобы настройки не терялись.

| Файл | Куда | После изменения |
|---|---|---|
| `nginx-serdal-tuning.conf` | `/etc/nginx/conf.d/serdal-tuning.conf` | `nginx -t && systemctl reload nginx` |
| `nginx-serdal-site-snippet.conf` | `/etc/nginx/snippets/serdal-static.conf` + `include` в server{} | то же |
| `php-99-serdal.ini` | `/etc/php/8.4/fpm/conf.d/99-serdal.ini` | `systemctl restart php8.4-fpm` |
| `fail2ban-jail.local` | `/etc/fail2ban/jail.local` | `systemctl restart fail2ban` |
| `enable-www.sh` | сертификат на www + `/etc/nginx/conf.d/serdal-www.conf` (www → serdal.ru, 301) | запустить один раз: `bash deploy/server/enable-www.sh` |
| `enable-news-redirect.sh` | сертификат на news.serdal.ru + `/etc/nginx/conf.d/serdal-news.conf` (news.serdal.ru → serdal.ru/blog, 301) | запустить один раз: `bash deploy/server/enable-news-redirect.sh` |

Ещё на сервере (без файла-эталона):

- PHP-FPM пул `/etc/php/8.4/fpm/pool.d/www.conf`: `pm.max_children = 12`, `pm.start_servers = 3`,
  `pm.min_spare_servers = 2`, `pm.max_spare_servers = 6`, `pm.max_requests = 500`.
- `ufw`: открыты только 22, 80, 443 и 10050 для серверов мониторинга хостера.
- `ffmpeg` (`apt install ffmpeg`): сжатие видео и GIF в новостях (`MediaService`, задача `ConvertVideo` в очереди `recordings`). Без него кнопка «Видео» отвечает «На сервере не настроена обработка видео», GIF загружаются как есть.
- `.env`: `APP_DEBUG=false`, `LOG_STACK=daily`, `LOG_LEVEL=info`.
- systemd-юниты — в `deploy/systemd`, ставятся автоматически (`deploy/install-systemd.sh`).
- Бэкапы: `php artisan backup:run [--files]` по расписанию, S3 `backups/db` и `backups/files` (private).
