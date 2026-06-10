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
            MtProtoLogger::log($method, $params, null, $duration, $error, $category, $api);
            throw $e;
        }

        $duration = (microtime(true) - $start) * 1000;
        MtProtoLogger::log($method, $params, $response, $duration, null, $category, $api);

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
            MtProtoLogger::log('auth.phoneLogin', ['phone' => $phone], $result, $duration, null, 'auth', $api);
            return ['status' => 'code_required', 'result' => $result];
        } catch (\Throwable $e) {
            $duration = (microtime(true) - $start) * 1000;
            MtProtoLogger::log('auth.phoneLogin', ['phone' => $phone], null, $duration, $e->getMessage(), 'auth', $api);
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
                MtProtoLogger::log('auth.completePhoneLogin', [], $result, $duration, null, 'auth', $api);
                return ['status' => '2fa_required'];
            }

            $duration = (microtime(true) - $start) * 1000;
            MtProtoLogger::log('auth.completePhoneLogin', [], $result, $duration, null, 'auth', $api);
            $_SESSION['telegram_logged_in'] = true;
            return ['status' => 'ok', 'user' => self::getSelf()];
        } catch (\Throwable $e) {
            $duration = (microtime(true) - $start) * 1000;
            MtProtoLogger::log('auth.completePhoneLogin', [], null, $duration, $e->getMessage(), 'auth', $api);
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
            MtProtoLogger::log('auth.complete2faLogin', [], $result, $duration, null, 'auth', $api);
            $_SESSION['telegram_logged_in'] = true;
            return ['status' => 'ok', 'user' => self::getSelf()];
        } catch (\Throwable $e) {
            $duration = (microtime(true) - $start) * 1000;
            MtProtoLogger::log('auth.complete2faLogin', [], null, $duration, $e->getMessage(), 'auth', $api);
            throw $e;
        }
    }

    /**
     * Выход из аккаунта.
     */
    public static function logout(): void
    {
        try {
            $api = self::getApi();
            $api->logout();
            MtProtoLogger::log('auth.logout', [], ['ok' => true], 0, null, 'auth', $api);
        } catch (\Throwable $e) {
            MtProtoLogger::log('auth.logout', [], null, 0, $e->getMessage(), 'auth', self::$api);
        }

        self::$api = null;
        self::$sessionFile = null;
        unset($_SESSION['telegram_session_id'], $_SESSION['telegram_logged_in']);
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

        foreach (glob($dir . '/*.madeline') as $file) {
            $sessions[] = [
                'id' => basename($file, '.madeline'),
                'size' => filesize($file),
                'modified' => date('Y-m-d H:i:s', filemtime($file)),
                'active' => ($_SESSION['telegram_session_id'] ?? '') === basename($file, '.madeline'),
            ];
        }

        return $sessions;
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
     * Удалить сессию.
     */
    public static function deleteSession(string $sessionId): bool
    {
        $path = Bootstrap::config('app')['paths']['sessions'] . '/' . preg_replace('/[^a-zA-Z0-9_-]/', '', $sessionId) . '.madeline';
        if (!file_exists($path)) {
            return false;
        }

        // Удаляем связанные файлы MadelineProto
        foreach (glob($path . '*') as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        return true;
    }
}
