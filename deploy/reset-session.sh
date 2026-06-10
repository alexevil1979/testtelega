#!/bin/bash
# Сброс сессии MadelineProto (MadelineProto 8 хранит сессию как ПАПКУ)
# Запуск: sudo bash deploy/reset-session.sh [session_id]

set -e

PROJECT="/ssd/www/testtelega"
SESSION="${1:-default}"

echo "=== Сброс сессии: $SESSION ==="

# MadelineProto 8 — директория .madeline
rm -rf "$PROJECT/sessions/${SESSION}.madeline"
rm -f "$PROJECT/sessions/${SESSION}.madeline"*

# IPC-файлы
rm -f "$PROJECT/sessions/"*.ipc "$PROJECT/sessions/"*.lock

chown -R www-data:www-data "$PROJECT/sessions"
chmod 750 "$PROJECT/sessions"

echo "[OK] Сессия $SESSION удалена"
ls -la "$PROJECT/sessions/"
