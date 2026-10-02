#!/usr/bin/env bash
#
# news.serdal.ru → https://serdal.ru/blog (301). Запускать на сервере под root один раз:
#     bash /var/www/serdal.ru/deploy/server/enable-news-redirect.sh
#
# news.serdal.ru — домен отправителя рассылок (Yandex Cloud Postbox, Яндекс 360). Сайта на нем нет:
# кто откроет адрес из письма, попадет в блог. Почту (MX, DKIM, SPF) скрипт не трогает — это записи DNS.
#
# ВАЖНО: если домен в Яндекс 360 подтверждается метатегом на странице news.serdal.ru — сначала подтвердите
# (или подтвердите записью TXT), потом запускайте: после переадресации страницы с метатегом не будет.
#
# 1. Выпускает отдельный сертификат Let's Encrypt для news.serdal.ru (конфиги основного сайта не трогаем).
# 2. Добавляет /etc/nginx/conf.d/serdal-news.conf: http и https → https://serdal.ru/blog.
# Повторный запуск безопасен.
#
set -euo pipefail

DOMAIN="news.serdal.ru"
TARGET="https://serdal.ru/blog"
CONF="/etc/nginx/conf.d/serdal-news.conf"

[ "$(id -u)" -eq 0 ] || { echo "!! Запустите под root"; exit 1; }
command -v certbot >/dev/null || { echo "!! certbot не установлен: apt install certbot python3-certbot-nginx"; exit 1; }

SERVER_IP="$(curl -s4 https://ifconfig.me || true)"
NEWS_IP="$(getent ahostsv4 "$DOMAIN" | awk 'NR==1{print $1}')"
if [ -n "$SERVER_IP" ] && [ "$NEWS_IP" != "$SERVER_IP" ]; then
    echo "!! $DOMAIN указывает на $NEWS_IP, а сервер — $SERVER_IP. Сначала поправьте A-запись news в DNS."
    exit 1
fi

if grep -Rl "server_name[^;]*$DOMAIN" /etc/nginx/sites-enabled/ /etc/nginx/conf.d/ 2>/dev/null | grep -v "serdal-news.conf" >/dev/null; then
    echo "!! $DOMAIN уже описан в другом конфиге nginx — уберите его оттуда и запустите снова"
    exit 1
fi

echo "==> Бэкап конфигов nginx: /root/nginx-backup-$(date +%F-%H%M).tar.gz"
tar czf "/root/nginx-backup-$(date +%F-%H%M).tar.gz" /etc/nginx

if certbot certificates --cert-name "$DOMAIN" 2>/dev/null | grep -q "Certificate Name: $DOMAIN"; then
    echo "==> Сертификат $DOMAIN уже есть"
else
    echo "==> Выпускаем сертификат $DOMAIN"
    # certonly: только получить сертификат, в конфиги certbot не пишет
    certbot certonly --nginx --cert-name "$DOMAIN" -d "$DOMAIN" --non-interactive --agree-tos --register-unsafely-without-email
fi

echo "==> $CONF"
cat > "$CONF" <<NGINX
# news.serdal.ru → блог: домен рассылок без своего сайта (deploy/server/enable-news-redirect.sh)
server {
    listen 80;
    server_name $DOMAIN;

    # Продление сертификата Let's Encrypt по http
    location /.well-known/acme-challenge/ {
        root /var/www/html;
    }

    location / {
        return 301 $TARGET;
    }
}

server {
    listen 443 ssl;
    server_name $DOMAIN;

    ssl_certificate /etc/letsencrypt/live/$DOMAIN/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/$DOMAIN/privkey.pem;

    return 301 $TARGET;
}
NGINX

if ! nginx -t; then
    echo "!! Конфиг nginx не прошел проверку — убираю $CONF, все работает как раньше"
    rm -f "$CONF"
    exit 1
fi
systemctl reload nginx

echo "==> Проверка"
curl -sI "http://$DOMAIN/" | grep -i -E "^HTTP|^location" || true
curl -sI "https://$DOMAIN/" | grep -i -E "^HTTP|^location" || true
echo "==> Готово: http(s)://$DOMAIN → $TARGET"
