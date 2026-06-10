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

        $settings = new Settings();
        $settings->getAppInfo()
            ->setApiId(Bootstrap::config('app')['telegram']['api_id'])
            ->setApiHash(Bootstrap::config('app')['telegram']['api_hash']);

        // SOCKS5/HTTP прокси для MTProto (всегда из .env или настроек UI)
        ProxyConfig::applyToSettings($settings);

        // Логирование MadelineProto в наш файл
        $settings->getLogger()
            ->setLevel(\danog\MadelineProto\Logger::LEVEL_FATAL);

        self::$api = new API($sessionPath, $settings);
        self::$sessionFile = $sessionPath;
        $_SESSION['telegram_session_id'] = $sessionId;

        return self::$api;
    }

    /**
     * Вызов MTProto-метода с логированием.
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
            $response = $api->methodCallAsyncRead($method, $params);
        } catch (\Throwable $e) {
            $error = $e->getMessage();
            $duration = (microtime(true) - $start) * 1000;
            MtProtoLogger::log($method, $params, null, $duration, $error, $category);
            throw $e;
        }

        $duration = (microtime(true) - $start) * 1000;
        MtProtoLogger::log($method, $params, $response, $duration, null, $category);

        return $response;
    }

    /**
     * Проверка статуса авторизации.
     */
    public static function isLoggedIn(): bool
    {
        try {
            $api = self::getApi();
            return (bool) $api->getSelf();
        } catch (\Throwable) {
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
            MtProtoLogger::log('auth.phoneLogin', ['phone' => $phone], $result, $duration, null, 'auth');
            return ['status' => 'code_required', 'result' => $result];
        } catch (\Throwable $e) {
            $duration = (microtime(true) - $start) * 1000;
            MtProtoLogger::log('auth.phoneLogin', ['phone' => $phone], null, $duration, $e->getMessage(), 'auth');
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
                MtProtoLogger::log('auth.completePhoneLogin', [], $result, $duration, null, 'auth');
                return ['status' => '2fa_required'];
            }

            $duration = (microtime(true) - $start) * 1000;
            MtProtoLogger::log('auth.completePhoneLogin', [], $result, $duration, null, 'auth');
            return ['status' => 'ok', 'user' => self::getSelf()];
        } catch (\Throwable $e) {
            $duration = (microtime(true) - $start) * 1000;
            MtProtoLogger::log('auth.completePhoneLogin', [], null, $duration, $e->getMessage(), 'auth');
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
            MtProtoLogger::log('auth.complete2faLogin', [], $result, $duration, null, 'auth');
            return ['status' => 'ok', 'user' => self::getSelf()];
        } catch (\Throwable $e) {
            $duration = (microtime(true) - $start) * 1000;
            MtProtoLogger::log('auth.complete2faLogin', [], null, $duration, $e->getMessage(), 'auth');
            throw $e;
        }
    }

    /**
     * Выход из аккаунта.
     */
    public static function logout(): void
    {
        try {
            self::getApi()->logout();
            MtProtoLogger::log('auth.logout', [], ['ok' => true], 0, null, 'auth');
        } catch (\Throwable $e) {
            MtProtoLogger::log('auth.logout', [], null, 0, $e->getMessage(), 'auth');
        }

        self::$api = null;
        self::$sessionFile = null;
        unset($_SESSION['telegram_session_id']);
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
