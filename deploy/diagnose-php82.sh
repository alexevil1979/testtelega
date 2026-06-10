#!/bin/bash
# Диагностика PHP 8.2 (/usr/local/php82) + FPM сокет + расширения
# Запуск: bash deploy/diagnose-php82.sh

PHP_BIN="${PHP_BIN:-/usr/local/php82/bin/php}"

echo "=== PHP binary ==="
ls -la "$PHP_BIN" 2>/dev/null || echo "[FAIL] $PHP_BIN не найден"

echo
echo "=== PHP version & ini ==="
"$PHP_BIN" -v
"$PHP_BIN" --ini

echo
echo "=== Обязательные расширения (web + IPC worker) ==="
for ext in mbstring openssl curl gmp pcntl pdo_mysql xml zip bcmath intl; do
    if "$PHP_BIN" -m | grep -qi "^${ext}$"; then
        echo "[OK] $ext"
    else
        echo "[FAIL] $ext — ОТСУТСТВУЕТ"
    fi
done

echo
echo "=== Поиск PHP-FPM сокетов ==="
for path in \
    /usr/local/php82/var/run/php-fpm.sock \
    /usr/local/php82/var/run/www.sock \
    /usr/local/php82/var/run/php-fpm82.sock \
    /run/php/php8.2-fpm.sock \
    /var/run/php-fpm.sock; do
    if [ -S "$path" ]; then
        echo "[OK] сокет найден: $path"
        ls -la "$path"
    fi
done

echo
echo "=== Активный Apache vhost (SetHandler) ==="
grep -r "SetHandler\|php-fpm\|fcgi" /etc/apache2/sites-enabled/ 2>/dev/null || true

echo
echo "=== PHP-FPM процессы ==="
ps aux | grep -E 'php-fpm|php82' | grep -v grep || echo "php-fpm не запущен"
