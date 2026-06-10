<?php

/**
 * Базовый контроллер с общими методами.
 */

declare(strict_types=1);

namespace App\Controllers;

use App\Services\TelegramService;
use App\View;

abstract class BaseController
{
    /**
     * Проверить авторизацию Telegram (для API).
     */
    protected function requireAuth(): void
    {
        if (!TelegramService::isLoggedIn()) {
            View::json(['error' => 'Telegram authorization required'], 401);
            exit;
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function getJsonBody(): array
    {
        $body = file_get_contents('php://input');
        return json_decode($body, true) ?: [];
    }
}
