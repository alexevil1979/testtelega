#!/bin/bash
# Включение расширений в /usr/local/php82
# gmp НЕ обязателен — MadelineProto использует bcmath как fallback
# Запуск: sudo bash deploy/fix-php82-extensions.sh

set -e

PHP_BIN="/usr/local/php82/bin/php"
PHP_INI="/usr/local/php82/etc/php.ini"
EXT_DIR=$(ls -d /usr/local/php82/lib/php/extensions/no-debug-non-zts-* 2>/dev/null | head -1)
REQUIRED=(mbstring openssl curl pcntl pdo_mysql xml zip bcmath intl)
OPTIONAL=(gmp)

echo "=== Fix PHP 8.2 extensions ==="
echo "PHP: $PHP_BIN"
echo "INI: $PHP_INI"
echo "EXT: $EXT_DIR"

if [ ! -f "$PHP_INI" ]; then
    echo "[FAIL] php.ini не найден: $PHP_INI"
    exit 1
fi

echo
echo "=== Доступные .so ==="
if [ -n "$EXT_DIR" ]; then
    ls "$EXT_DIR"/*.so 2>/dev/null | xargs -n1 basename || true
fi

# extension_dir
if ! grep -q "^extension_dir" "$PHP_INI" && [ -n "$EXT_DIR" ]; then
    echo "extension_dir = \"$EXT_DIR\"" >> "$PHP_INI"
    echo "  set extension_dir"
fi

# Раскомментировать extension= в php.ini (обязательные + опциональные)
for ext in "${REQUIRED[@]}" "${OPTIONAL[@]}"; do
    if "$PHP_BIN" -m 2>/dev/null | grep -qi "^${ext}$"; then
        continue
    fi
    if grep -q "^;extension=${ext}\b" "$PHP_INI" 2>/dev/null; then
        sed -i "s/^;extension=${ext}/extension=${ext}/" "$PHP_INI"
        echo "  enabled: extension=${ext}"
    elif grep -q "^;extension=${ext}.so" "$PHP_INI" 2>/dev/null; then
        sed -i "s/^;extension=${ext}.so/extension=${ext}.so/" "$PHP_INI"
        echo "  enabled: extension=${ext}.so"
    elif [ -f "${EXT_DIR}/${ext}.so" ]; then
        echo "extension=${ext}.so" >> "$PHP_INI"
        echo "  added: extension=${ext}.so"
    elif [[ " ${OPTIONAL[*]} " == *" ${ext} "* ]]; then
        echo "  [SKIP] ${ext} — опционально, не критично"
    else
        echo "  [WARN] ${ext}.so не найден"
    fi
done

echo
echo "=== Проверка после правок ==="
FAIL=0
for ext in "${REQUIRED[@]}"; do
    if "$PHP_BIN" -m | grep -qi "^${ext}$"; then
        echo "[OK] $ext"
    else
        echo "[FAIL] $ext"
        FAIL=1
    fi
done

if "$PHP_BIN" -m | grep -qi '^gmp$'; then
    echo "[OK] gmp (опционально)"
else
    echo "[--] gmp не установлен — OK, используется bcmath"
fi

if [ "$FAIL" -eq 1 ]; then
    exit 1
fi

# Перезапуск php82-fpm
if [ -f /usr/local/php82/sbin/php-fpm ]; then
    kill -USR2 $(pgrep -f 'php82/etc/php-fpm.conf' | head -1) 2>/dev/null || \
    /usr/local/php82/sbin/php-fpm -D 2>/dev/null || true
    echo "[OK] php82-fpm перезапущен"
fi

systemctl reload apache2 2>/dev/null || true
echo "Готово."
