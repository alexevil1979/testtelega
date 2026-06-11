<?php

/**
 * Обёртка над MadelineProto с логированием всех вызовов.
 * Управляет сессиями, авторизацией и MTProto-методами.
 */

declare(strict_types=1);

namespace App\Services;

use App\Bootstrap;
use danog\MadelineProto\API;
use danog\MadelineProto\Settings;

final class TelegramService
{
    private static ?API $api = null;
    private static ?string $sessionFile = null;

    /**
     * Получить или создать экземпляр MadelineProto API.
     */
    public static function getApi(?string $sessionId = null): API
    {
        $sessionId = $sessionId ?? ($_SESSION['telegram_session_id'] ?? 'default');
        $sessionPath = Bootstrap::config('app')['paths']['sessions'] . '/' . $sessionId . '.madeline';

        if (self::$api !== null && self::$sessionFile === $sessionPath) {
            return self::$api;
        }

        MadelineEnvironment::applyBeforeApiConstruct($sessionPath);

        $settings = new Settings();
        $settings->getAppInfo()
            ->setApiId(Bootstrap::config('app')['telegram']['api_id'])
            ->setApiHash(Bootstrap::config('app')['telegram']['api_hash']);

        $settings->getConnection()->setTimeout(60.0);
        $settings->getRpc()->setRpcDropTimeout(120);

        // SOCKS5/HTTP прокси для MTProto (всегда из .env или настроек UI)
        ProxyConfig::applyToSettings($settings);

        // Лог MadelineProto — в logs/, НЕ в public/ (иначе Permission denied)
        $logsPath = Bootstrap::config('app')['paths']['logs'];
        if (!is_dir($logsPath)) {
            mkdir($logsPath, 0775, true);
        }
        $settings->getLogger()
            ->setExtra($logsPath . '/MadelineProto.log')
            ->setLevel(\danog\MadelineProto\Logger::LEVEL_NOTICE);

        // Буферизация: MadelineProto не должен выводить HTML/JS в ответ веб-страницы
        ob_start();
        try {
            self::$api = new API($sessionPath, $settings);
        } finally {
            ob_end_clean();
        }

        self::$sessionFile = $sessionPath;
        $_SESSION['telegram_session_id'] = $sessionId;

        return self::$api;
    }

    /**
     * Вызов MTProto-метода с логированием.
     * MadelineProto 8.x: $api->messages->getDialogs(...) вместо methodCallAsyncRead на API.
     *
     * @param array<string, mixed> $params
     */
    public static function call(string $method, array $params = [], string $category = 'general'): mixed
    {
        $api = self::getApi();
        $start = microtime(true);
        $error = null;
        $response = null;

        try {
            $response = self::invokeMtproto($api, $method, $params);
        } catch (\Throwable $e) {
            $error = $e->getMessage();
            $duration = (microtime(true) - $start) * 1000;
            self::safeLog($method, $params, null, $duration, $error, $category, $api);
            throw $e;
        }

        $duration = (microtime(true) - $start) * 1000;
        self::safeLog($method, $params, $response, $duration, null, $category, $api);

        return $response;
    }

    /**
     * @param array<string, mixed> $params
     */
    private static function invokeMtproto(API $api, string $method, array $params): mixed
    {
        $parts = explode('.', $method, 2);
        if (count($parts) !== 2) {
            throw new \InvalidArgumentException("Неверный MTProto-метод: {$method}");
        }

        [$namespace, $methodName] = $parts;
        if (!isset($api->$namespace)) {
            throw new \InvalidArgumentException("Неизвестный namespace MadelineProto: {$namespace}");
        }

        return $api->$namespace->$methodName($params);
    }

    /**
     * Логирование не должно ломать MTProto-вызовы.
     *
     * @param array<string, mixed> $params
     */
    private static function safeLog(
        string $method,
        array $params,
        mixed $response,
        float $durationMs,
        ?string $error,
        string $category,
        ?API $api = null,
    ): void {
        try {
            MtProtoLogger::log($method, $params, $response, $durationMs, $error, $category, $api);
        } catch (\Throwable) {
            // ignore
        }
    }

    /**
     * Состояние авторизации без инициализации MadelineProto (для страниц UI).
     *
     * @return array{logged_in: bool, session_id: string|null}
     */
    public static function getLoginState(): array
    {
        return [
            'logged_in' => !empty($_SESSION['telegram_logged_in']),
            'session_id' => $_SESSION['telegram_session_id'] ?? null,
        ];
    }

    /**
     * Проверка статуса авторизации (для API — с реальным подключением).
     */
    public static function isLoggedIn(): bool
    {
        if (empty($_SESSION['telegram_logged_in'])) {
            return false;
        }

        try {
            $api = self::getApi();
            $loggedIn = (bool) $api->getSelf();
            if (!$loggedIn) {
                $_SESSION['telegram_logged_in'] = false;
            }
            return $loggedIn;
        } catch (\Throwable) {
            $_SESSION['telegram_logged_in'] = false;
            return false;
        }
    }

    /**
     * Получить информацию о текущем пользователе.
     *
     * @return array<string, mixed>|null
     */
    public static function getSelf(): ?array
    {
        try {
            $self = self::getApi()->getSelf();
            return is_array($self) ? $self : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Начать авторизацию по номеру телефона.
     */
    public static function phoneLogin(string $phone): array
    {
        $api = self::getApi();
        $start = microtime(true);

        try {
            $result = $api->phoneLogin($phone);
            $duration = (microtime(true) - $start) * 1000;
            self::safeLog('auth.phoneLogin', ['phone' => $phone], $result, $duration, null, 'auth', $api);
            return ['status' => 'code_required', 'result' => $result];
        } catch (\Throwable $e) {
            $duration = (microtime(true) - $start) * 1000;
            self::safeLog('auth.phoneLogin', ['phone' => $phone], null, $duration, $e->getMessage(), 'auth', $api);
            throw $e;
        }
    }

    /**
     * Отправить код подтверждения.
     */
    public static function completePhoneLogin(string $code): array
    {
        $api = self::getApi();
        $start = microtime(true);

        try {
            $result = $api->completePhoneLogin($code);

            if ($result['_'] === 'account.password') {
                $duration = (microtime(true) - $start) * 1000;
                self::safeLog('auth.completePhoneLogin', [], $result, $duration, null, 'auth', $api);
                return ['status' => '2fa_required'];
            }

            $duration = (microtime(true) - $start) * 1000;
            self::safeLog('auth.completePhoneLogin', [], $result, $duration, null, 'auth', $api);
            $_SESSION['telegram_logged_in'] = true;
            return ['status' => 'ok', 'user' => self::getSelf()];
        } catch (\Throwable $e) {
            $duration = (microtime(true) - $start) * 1000;
            self::safeLog('auth.completePhoneLogin', [], null, $duration, $e->getMessage(), 'auth', $api);
            throw $e;
        }
    }

    /**
     * Ввод пароля 2FA.
     */
    public static function complete2faLogin(string $password): array
    {
        $api = self::getApi();
        $start = microtime(true);

        try {
            $result = $api->complete2faLogin($password);
            $duration = (microtime(true) - $start) * 1000;
            self::safeLog('auth.complete2faLogin', [], $result, $duration, null, 'auth', $api);
            $_SESSION['telegram_logged_in'] = true;
            return ['status' => 'ok', 'user' => self::getSelf()];
        } catch (\Throwable $e) {
            $duration = (microtime(true) - $start) * 1000;
            self::safeLog('auth.complete2faLogin', [], null, $duration, $e->getMessage(), 'auth', $api);
            throw $e;
        }
    }

    /**
     * Выход из аккаунта (Telegram + локальные файлы сессии).
     */
    public static function logout(): void
    {
        $sessionId = self::sanitizeSessionId($_SESSION['telegram_session_id'] ?? 'default');

        try {
            $api = self::getApi($sessionId);
            $api->logout();
            self::safeLog('auth.logout', [], ['ok' => true], 0, null, 'auth', $api);
        } catch (\Throwable $e) {
            self::safeLog('auth.logout', [], null, 0, $e->getMessage(), 'auth', self::$api);
        }

        self::resetApi();
        self::destroySessionFiles($sessionId);
        unset($_SESSION['telegram_session_id'], $_SESSION['telegram_logged_in']);
    }

    /**
     * Принудительный сброс сессии без вызова Telegram API (смена аккаунта).
     */
    public static function forceResetSession(?string $sessionId = null): bool
    {
        $sessionId = self::sanitizeSessionId($sessionId ?? ($_SESSION['telegram_session_id'] ?? 'default'));

        if (self::$sessionFile === self::sessionBasePath($sessionId)) {
            self::resetApi();
        }

        $deleted = self::destroySessionFiles($sessionId);

        if (self::sanitizeSessionId($_SESSION['telegram_session_id'] ?? 'default') === $sessionId) {
            unset($_SESSION['telegram_session_id'], $_SESSION['telegram_logged_in']);
        }

        return $deleted;
    }

    /**
     * Проверка: на диске есть сессия MadelineProto.
     */
    public static function sessionExists(?string $sessionId = null): bool
    {
        $base = self::sessionBasePath(self::sanitizeSessionId($sessionId ?? ($_SESSION['telegram_session_id'] ?? 'default')));
        if (file_exists($base)) {
            return true;
        }

        $dir = Bootstrap::config('app')['paths']['sessions'];
        $id = self::sanitizeSessionId($sessionId ?? 'default');

        return !empty(glob($dir . '/' . $id . '.madeline*'));
    }

    /**
     * MadelineProto на диске уже авторизован (независимо от PHP-сессии).
     */
    public static function isMadelineLoggedIn(?string $sessionId = null): bool
    {
        if (!self::sessionExists($sessionId)) {
            return false;
        }

        try {
            return (bool) self::getApi($sessionId)->getSelf();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Список всех сессий MadelineProto.
     *
     * @return list<array<string, mixed>>
     */
    public static function listSessions(): array
    {
        $dir = Bootstrap::config('app')['paths']['sessions'];
        $sessions = [];
        $seen = [];

        foreach (glob($dir . '/*.madeline', GLOB_ONLYDIR) ?: [] as $path) {
            $id = basename($path, '.madeline');
            $seen[$id] = true;
            $sessions[] = self::buildSessionInfo($id, $path);
        }

        // Старый формат: один файл *.madeline
        foreach (glob($dir . '/*.madeline') ?: [] as $path) {
            if (is_dir($path)) {
                continue;
            }
            $id = basename($path, '.madeline');
            if (isset($seen[$id])) {
                continue;
            }
            $sessions[] = self::buildSessionInfo($id, $path);
        }

        usort($sessions, static fn(array $a, array $b): int => strcmp($a['id'], $b['id']));

        return $sessions;
    }

    /**
     * @return array<string, mixed>
     */
    private static function buildSessionInfo(string $id, string $path): array
    {
        $activeId = self::sanitizeSessionId($_SESSION['telegram_session_id'] ?? 'default');

        return [
            'id' => $id,
            'size' => self::pathSize($path),
            'modified' => date('Y-m-d H:i:s', filemtime($path)),
            'active' => $activeId === $id,
            'logged_in' => is_file($path . '/lightState.php') || is_dir($path . '/safe.php'),
        ];
    }

    /**
     * Сбросить кэш API (после смены прокси и т.д.).
     */
    public static function resetApi(): void
    {
        self::$api = null;
        self::$sessionFile = null;
    }

    /**
     * Удалить сессию с диска.
     */
    public static function deleteSession(string $sessionId): bool
    {
        $sessionId = self::sanitizeSessionId($sessionId);

        if (self::$sessionFile === self::sessionBasePath($sessionId)) {
            self::resetApi();
        }

        return self::destroySessionFiles($sessionId);
    }

    private static function sanitizeSessionId(string $sessionId): string
    {
        $id = preg_replace('/[^a-zA-Z0-9_-]/', '', $sessionId);

        return $id !== '' ? $id : 'default';
    }

    private static function sessionBasePath(string $sessionId): string
    {
        return Bootstrap::config('app')['paths']['sessions'] . '/' . self::sanitizeSessionId($sessionId) . '.madeline';
    }

    /**
     * MadelineProto 8 хранит сессию как директорию + lock/ipc файлы.
     */
    private static function destroySessionFiles(string $sessionId): bool
    {
        $dir = Bootstrap::config('app')['paths']['sessions'];
        $id = self::sanitizeSessionId($sessionId);
        $base = self::sessionBasePath($id);
        $found = false;

        if (file_exists($base)) {
            self::removePath($base);
            $found = true;
        }

        foreach (glob($dir . '/' . $id . '.madeline*') ?: [] as $extra) {
            if (file_exists($extra)) {
                self::removePath($extra);
                $found = true;
            }
        }

        foreach (glob($dir . '/*') ?: [] as $item) {
            $name = basename($item);
            if (preg_match('/\.(ipc|lock)$/', $name) && str_contains($name, $id)) {
                self::removePath($item);
                $found = true;
            }
        }

        return $found;
    }

    private static function removePath(string $path): void
    {
        if (!file_exists($path)) {
            return;
        }

        if (is_dir($path)) {
            $items = scandir($path);
            if ($items !== false) {
                foreach ($items as $item) {
                    if ($item === '.' || $item === '..') {
                        continue;
                    }
                    self::removePath($path . '/' . $item);
                }
            }
            @rmdir($path);
            return;
        }

        @unlink($path);
    }

    private static function pathSize(string $path): int
    {
        if (is_file($path)) {
            return (int) (filesize($path) ?: 0);
        }

        if (!is_dir($path)) {
            return 0;
        }

        $size = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            if ($file->isFile()) {
                $size += (int) $file->getSize();
            }
        }

        return $size;
    }
}
