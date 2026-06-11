<?php

/**
 * Авторизация Telegram: телефон, код, 2FA.
 */

declare(strict_types=1);

namespace App\Controllers;

use App\Services\TelegramService;
use App\View;

final class AuthController extends BaseController
{
    public function page(): void
    {
        $state = TelegramService::getLoginState();

        View::render('pages/auth', [
            'title' => 'Авторизация',
            'page' => 'auth',
            'isLoggedIn' => $state['logged_in'],
        ]);
    }

    public function sendPhone(): void
    {
        $data = $this->getJsonBody();
        $phone = trim($data['phone'] ?? '');

        if (empty($phone)) {
            View::json(['error' => 'Phone number required'], 400);
            return;
        }

        // Опционально: новая сессия
        if (!empty($data['session_id'])) {
            $_SESSION['telegram_session_id'] = preg_replace('/[^a-zA-Z0-9_-]/', '', $data['session_id']);
        }

        try {
            $result = TelegramService::phoneLogin($phone);
            View::json($result);
        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            if (stripos($msg, 'already logged') !== false || stripos($msg, 'уже залогинен') !== false) {
                View::json([
                    'error' => $msg,
                    'code' => 'already_logged_in',
                ], 409);
                return;
            }
            View::json(['error' => $msg], 500);
        }
    }

    public function sendCode(): void
    {
        $data = $this->getJsonBody();
        $code = trim($data['code'] ?? '');

        if (empty($code)) {
            View::json(['error' => 'Code required'], 400);
            return;
        }

        try {
            $result = TelegramService::completePhoneLogin($code);
            View::json($result);
        } catch (\Throwable $e) {
            View::json(['error' => $e->getMessage()], 500);
        }
    }

    public function send2fa(): void
    {
        $data = $this->getJsonBody();
        $password = $data['password'] ?? '';

        if (empty($password)) {
            View::json(['error' => '2FA password required'], 400);
            return;
        }

        try {
            $result = TelegramService::complete2faLogin($password);
            View::json($result);
        } catch (\Throwable $e) {
            View::json(['error' => $e->getMessage()], 500);
        }
    }

    public function logout(): void
    {
        TelegramService::logout();
        View::json(['status' => 'ok']);
    }

    public function resetSession(): void
    {
        $data = $this->getJsonBody();
        $sessionId = $data['session_id'] ?? ($_SESSION['telegram_session_id'] ?? 'default');

        $deleted = TelegramService::forceResetSession($sessionId);
        View::json([
            'status' => $deleted ? 'ok' : 'not_found',
            'session_id' => preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $sessionId) ?: 'default',
        ]);
    }

    public function status(): void
    {
        $sessionId = $_SESSION['telegram_session_id'] ?? 'default';
        $phpLoggedIn = !empty($_SESSION['telegram_logged_in']);

        // Не держать lock сессии во время медленного MadelineProto
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $madelineLoggedIn = TelegramService::isMadelineLoggedIn($sessionId);
        $loggedIn = $phpLoggedIn && $madelineLoggedIn;

        View::json([
            'logged_in' => $loggedIn,
            'session_id' => $sessionId,
            'madeline_logged_in' => $madelineLoggedIn,
            'session_exists' => TelegramService::sessionExists($sessionId),
        ]);
    }

    public function me(): void
    {
        if (!TelegramService::isLoggedIn()) {
            View::json(['error' => 'Not logged in'], 401);
            return;
        }

        View::json(['user' => TelegramService::getSelf()]);
    }
}
