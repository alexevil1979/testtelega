#!/bin/bash
# Остановить фоновые IPC-воркеры MadelineProto (без удаления сессии)
# Запуск: sudo bash deploy/stop-madeline-ipc.sh

PROJECT="${PROJECT:-/ssd/www/testtelega}"

echo "=== Остановка MadelineProto IPC workers ==="
pgrep -af "madeline-ipc|MadelineProto worker" || echo "[--] активных воркеров не найдено"

pkill -f "madeline-ipc.*${PROJECT}/sessions" 2>/dev/null || true
pkill -f "MadelineProto worker.*${PROJECT}/sessions" 2>/dev/null || true
sleep 1

pgrep -af "madeline-ipc|MadelineProto worker" && echo "[WARN] часть процессов ещё жива" || echo "[OK] IPC workers остановлены"
