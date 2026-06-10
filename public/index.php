<?php

/**
 * Точка входа TestTelega — Telegram Web Client / Tester.
 * DocumentRoot Apache: /ssd/www/testtelega/public
 */

declare(strict_types=1);

$rootPath = dirname(__DIR__);

require_once $rootPath . '/app/Bootstrap.php';

use App\Bootstrap;
use App\Router;

Bootstrap::init($rootPath);

$router = new Router();
$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
