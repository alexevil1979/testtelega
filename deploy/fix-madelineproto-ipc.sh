#!/bin/bash
# Исправление MadelineProto IPC на VPS с кастомным PHP 8.2
# - proc_open / popen для запуска IPC worker
# - open_basedir с путями проекта и /tmp
# - PATH=/usr/local/php82/bin в PHP-FPM pool
#
# Запуск: sudo bash deploy/fix-madelineproto-ipc.sh

set -e

PHP_BIN="${PHP_BIN:-/usr/local/php82/bin/php}"
PHP_INI="${PHP_INI:-/usr/local/php82/etc/php.ini}"
FPM_POOL="${FPM_POOL:-/usr/local/php82/etc/php-fpm.d/www.conf}"
PROJECT="${PROJECT:-/ssd/www/testtelega}"

echo "=== MadelineProto IPC fix ==="
echo "PHP:     $PHP_BIN"
echo "php.ini: $PHP_INI"
echo "FPM:     $FPM_POOL"
echo "Project: $PROJECT"

if [ ! -x "$PHP_BIN" ]; then
    echo "[FAIL] PHP binary не найден: $PHP_BIN"
    exit 1
fi

if [ ! -f "$PHP_INI" ]; then
    echo "[FAIL] php.ini не найден: $PHP_INI"
    exit 1
fi

backup() {
    local f="$1"
    if [ -f "$f" ]; then
        cp -a "$f" "${f}.bak.$(date +%Y%m%d%H%M%S)"
        echo "[OK] Backup: ${f}.bak.*"
    fi
}

# --- php.ini: disable_functions ---
backup "$PHP_INI"

echo
echo "=== php.ini: disable_functions ==="
DISABLED=$(grep -E '^disable_functions\s*=' "$PHP_INI" | head -1 || true)
if [ -n "$DISABLED" ]; then
    echo "Было: $DISABLED"
    NEW=$(echo "$DISABLED" | sed -E \
        -e 's/proc_open,?//g' \
        -e 's/popen,?//g' \
        -e 's/shell_exec,?//g' \
        -e 's/exec,?//g' \
        -e 's/passthru,?//g' \
        -e 's/system,?//g' \
        -e 's/,,/,/g' \
        -e 's/^disable_functions\s*=\s*,/disable_functions = /' \
        -e 's/,\s*$//')
    sed -i "s|^disable_functions\s*=.*|${NEW}|" "$PHP_INI"
    echo "Стало: $NEW"
else
    echo "[--] disable_functions не задан — OK"
fi

# --- php.ini: open_basedir ---
echo
echo "=== php.ini: open_basedir ==="
OBDIR="${PROJECT}:/tmp:/usr/local/php82:/dev/urandom"
if grep -qE '^open_basedir\s*=' "$PHP_INI"; then
    sed -i "s|^open_basedir\s*=.*|open_basedir = ${OBDIR}|" "$PHP_INI"
    echo "[OK] open_basedir = $OBDIR"
elif grep -qE '^;\s*open_basedir\s*=' "$PHP_INI"; then
    sed -i "s|^;\s*open_basedir\s*=.*|open_basedir = ${OBDIR}|" "$PHP_INI"
    echo "[OK] open_basedir раскомментирован = $OBDIR"
else
    echo "open_basedir = ${OBDIR}" >> "$PHP_INI"
    echo "[OK] open_basedir добавлен"
fi

# --- php.ini: таймауты для авторизации ---
echo
echo "=== php.ini: max_execution_time ==="
if grep -qE '^max_execution_time\s*=' "$PHP_INI"; then
    sed -i 's/^max_execution_time\s*=.*/max_execution_time = 300/' "$PHP_INI"
else
    echo 'max_execution_time = 300' >> "$PHP_INI"
fi
echo "[OK] max_execution_time = 300"

# --- PHP-FPM pool ---
if [ -f "$FPM_POOL" ]; then
    backup "$FPM_POOL"
    echo
    echo "=== PHP-FPM pool: $FPM_POOL ==="

    grep -q '^clear_env' "$FPM_POOL" && \
        sed -i 's/^clear_env.*/clear_env = no/' "$FPM_POOL" || \
        echo 'clear_env = no' >> "$FPM_POOL"

    grep -q '^env\[PATH\]' "$FPM_POOL" && \
        sed -i 's|^env\[PATH\].*|env[PATH] = /usr/local/php82/bin:/usr/bin:/bin|' "$FPM_POOL" || \
        echo 'env[PATH] = /usr/local/php82/bin:/usr/bin:/bin' >> "$FPM_POOL"

    grep -q '^request_terminate_timeout' "$FPM_POOL" && \
        sed -i 's/^request_terminate_timeout.*/request_terminate_timeout = 300/' "$FPM_POOL" || \
        echo 'request_terminate_timeout = 300' >> "$FPM_POOL"

    # Убрать proc_open из pool-level disable_functions (если есть)
    if grep -q 'php_admin_value\[disable_functions\]' "$FPM_POOL"; then
        sed -i -E 's|php_admin_value\[disable_functions\].*||' "$FPM_POOL"
        echo "[OK] Удалён php_admin_value[disable_functions] из pool"
    fi

    if grep -q 'php_admin_value\[open_basedir\]' "$FPM_POOL"; then
        sed -i "s|php_admin_value\[open_basedir\].*|php_admin_value[open_basedir] = ${OBDIR}|" "$FPM_POOL"
    else
        echo "php_admin_value[open_basedir] = ${OBDIR}" >> "$FPM_POOL"
    fi

    echo "[OK] FPM pool обновлён"
else
    echo "[WARN] FPM pool не найден: $FPM_POOL"
fi

# --- Права на sessions и logs ---
echo
echo "=== Права sessions/ и logs/ ==="
mkdir -p "$PROJECT/sessions" "$PROJECT/logs"
chown -R www-data:www-data "$PROJECT/sessions" "$PROJECT/logs" 2>/dev/null || \
    chown -R apache:apache "$PROJECT/sessions" "$PROJECT/logs" 2>/dev/null || true
chmod 775 "$PROJECT/sessions" "$PROJECT/logs"

# --- Перезапуск FPM ---
echo
echo "=== Перезапуск PHP-FPM ==="
if systemctl list-units --type=service 2>/dev/null | grep -q php82-fpm; then
    systemctl restart php82-fpm || systemctl restart php-fpm82 || true
elif [ -f /usr/local/php82/sbin/php-fpm ]; then
    killall php-fpm 2>/dev/null || true
    /usr/local/php82/sbin/php-fpm --daemonize --fpm-config /usr/local/php82/etc/php-fpm.conf || true
else
    echo "[WARN] Не удалось определить сервис FPM — перезапустите вручную"
fi

# --- Проверки ---
echo
echo "=== Проверка CLI ==="
"$PHP_BIN" -r "
\$disabled = array_map('trim', explode(',', ini_get('disable_functions') ?: ''));
foreach (['proc_open','popen'] as \$fn) {
    echo in_array(\$fn, \$disabled, true) || !function_exists(\$fn)
        ? \"[FAIL] \$fn недоступен\n\"
        : \"[OK] \$fn доступен\n\";
}
echo '[INFO] open_basedir: ' . (ini_get('open_basedir') ?: '(не задан)') . \"\n\";
"

echo
echo "=== Сравнение CLI и WEB PHP ==="
echo "CLI: $($PHP_BIN -r 'echo PHP_VERSION;')"
WEB_VER=$(curl -sk "https://testtelega.1tlt.ru/api/auth/status" 2>/dev/null | head -c 200 || true)
if [ -n "$WEB_VER" ]; then
    echo "[OK] Web API отвечает: $WEB_VER"
else
    echo "[WARN] Web API не отвечает — проверьте Apache vhost:"
    echo "       sudo bash deploy/fix-apache-php82.sh"
fi

echo
echo "=== proc_open тест (CLI) ==="
"$PHP_BIN" -r "
\$p = proc_open('echo IPC_OK', [1 => ['pipe','w'], 2 => ['pipe','w']], \$pipes);
if (!is_resource(\$p)) { echo \"[FAIL] proc_open не запустил процесс\n\"; exit(1); }
echo stream_get_contents(\$pipes[1]);
proc_close(\$p);
"

echo
echo "[OK] Готово. Дальше на сервере:"
echo "  cd $PROJECT && git pull origin main"
echo "  bash deploy/reset-session.sh default   # если сессия зависла"
echo "  Повторите авторизацию на https://testtelega.1tlt.ru/auth"
echo "  Лог: tail -50 $PROJECT/logs/MadelineProto.log"
