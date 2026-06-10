#!/bin/bash
# Диагностика PHP 8.2 (/usr/local/php82) + FPM сокет + расширения
# Запуск: bash deploy/diagnose-php82.sh

PHP_BIN="${PHP_BIN:-/usr/local/php82/bin/php}"
FPM_CONF="/usr/local/php82/etc/php-fpm.conf"

echo "=== PHP binary ==="
ls -la "$PHP_BIN" 2>/dev/null || echo "[FAIL] $PHP_BIN не найден"

echo
echo "=== PHP version & ini ==="
"$PHP_BIN" -v
"$PHP_BIN" --ini

echo
echo "=== Обязательные расширения (web + IPC worker) ==="
MISSING=0
for ext in mbstring openssl curl pcntl pdo_mysql xml zip bcmath intl; do
    if "$PHP_BIN" -m | grep -qi "^${ext}$"; then
        echo "[OK] $ext"
    else
        echo "[FAIL] $ext — ОТСУТСТВУЕТ"
        MISSING=$((MISSING + 1))
    fi
done
if [ "$MISSING" -gt 0 ]; then
    echo
    echo ">>> Запустите: sudo bash deploy/fix-php82-extensions.sh"
fi

echo
echo "=== Опционально (ускорение, не обязательно) ==="
if "$PHP_BIN" -m | grep -qi '^gmp$'; then
    echo "[OK] gmp — быстрая математика"
else
    echo "[--] gmp — не установлен, MadelineProto работает через bcmath (медленнее)"
fi

echo
echo "=== PHP-FPM listen (из конфига) ==="
if [ -f "$FPM_CONF" ]; then
    grep -rE "^\s*listen\s*=" /usr/local/php82/etc/php-fpm.d/ "$FPM_CONF" 2>/dev/null | grep -v ';' || true
else
    echo "[WARN] $FPM_CONF не найден"
fi

echo
echo "=== Поиск PHP-FPM сокетов на диске ==="
find /usr/local/php82 /tmp /var/run /run -name '*.sock' 2>/dev/null | while read -r path; do
    if [ -S "$path" ]; then
        echo "[OK] $path"
        ls -la "$path"
    fi
done

echo
echo "=== PHP 8.2 FPM master ==="
ps aux | grep 'php82/etc/php-fpm' | grep -v grep || echo "[WARN] php82-fpm master не найден"

echo
echo "=== Активный Apache vhost testtelega ==="
grep -rE "SetHandler|ServerName|DocumentRoot" /etc/apache2/sites-enabled/ 2>/dev/null | grep -i testtelega -A5 -B2 || \
grep -rE "SetHandler|ServerName" /etc/apache2/sites-enabled/ 2>/dev/null || echo "[WARN] vhost не найден"

echo
echo "=== Рекомендуемый SetHandler ==="
# Активный пул — www.conf (не .default)
LISTEN=""
if [ -f /usr/local/php82/etc/php-fpm.d/www.conf ]; then
    LISTEN=$(grep -E "^\s*listen\s*=" /usr/local/php82/etc/php-fpm.d/www.conf | grep -v ';' | head -1 | sed 's/.*=\s*//;s/\s*$//')
fi
if [ -z "$LISTEN" ]; then
    LISTEN=$(grep -rhE "^\s*listen\s*=" /usr/local/php82/etc/php-fpm.d/ "$FPM_CONF" 2>/dev/null | grep -v ';' | grep -v '.default' | head -1 | sed 's/.*=\s*//;s/\s*$//')
fi
if [ -n "$LISTEN" ]; then
    if [[ "$LISTEN" == /* ]]; then
        echo "SetHandler \"proxy:unix:${LISTEN}|fcgi://localhost\""
    else
        echo "SetHandler \"proxy:fcgi://${LISTEN}\""
    fi
else
    echo "[WARN] listen не найден в php-fpm.conf"
fi
