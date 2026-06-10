<?php

/**
 * Рендеринг PHP-шаблонов с layout.
 */

declare(strict_types=1);

namespace App;

use App\Middleware\CsrfMiddleware;

final class View
{
    /**
     * @param array<string, mixed> $data
     */
    public static function render(string $template, array $data = [], string $layout = 'layouts/main'): void
    {
        $config = Bootstrap::config('app');
        $data['app'] = $config;
        $data['csrf_token'] = CsrfMiddleware::token();

        extract($data);

        ob_start();
        require Bootstrap::rootPath() . '/app/Views/' . $template . '.php';
        $content = ob_get_clean();

        require Bootstrap::rootPath() . '/app/Views/' . $layout . '.php';
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function json(array $data, int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
}
