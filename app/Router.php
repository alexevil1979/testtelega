<?php

/**
 * Простой маршрутизатор с поддержкой параметров {id}.
 */

declare(strict_types=1);

namespace App;

use App\Middleware\CsrfMiddleware;
use App\Middleware\RateLimitMiddleware;

final class Router
{
    /** @var array<string, array{0: class-string, 1: string}> */
    private array $routes;

    public function __construct()
    {
        $this->routes = require Bootstrap::rootPath() . '/config/routes.php';
    }

    public function dispatch(string $method, string $uri): void
    {
        $uri = parse_url($uri, PHP_URL_PATH) ?: '/';
        $uri = rtrim($uri, '/') ?: '/';

        // Middleware для API-запросов
        if (str_starts_with($uri, '/api/')) {
            RateLimitMiddleware::handle();
            if ($method === 'POST' || $method === 'PUT' || $method === 'DELETE') {
                CsrfMiddleware::handle();
            }
        }

        foreach ($this->routes as $pattern => $handler) {
            [$routeMethod, $routePath] = explode(' ', $pattern, 2);

            if ($routeMethod !== $method) {
                continue;
            }

            $params = $this->matchRoute($routePath, $uri);
            if ($params === null) {
                continue;
            }

            [$controllerClass, $action] = $handler;
            $controller = new $controllerClass();

            call_user_func_array([$controller, $action], $params);
            return;
        }

        http_response_code(404);
        if (str_starts_with($uri, '/api/')) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Not found']);
        } else {
            View::render('pages/404', ['title' => '404']);
        }
    }

    /**
     * @return array<string, string>|null
     */
    private function matchRoute(string $routePath, string $uri): ?array
    {
        $pattern = preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $routePath);
        $pattern = '#^' . $pattern . '$#';

        if (!preg_match($pattern, $uri, $matches)) {
            return null;
        }

        $params = [];
        foreach ($matches as $key => $value) {
            if (is_string($key)) {
                $params[$key] = $value;
            }
        }

        return array_values($params);
    }
}
