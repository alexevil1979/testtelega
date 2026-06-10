#!/bin/bash
# Исправить Apache vhost testtelega — PHP 8.2 FPM из /usr/local/php82
# Запуск: sudo bash deploy/fix-apache-php82.sh

set -e

PROJECT="/ssd/www/testtelega"
VHOST="/etc/apache2/sites-available/testtelega.conf"
FPM_CONF="/usr/local/php82/etc/php-fpm.conf"

# Читаем listen из php-fpm
LISTEN=$(grep -rhE "^\s*listen\s*=" /usr/local/php82/etc/php-fpm.d/ "$FPM_CONF" 2>/dev/null | grep -v ';' | head -1 | sed 's/.*=\s*//;s/\s*$//')

if [ -z "$LISTEN" ]; then
    echo "[FAIL] Не найден listen в $FPM_CONF"
    echo "Проверьте: grep -r listen /usr/local/php82/etc/"
    exit 1
fi

echo "=== PHP-FPM listen: $LISTEN ==="

# Формируем SetHandler
if [[ "$LISTEN" == /* ]]; then
    HANDLER="proxy:unix:${LISTEN}|fcgi://localhost"
else
    HANDLER="proxy:fcgi://${LISTEN}"
fi

echo "=== SetHandler: $HANDLER ==="

cp "$PROJECT/deploy/apache-vhost-php82-fpm-ssl.conf" "$VHOST"

# Заменяем SetHandler в vhost
sed -i "s|SetHandler \"proxy:unix:.*\"|SetHandler \"${HANDLER}\"|" "$VHOST"

a2enmod proxy proxy_fcgi rewrite headers ssl 2>/dev/null || true
a2ensite testtelega 2>/dev/null || true
a2dissite 000-default.conf 2>/dev/null || true

echo "=== Итоговый vhost (фрагмент) ==="
grep -E "ServerName|SetHandler|DocumentRoot" "$VHOST"

apache2ctl configtest
systemctl reload apache2

echo "[OK] Apache настроен: $HANDLER"
