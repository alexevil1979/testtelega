<?php

/**
 * Rate limiting по IP-адресу (файловый кэш).
 */

declare(strict_types=1);

namespace App\Middleware;

use App\Bootstrap;
use App\View;

final class RateLimitMiddleware
{
    public static function handle(): void
    {
        $config = Bootstrap::config('app')['security'];
        $maxRequests = $config['rate_limit_requests'];
        $window = $config['rate_limit_window'];

        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $cacheDir = Bootstrap::config('app')['paths']['logs'] . '/rate_limit';
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0750, true);
        }

        $file = $cacheDir . '/' . md5($ip) . '.json';
        $now = time();
        $data = ['count' => 0, 'reset' => $now + $window];

        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true) ?: $data;
        }

        if ($now > $data['reset']) {
            $data = ['count' => 0, 'reset' => $now + $window];
        }

        $data['count']++;

        if ($data['count'] > $maxRequests) {
            View::json(['error' => 'Rate limit exceeded'], 429);
            exit;
        }

        file_put_contents($file, json_encode($data), LOCK_EX);
    }
}
