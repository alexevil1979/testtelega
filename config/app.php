<?php

/**
 * Основные настройки приложения TestTelega.
 */

declare(strict_types=1);

return [
    'name' => $_ENV['APP_NAME'] ?? 'TestTelega',
    'env' => $_ENV['APP_ENV'] ?? 'production',
    'debug' => filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN),
    'url' => $_ENV['APP_URL'] ?? 'http://localhost',
    'key' => $_ENV['APP_KEY'] ?? '',
    'timezone' => $_ENV['APP_TIMEZONE'] ?? 'Europe/Moscow',

    // Путь к PHP CLI (для MadelineProto IPC worker на VPS с кастомной сборкой)
    'php_bin' => $_ENV['PHP_BIN'] ?? '/usr/local/php82/bin/php',

    // Веб (FPM): полная инициализация без IPC, пока нет lightState.php (см. MadelineEnvironment)
    'madeline_force_full' => filter_var($_ENV['MADELINE_FORCE_FULL'] ?? true, FILTER_VALIDATE_BOOLEAN),

    'telegram' => [
        'api_id' => (int) ($_ENV['TELEGRAM_API_ID'] ?? 0),
        'api_hash' => $_ENV['TELEGRAM_API_HASH'] ?? '',
    ],

    'proxy' => [
        'enabled' => filter_var($_ENV['PROXY_ENABLED'] ?? true, FILTER_VALIDATE_BOOLEAN),
        'mtproto_url' => $_ENV['PROXY_URL'] ?? 'socks5://127.0.0.1:1084',
        'http_api_url' => $_ENV['HTTP_API_PROXY_URL'] ?? 'socks5://127.0.0.1:1084',
    ],

    'paths' => [
        'root' => dirname(__DIR__),
        'sessions' => dirname(__DIR__) . '/' . ($_ENV['SESSIONS_PATH'] ?? 'sessions'),
        'logs' => dirname(__DIR__) . '/' . ($_ENV['LOGS_PATH'] ?? 'logs'),
    ],

    'security' => [
        'csrf_token_name' => $_ENV['CSRF_TOKEN_NAME'] ?? '_csrf_token',
        'rate_limit_requests' => (int) ($_ENV['RATE_LIMIT_REQUESTS'] ?? 60),
        'rate_limit_window' => (int) ($_ENV['RATE_LIMIT_WINDOW'] ?? 60),
    ],

    'mtproto_log' => [
        'enabled' => filter_var($_ENV['MTProto_LOG_ENABLED'] ?? true, FILTER_VALIDATE_BOOLEAN),
        'retention_days' => (int) ($_ENV['MTProto_LOG_RETENTION_DAYS'] ?? 30),
    ],
];
