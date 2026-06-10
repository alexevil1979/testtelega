#!/bin/bash
# Исправить Apache: использовать сокет /usr/local/php82, не /run/php/php8.2-fpm.sock
# Запуск: sudo bash deploy/fix-apache-php82.sh

set -e

PROJECT="/ssd/www/testtelega"
VHOST="/etc/apache2/sites-available/testtelega.conf"

# Найти реальный сокет
SOCKET=""
for path in \
    /usr/local/php82/var/run/php-fpm.sock \
    /usr/local/php82/var/run/www.sock \
    /usr/local/php82/var/run/php-fpm82.sock; do
    if [ -S "$path" ]; then
        SOCKET="$path"
        break
    fi
done

if [ -z "$SOCKET" ]; then
    echo "[WARN] Сокет FPM не найден. Запустите php-fpm:"
    echo "  /usr/local/php82/sbin/php-fpm -D"
    echo "  или: systemctl start php82-fpm"
    SOCKET="/usr/local/php82/var/run/php-fpm.sock"
fi

echo "=== Используем сокет: $SOCKET ==="

# Копируем SSL vhost из проекта
cp "$PROJECT/deploy/apache-vhost-php82-fpm-ssl.conf" "$VHOST"

# Подставляем найденный сокет
sed -i "s|unix:/usr/local/php82/var/run/php-fpm.sock|unix:${SOCKET}|g" "$VHOST"

# Удалить неправильные SetHandler от certbot (если добавил php8.2-fpm.sock из apt)
sed -i 's|/run/php/php8.2-fpm.sock|'"${SOCKET}"'|g' "$VHOST"

a2enmod proxy proxy_fcgi rewrite headers ssl 2>/dev/null || true
a2ensite testtelega 2>/dev/null || true

echo "=== Активный SetHandler ==="
grep SetHandler "$VHOST"

apache2ctl configtest
systemctl reload apache2

echo "[OK] Apache настроен на $SOCKET"
