<?php

/**
 * CSRF-защита для POST/PUT/DELETE запросов.
 */

declare(strict_types=1);

namespace App\Middleware;

use App\Bootstrap;
use App\View;

final class CsrfMiddleware
{
    public static function token(): string
    {
        $name = Bootstrap::config('app')['security']['csrf_token_name'];

        if (empty($_SESSION[$name])) {
            $_SESSION[$name] = bin2hex(random_bytes(32));
        }

        return $_SESSION[$name];
    }

    public static function handle(): void
    {
        $name = Bootstrap::config('app')['security']['csrf_token_name'];
        $token = $_SERVER['HTTP_X_CSRF_TOKEN']
            ?? $_POST[$name]
            ?? null;

        if (!$token || !hash_equals(self::token(), $token)) {
            View::json(['error' => 'Invalid CSRF token'], 403);
            exit;
        }
    }
}
