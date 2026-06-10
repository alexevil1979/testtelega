#!/bin/bash
# Проверка окружения TestTelega для VPS с кастомным PHP 8.2
# Запуск: bash deploy/check-php82.sh

PHP_BIN="${PHP_BIN:-/usr/local/php82/bin/php}"
PROJECT="${PROJECT:-/ssd/www/testtelega}"

echo "=== TestTelega PHP check ==="
echo "PHP_BIN: $PHP_BIN"
echo "PROJECT: $PROJECT"
echo

if [ ! -x "$PHP_BIN" ]; then
    echo "[FAIL] PHP не найден: $PHP_BIN"
    exit 1
fi

echo "[OK] $($PHP_BIN -v | head -1)"
echo "php.ini: $($PHP_BIN --ini | grep 'Loaded Configuration' | sed 's/.*: //')"
echo

REQUIRED=(openssl curl mbstring xml zip gmp bcmath pdo_mysql pcntl json intl)
MISSING=()

for ext in "${REQUIRED[@]}"; do
    if $PHP_BIN -m | grep -qi "^${ext}$"; then
        echo "[OK] extension: $ext"
    else
        echo "[FAIL] extension: $ext"
        MISSING+=("$ext")
    fi
done

echo
if [ ${#MISSING[@]} -gt 0 ]; then
    echo "Отсутствуют расширения: ${MISSING[*]}"
    echo "Правьте: /usr/local/php82/etc/php.ini"
    exit 1
fi

if [ -d "$PROJECT/vendor" ]; then
    echo "[OK] vendor/ установлен"
else
    echo "[WARN] vendor/ не найден — выполните:"
    echo "  $PHP_BIN \$(command -v composer) install --no-dev --optimize-autoloader"
fi

echo
echo "Composer install:"
echo "  cd $PROJECT && $PHP_BIN \$(command -v composer) install --no-dev --optimize-autoloader"
