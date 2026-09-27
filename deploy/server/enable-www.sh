#!/usr/bin/env bash
#
# www.serdal.ru → serdal.ru (301). Запускать на сервере под root один раз:
#     bash /var/www/serdal.ru/deploy/server/enable-www.sh
#
# 1. Расширяет сертификат Let's Encrypt serdal.ru на www.serdal.ru (пути к файлам не меняются,
#    конфиг основного сайта не трогаем).
# 2. Добавляет /etc/nginx/conf.d/serdal-www.conf — server{} для www на 443 с переадресацией на https://serdal.ru.
# Повторный запуск безопасен.
#
set -euo pipefail

DOMAIN="serdal.ru"
WWW="www.serdal.ru"
CONF="/etc/nginx/conf.d/serdal-www.conf"

[ "$(id -u)" -eq 0 ] || { echo "!! Запустите под root"; exit 1; }
command -v certbot >/dev/null || { echo "!! certbot не установлен: apt install certbot python3-certbot-nginx"; exit 1; }

SERVER_IP="$(curl -s4 https://ifconfig.me || true)"
WWW_IP="$(getent ahostsv4 "$WWW" | awk 'NR==1{print $1}')"
if [ -n "$SERVER_IP" ] && [ "$WWW_IP" != "$SERVER_IP" ]; then
    echo "!! $WWW указывает на $WWW_IP, а сервер — $SERVER_IP. Сначала поправьте DNS."
    exit 1
fi

if ! certbot certificates --cert-name "$DOMAIN" 2>/dev/null | grep -q "Certificate Name: $DOMAIN"; then
    echo "!! Сертификат $DOMAIN выпущен не certbot — расширьте его тем же инструментом, которым выпускали."
    exit 1
fi

echo "==> Бэкап конфигов nginx: /root/nginx-backup-$(date +%F-%H%M).tar.gz"
tar czf "/root/nginx-backup-$(date +%F-%H%M).tar.gz" /etc/nginx

if certbot certificates --cert-name "$DOMAIN" 2>/dev/null | grep -q "Domains:.*$WWW"; then
    echo "==> Сертификат уже содержит $WWW"
else
    echo "==> Расширяем сертификат на $WWW"
    # certonly: только получить сертификат, в конфиги сайта certbot не пишет
    certbot certonly --nginx --cert-name "$DOMAIN" -d "$DOMAIN" -d "$WWW" --expand --non-interactive --agree-tos
fi

# www уже описан в server{} сайта (sites-enabled — симлинки, поэтому grep -R) — свой блок не нужен:
# nginx возьмёт первый, а второй выдаст предупреждение «conflicting server name». Хватит перезагрузки с новым сертификатом.
if grep -Rl "server_name[^;]*$WWW" /etc/nginx/sites-enabled/ /etc/nginx/conf.d/ 2>/dev/null | grep -v "serdal-www.conf" >/dev/null; then
    echo "==> $WWW уже есть в конфиге сайта — отдельный блок не создаю"
    rm -f "$CONF"
    nginx -t && systemctl reload nginx
    echo "==> Проверка"
    curl -sI "https://$WWW/about" | grep -i -E "^HTTP|^location" || true
    exit 0
fi

echo "==> $CONF"
cat > "$CONF" <<NGINX
# www.serdal.ru → serdal.ru: у сайта один адрес для поисковиков (deploy/server/enable-www.sh)
server {
    listen 443 ssl;
    server_name $WWW;

    ssl_certificate /etc/letsencrypt/live/$DOMAIN/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/$DOMAIN/privkey.pem;

    return 301 https://$DOMAIN\$request_uri;
}
NGINX

if ! nginx -t; then
    echo "!! Конфиг nginx не прошёл проверку — убираю $CONF, сайт работает как раньше"
    rm -f "$CONF"
    exit 1
fi
systemctl reload nginx

echo "==> Проверка"
curl -sI "https://$WWW/about" | grep -i -E "^HTTP|^location" || true
echo "==> Готово: https://$WWW → https://$DOMAIN"
