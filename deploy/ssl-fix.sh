#!/bin/bash
# Установка уже выпущенного Let's Encrypt сертификата для testtelega.1tlt.ru
# Запуск: sudo bash deploy/ssl-fix.sh

set -e

DOMAIN="testtelega.1tlt.ru"
PROJECT="/ssd/www/testtelega"
VHOST="/etc/apache2/sites-available/testtelega.conf"

echo "=== SSL fix for $DOMAIN ==="

if [ ! -f "/etc/letsencrypt/live/$DOMAIN/fullchain.pem" ]; then
    echo "[FAIL] Сертификат не найден. Выпустите:"
    echo "  sudo certbot certonly --apache -d $DOMAIN"
    exit 1
fi

echo "[OK] Сертификат найден"

a2enmod proxy proxy_fcgi rewrite headers ssl 2>/dev/null || true

cp "$PROJECT/deploy/apache-vhost-php82-fpm-ssl.conf" "$VHOST"

apache2ctl configtest
systemctl reload apache2

echo "[OK] SSL установлен. Проверка:"
curl -sI "https://$DOMAIN" | head -5
