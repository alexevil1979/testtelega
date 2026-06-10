<?php

/**
 * Маршруты приложения.
 * Формат: 'METHOD /path' => [Controller::class, 'method']
 */

declare(strict_types=1);

use App\Controllers\ApiController;
use App\Controllers\AuthController;
use App\Controllers\ChatController;
use App\Controllers\ContactController;
use App\Controllers\DashboardController;
use App\Controllers\DiagController;
use App\Controllers\LoggerController;
use App\Controllers\SettingsController;

return [
    // Страницы
    'GET /' => [DashboardController::class, 'index'],
    'GET /auth' => [AuthController::class, 'page'],
    'GET /chats' => [ChatController::class, 'page'],
    'GET /contacts' => [ContactController::class, 'page'],
    'GET /actions' => [DashboardController::class, 'actions'],
    'GET /logger' => [LoggerController::class, 'page'],
    'GET /settings' => [SettingsController::class, 'page'],

    // API — Авторизация
    'POST /api/auth/phone' => [AuthController::class, 'sendPhone'],
    'POST /api/auth/code' => [AuthController::class, 'sendCode'],
    'POST /api/auth/2fa' => [AuthController::class, 'send2fa'],
    'POST /api/auth/logout' => [AuthController::class, 'logout'],
    'GET /api/auth/status' => [AuthController::class, 'status'],
    'GET /api/diag/madeline' => [DiagController::class, 'madeline'],
    'GET /api/auth/me' => [AuthController::class, 'me'],

    // API — Диалоги и чаты
    'GET /api/chats' => [ChatController::class, 'list'],
    'GET /api/chats/{id}/messages' => [ChatController::class, 'messages'],
    'POST /api/chats/{id}/send' => [ChatController::class, 'send'],
    'POST /api/chats/{id}/edit' => [ChatController::class, 'edit'],
    'POST /api/chats/{id}/delete' => [ChatController::class, 'delete'],
    'POST /api/chats/{id}/forward' => [ChatController::class, 'forward'],
    'POST /api/chats/{id}/pin' => [ChatController::class, 'pin'],
    'POST /api/chats/{id}/upload' => [ChatController::class, 'upload'],

    // API — Контакты
    'GET /api/contacts' => [ContactController::class, 'list'],
    'GET /api/users/search' => [ContactController::class, 'search'],
    'POST /api/contacts/add' => [ContactController::class, 'add'],
    'GET /api/users/{id}' => [ContactController::class, 'info'],

    // API — Действия
    'POST /api/actions/create-group' => [ApiController::class, 'createGroup'],
    'POST /api/actions/create-channel' => [ApiController::class, 'createChannel'],
    'POST /api/actions/invite' => [ApiController::class, 'invite'],
    'POST /api/actions/kick' => [ApiController::class, 'kick'],
    'POST /api/actions/block' => [ApiController::class, 'block'],
    'POST /api/actions/unblock' => [ApiController::class, 'unblock'],
    'GET /api/updates' => [ApiController::class, 'updates'],

    // API — Логгер
    'GET /api/logger/stream' => [LoggerController::class, 'stream'],
    'GET /api/logger/list' => [LoggerController::class, 'list'],
    'GET /api/logger/export' => [LoggerController::class, 'export'],
    'POST /api/logger/clear' => [LoggerController::class, 'clear'],

    // API — Настройки
    'GET /api/settings' => [SettingsController::class, 'get'],
    'POST /api/settings' => [SettingsController::class, 'save'],
    'POST /api/settings/clear-cache' => [SettingsController::class, 'clearCache'],
    'GET /api/settings/sessions' => [SettingsController::class, 'sessions'],
    'POST /api/settings/sessions/delete' => [SettingsController::class, 'deleteSession'],

    // Универсальный RPC-вызов (для тестирования любого метода MTProto)
    'POST /api/rpc' => [ApiController::class, 'rpc'],
];
