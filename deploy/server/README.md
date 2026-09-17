# Серверные настройки serdal.ru

Конфиги, которые живут вне каталога приложения. `deploy.sh` их **не** ставит —
они применяются вручную под root; здесь лежит эталон, чтобы настройки не терялись.

| Файл | Куда | После изменения |
|---|---|---|
| `nginx-serdal-tuning.conf` | `/etc/nginx/conf.d/serdal-tuning.conf` | `nginx -t && systemctl reload nginx` |
| `nginx-serdal-site-snippet.conf` | `/etc/nginx/snippets/serdal-static.conf` + `include` в server{} | то же |
| `php-99-serdal.ini` | `/etc/php/8.4/fpm/conf.d/99-serdal.ini` | `systemctl restart php8.4-fpm` |
| `fail2ban-jail.local` | `/etc/fail2ban/jail.local` | `systemctl restart fail2ban` |

Ещё на сервере (без файла-эталона):

- PHP-FPM пул `/etc/php/8.4/fpm/pool.d/www.conf`: `pm.max_children = 12`, `pm.start_servers = 3`,
  `pm.min_spare_servers = 2`, `pm.max_spare_servers = 6`, `pm.max_requests = 500`.
- `ufw`: открыты только 22, 80, 443 и 10050 для серверов мониторинга хостера.
- `.env`: `APP_DEBUG=false`, `LOG_STACK=daily`, `LOG_LEVEL=info`.
- systemd-юниты — в `deploy/systemd`, ставятся автоматически (`deploy/install-systemd.sh`).
- Бэкапы: `php artisan backup:run [--files]` по расписанию, S3 `backups/db` и `backups/files` (private).
