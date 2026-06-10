<?php

/**
 * Подготовка окружения MadelineProto на VPS с кастомным PHP.
 * Веб (FPM): полная инициализация без IPC — как altervista / CLI в botfabric.
 */

declare(strict_types=1);

namespace App\Services;

use App\Bootstrap;
use danog\MadelineProto\Ipc\Runner\ProcessRunner;
use danog\MadelineProto\Magic;
use ReflectionClass;

final class MadelineEnvironment
{
    private static bool $prepared = false;

    public static function prepare(?string $sessionPath = null): void
    {
        if (self::$prepared) {
            return;
        }

        $phpBin = self::getPhpBin();
        $root = Bootstrap::config('app')['paths']['root'];

        if (is_executable($phpBin)) {
            self::forceIpcPhpBinary($phpBin);
            self::prependPath(dirname($phpBin));
        }

        $projectBin = $root . '/bin';
        if (is_dir($projectBin)) {
            self::prependPath($projectBin);
        }

        if (PHP_SAPI !== 'cli') {
            @ini_set('max_execution_time', '300');
        }

        self::$prepared = true;
    }

    /**
     * Вызвать непосредственно перед new API().
     * Magic::start() в конструкторе API сбрасывает $altervista — используем флаг MadelineProto.
     */
    public static function applyBeforeApiConstruct(?string $sessionPath = null): void
    {
        self::prepare($sessionPath);

        if (!self::shouldForceFullInit($sessionPath)) {
            return;
        }

        // connectToMadelineProto: forceFull ||= isset($_GET['MadelineSelfRestart'])
        $_GET['MadelineSelfRestart'] = '1';
        Magic::$altervista = true;
    }

    public static function isForceFullActive(): bool
    {
        return isset($_GET['MadelineSelfRestart']) || Magic::$altervista;
    }

    public static function getPhpBin(): string
    {
        return Bootstrap::config('app')['php_bin'] ?? '/usr/local/php82/bin/php';
    }

    /**
     * @return array<string, mixed>
     */
    public static function getDiagnostics(): array
    {
        $phpBin = self::getPhpBin();
        $disabled = array_filter(array_map('trim', explode(',', (string) ini_get('disable_functions'))));

        $ipcBinary = null;
        try {
            $ref = new ReflectionClass(ProcessRunner::class);
            $prop = $ref->getProperty('binaryPath');
            $prop->setAccessible(true);
            $ipcBinary = $prop->getValue();
        } catch (\Throwable) {
            $ipcBinary = null;
        }

        return [
            'php_sapi' => PHP_SAPI,
            'php_version' => PHP_VERSION,
            'php_binary' => PHP_BINARY,
            'configured_php_bin' => $phpBin,
            'php_bin_executable' => is_executable($phpBin),
            'ipc_binary_cached' => $ipcBinary,
            'path' => getenv('PATH') ?: '',
            'proc_open' => function_exists('proc_open') && !in_array('proc_open', $disabled, true),
            'open_basedir' => ini_get('open_basedir') ?: null,
            'force_full' => self::isForceFullActive(),
            'madeline_force_full_env' => Bootstrap::config('app')['madeline_force_full'] ?? true,
            'cwd' => getcwd(),
        ];
    }

    private static function shouldForceFullInit(?string $sessionPath): bool
    {
        if (PHP_SAPI === 'cli') {
            return false;
        }

        $configForce = Bootstrap::config('app')['madeline_force_full'] ?? true;
        if (!$configForce) {
            return false;
        }

        // В FPM IPC нестабилен — полная инициализация в том же процессе (как altervista / CLI botfabric).
        // MADELINE_FORCE_FULL=false — только если IPC проверен через /api/diag/madeline.
        return true;
    }

    private static function forceIpcPhpBinary(string $phpBin): void
    {
        try {
            $ref = new ReflectionClass(ProcessRunner::class);
            $prop = $ref->getProperty('binaryPath');
            $prop->setAccessible(true);
            $prop->setValue(null, $phpBin);
        } catch (\Throwable) {
            // fallback: PATH
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
