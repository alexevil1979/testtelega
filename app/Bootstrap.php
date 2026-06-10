<?php

/**
 * Инициализация приложения: автозагрузка, .env, конфиг, сессия PHP.
 */

declare(strict_types=1);

namespace App;

use Dotenv\Dotenv;

final class Bootstrap
{
    private static bool $booted = false;

    /** @var array<string, mixed> */
    private static array $config = [];

    public static function init(string $rootPath): void
    {
        if (self::$booted) {
            return;
        }

        // Composer autoload
        require_once $rootPath . '/vendor/autoload.php';

        // Загрузка .env
        if (file_exists($rootPath . '/.env')) {
            $dotenv = Dotenv::createImmutable($rootPath);
            $dotenv->load();
        }

        // Конфигурация
        self::$config['app'] = require $rootPath . '/config/app.php';
        self::$config['database'] = require $rootPath . '/config/database.php';

        // Часовой пояс
        date_default_timezone_set(self::$config['app']['timezone']);

        // PHP-сессия для CSRF и состояния UI
        if (session_status() === PHP_SESSION_NONE) {
            session_start([
                'cookie_httponly' => true,
                'cookie_samesite' => 'Lax',
                'use_strict_mode' => true,
            ]);
        }

        // Обработка ошибок
        if (self::$config['app']['debug']) {
            error_reporting(E_ALL);
            ini_set('display_errors', '1');
        } else {
            error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
            ini_set('display_errors', '0');
        }

        self::$booted = true;
    }

    /**
     * @return array<string, mixed>
     */
    public static function config(string $key = ''): array
    {
        if ($key === '') {
            return self::$config;
        }

        return self::$config[$key] ?? [];
    }

    public static function rootPath(): string
    {
        return self::$config['app']['paths']['root'];
    }
}
