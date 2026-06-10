<?php

/**
 * Парсинг и применение прокси для MadelineProto (SOCKS5 / HTTP).
 */

declare(strict_types=1);

namespace App\Services;

use App\Bootstrap;
use App\Database;
use danog\MadelineProto\Settings;
use danog\MadelineProto\Stream\Proxy\HttpProxy;
use danog\MadelineProto\Stream\Proxy\SocksProxy;

final class ProxyConfig
{
    /**
     * Получить настройки прокси: сначала из БД, затем из .env.
     *
     * @return array{enabled: bool, mtproto_url: string, http_api_url: string}
     */
    public static function get(): array
    {
        $defaults = Bootstrap::config('app')['proxy'];

        try {
            $pdo = Database::connection();
            $stmt = $pdo->query(
                "SELECT `key`, `value` FROM app_settings WHERE `key` IN ('proxy_enabled', 'proxy_url', 'http_api_proxy_url')"
            );
            $db = [];
            while ($row = $stmt->fetch()) {
                $db[$row['key']] = json_decode($row['value'], true) ?? $row['value'];
            }

            if (isset($db['proxy_enabled'])) {
                $defaults['enabled'] = (bool) $db['proxy_enabled'];
            }
            if (!empty($db['proxy_url'])) {
                $defaults['mtproto_url'] = (string) $db['proxy_url'];
            }
            if (!empty($db['http_api_proxy_url'])) {
                $defaults['http_api_url'] = (string) $db['http_api_proxy_url'];
            }
        } catch (\Throwable) {
            // БД недоступна — используем .env
        }

        return $defaults;
    }

    /**
     * Применить прокси к настройкам MadelineProto.
     */
    public static function applyToSettings(Settings $settings): void
    {
        $proxy = self::get();

        if (!$proxy['enabled']) {
            return;
        }

        $connection = $settings->getConnection();
        $connection->clearProxies();

        if (!empty($proxy['mtproto_url'])) {
            self::addProxyFromUrl($connection, $proxy['mtproto_url']);
        }

        if (!empty($proxy['http_api_url']) && $proxy['http_api_url'] !== $proxy['mtproto_url']) {
            self::addProxyFromUrl($connection, $proxy['http_api_url']);
        }
    }

    /**
     * @return array{address: string, port: int, username?: string, password?: string}|null
     */
    public static function parseUrl(string $url): ?array
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }

        $parsed = parse_url($url);
        if (!$parsed || empty($parsed['host'])) {
            return null;
        }

        $result = [
            'address' => $parsed['host'],
            'port' => (int) ($parsed['port'] ?? 1080),
        ];

        if (!empty($parsed['user'])) {
            $result['username'] = $parsed['user'];
        }
        if (!empty($parsed['pass'])) {
            $result['password'] = $parsed['pass'];
        }

        return $result;
    }

    /**
     * Добавить прокси по URL (socks5://, socks4://, http://).
     */
    private static function addProxyFromUrl(\danog\MadelineProto\Settings\Connection $connection, string $url): void
    {
        $parsed = self::parseUrl($url);
        if (!$parsed) {
            return;
        }

        $scheme = strtolower((string) (parse_url($url, PHP_URL_SCHEME) ?? 'socks5'));

        $proxyClass = match ($scheme) {
            'http', 'https' => HttpProxy::class,
            'socks5', 'socks4', 'socks' => SocksProxy::class,
            default => SocksProxy::class,
        };

        $connection->addProxy($proxyClass, $parsed);
    }
}
