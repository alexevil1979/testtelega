#!/bin/bash
# Права на папки TestTelega
# Запуск: sudo bash deploy/fix-permissions.sh

set -e

PROJECT="/ssd/www/testtelega"

echo "=== Fix permissions: $PROJECT ==="

mkdir -p "$PROJECT/sessions" "$PROJECT/logs"

chown -R www-data:www-data "$PROJECT/sessions" "$PROJECT/logs"
chmod 750 "$PROJECT/sessions"
chmod 775 "$PROJECT/logs"

if [ -f "$PROJECT/.env" ]; then
    chown www-data:www-data "$PROJECT/.env"
    chmod 640 "$PROJECT/.env"
fi

# Удалить ошибочный лог из public/, если создался
rm -f "$PROJECT/public/MadelineProto.log"

echo "[OK] sessions/ logs/ — www-data"
ls -la "$PROJECT/sessions" "$PROJECT/logs"
