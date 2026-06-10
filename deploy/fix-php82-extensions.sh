#!/bin/bash
# Включение mbstring и других расширений в /usr/local/php82
# MadelineProto IPC worker использует CLI PHP — mbstring обязателен!
# Запуск: sudo bash deploy/fix-php82-extensions.sh

set -e

PHP_BIN="/usr/local/php82/bin/php"
PHP_INI="/usr/local/php82/etc/php.ini"
EXT_DIR=$(ls -d /usr/local/php82/lib/php/extensions/no-debug-non-zts-* 2>/dev/null | head -1)

echo "=== Fix PHP 8.2 extensions ==="
echo "PHP: $PHP_BIN"
echo "INI: $PHP_INI"
echo "EXT: $EXT_DIR"

if [ ! -f "$PHP_INI" ]; then
    echo "[FAIL] php.ini не найден: $PHP_INI"
    exit 1
fi

# Проверка mbstring
if "$PHP_BIN" -m | grep -qi '^mbstring$'; then
    echo "[OK] mbstring уже включён"
    exit 0
fi

echo "[WARN] mbstring отсутствует — включаем..."

# Список .so файлов
if [ -n "$EXT_DIR" ]; then
    ls "$EXT_DIR"/*.so 2>/dev/null | xargs -n1 basename
fi

# Раскомментировать extension= в php.ini
for ext in mbstring openssl curl xml zip gmp bcmath pdo_mysql mysqli intl pcntl; do
    if grep -q "^;extension=${ext}" "$PHP_INI" 2>/dev/null; then
        sed -i "s/^;extension=${ext}/extension=${ext}/" "$PHP_INI"
        echo "  enabled: extension=${ext}"
    elif grep -q "^;extension=${ext}.so" "$PHP_INI" 2>/dev/null; then
        sed -i "s/^;extension=${ext}.so/extension=${ext}.so/" "$PHP_INI"
        echo "  enabled: extension=${ext}.so"
    fi
done

# Если extension_dir не задан
if ! grep -q "^extension_dir" "$PHP_INI" && [ -n "$EXT_DIR" ]; then
    echo "extension_dir = \"$EXT_DIR\"" >> "$PHP_INI"
    echo "  set extension_dir"
fi

echo
echo "=== Проверка после правок ==="
"$PHP_BIN" -m | grep -E 'mbstring|openssl|curl|gmp|pcntl'

if "$PHP_BIN" -m | grep -qi '^mbstring$'; then
    echo "[OK] mbstring включён"
else
    echo "[FAIL] mbstring всё ещё отсутствует!"
    echo "Возможно нужна пересборка PHP с --enable-mbstring"
    echo "Или вручную добавьте в $PHP_INI:"
    echo "  extension=mbstring"
    exit 1
fi

# Перезапуск FPM если есть
if [ -f /usr/local/php82/sbin/php-fpm ]; then
    /usr/local/php82/sbin/php-fpm restart 2>/dev/null || killall -USR2 php-fpm 2>/dev/null || true
    echo "[OK] php-fpm перезапущен"
fi

systemctl reload apache2 2>/dev/null || true
echo "Готово."
