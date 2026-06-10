<?php

/**
 * Подготовка окружения MadelineProto IPC на VPS с кастомным PHP.
 * Аналог явного command=/usr/local/php82/bin/php в Supervisor (botfabric).
 */

declare(strict_types=1);

namespace App\Services;

use App\Bootstrap;
use danog\MadelineProto\Ipc\Runner\ProcessRunner;
use ReflectionClass;

final class MadelineEnvironment
{
    private static bool $prepared = false;

    public static function prepare(): void
    {
        if (self::$prepared) {
            return;
        }

        $phpBin = Bootstrap::config('app')['php_bin'] ?? '/usr/local/php82/bin/php';
        $root = Bootstrap::config('app')['paths']['root'];
        $logsPath = Bootstrap::config('app')['paths']['logs'];

        if (!is_dir($logsPath)) {
            mkdir($logsPath, 0775, true);
        }

        // CWD: дефолтный MadelineProto.log не должен писаться в public/ (botfabric: var/log)
        if (is_dir($logsPath)) {
            @chdir($logsPath);
        }

        if (is_executable($phpBin)) {
            self::forceIpcPhpBinary($phpBin);
            self::prependPath(dirname($phpBin));
        }

        $projectBin = $root . '/bin';
        if (is_dir($projectBin)) {
            self::prependPath($projectBin);
        }

        self::$prepared = true;
    }

    private static function forceIpcPhpBinary(string $phpBin): void
    {
        try {
            $ref = new ReflectionClass(ProcessRunner::class);
            $prop = $ref->getProperty('binaryPath');
            $prop->setAccessible(true);
            $prop->setValue(null, $phpBin);
        } catch (\Throwable) {
            // fallback: только PATH
        }
    }

    private static function prependPath(string $dir): void
    {
        $parts = array_filter(explode(PATH_SEPARATOR, getenv('PATH') ?: ''));
        if (!in_array($dir, $parts, true)) {
            array_unshift($parts, $dir);
        }
        $newPath = implode(PATH_SEPARATOR, $parts);
        putenv('PATH=' . $newPath);
        $_SERVER['PATH'] = $newPath;
        $_ENV['PATH'] = $newPath;
    }
}
