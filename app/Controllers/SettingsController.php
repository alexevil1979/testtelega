<?php

/**
 * Настройки приложения, управление сессиями.
 */

declare(strict_types=1);

namespace App\Controllers;

use App\Bootstrap;
use App\Database;
use App\Services\ProxyConfig;
use App\Services\TelegramService;
use App\View;

final class SettingsController extends BaseController
{
    public function page(): void
    {
        View::render('pages/settings', [
            'title' => 'Настройки',
            'page' => 'settings',
            'sessions' => TelegramService::listSessions(),
            'proxy' => ProxyConfig::get(),
        ]);
    }

    public function get(): void
    {
        try {
            $pdo = Database::connection();
            $stmt = $pdo->query('SELECT `key`, `value` FROM app_settings');
            $settings = [];
            while ($row = $stmt->fetch()) {
                $settings[$row['key']] = json_decode($row['value'], true) ?? $row['value'];
            }

            View::json(['settings' => $settings]);
        } catch (\Throwable $e) {
            View::json(['settings' => []]);
        }
    }

    public function save(): void
    {
        $data = $this->getJsonBody();

        try {
            $pdo = Database::connection();
            $stmt = $pdo->prepare(
                'INSERT INTO app_settings (`key`, `value`) VALUES (:key, :value)
                 ON DUPLICATE KEY UPDATE `value` = :value2'
            );

            foreach ($data as $key => $value) {
                $json = json_encode($value, JSON_UNESCAPED_UNICODE);
                $stmt->execute(['key' => $key, 'value' => $json, 'value2' => $json]);
            }

            // Сброс API при смене прокси
            if (isset($data['proxy_url']) || isset($data['http_api_proxy_url']) || isset($data['proxy_enabled'])) {
                TelegramService::resetApi();
            }

            View::json(['status' => 'ok']);
        } catch (\Throwable $e) {
            View::json(['error' => $e->getMessage()], 500);
        }
    }

    public function clearCache(): void
    {
        try {
            $pdo = Database::connection();
            $pdo->exec('DELETE FROM chat_cache WHERE updated_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)');

            $cacheDir = Bootstrap::config('app')['paths']['logs'] . '/rate_limit';
            if (is_dir($cacheDir)) {
                foreach (glob($cacheDir . '/*.json') as $file) {
                    unlink($file);
                }
            }

            View::json(['status' => 'ok']);
        } catch (\Throwable $e) {
            View::json(['error' => $e->getMessage()], 500);
        }
    }

    public function sessions(): void
    {
        View::json(['sessions' => TelegramService::listSessions()]);
    }

    public function deleteSession(): void
    {
        $data = $this->getJsonBody();
        $sessionId = $data['session_id'] ?? '';

        if (empty($sessionId)) {
            View::json(['error' => 'Session ID required'], 400);
            return;
        }

        $deleted = TelegramService::deleteSession($sessionId);
        View::json(['status' => $deleted ? 'ok' : 'not_found']);
    }
}
