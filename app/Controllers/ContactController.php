<?php

/**
 * Контакты и поиск пользователей.
 */

declare(strict_types=1);

namespace App\Controllers;

use App\Services\TelegramService;
use App\View;

final class ContactController extends BaseController
{
    public function page(): void
    {
        View::render('pages/contacts', [
            'title' => 'Контакты',
            'page' => 'contacts',
            'isLoggedIn' => TelegramService::getLoginState()['logged_in'],
        ]);
    }

    public function list(): void
    {
        $this->requireAuth();

        try {
            $contacts = TelegramService::call('contacts.getContacts', ['hash' => 0], 'users');
            View::json(['contacts' => $contacts]);
        } catch (\Throwable $e) {
            View::json(['error' => $e->getMessage()], 500);
        }
    }

    public function search(): void
    {
        $this->requireAuth();
        $q = trim($_GET['q'] ?? '');

        if (empty($q)) {
            View::json(['error' => 'Query required'], 400);
            return;
        }

        try {
            $result = TelegramService::call('contacts.search', [
                'q' => $q,
                'limit' => (int) ($_GET['limit'] ?? 20),
            ], 'users');

            View::json(['result' => $result]);
        } catch (\Throwable $e) {
            View::json(['error' => $e->getMessage()], 500);
        }
    }

    public function add(): void
    {
        $this->requireAuth();
        $data = $this->getJsonBody();

        try {
            $result = TelegramService::call('contacts.importContacts', [
                'contacts' => [[
                    '_' => 'inputPhoneContact',
                    'client_id' => random_int(1, PHP_INT_MAX),
                    'phone' => $data['phone'] ?? '',
                    'first_name' => $data['first_name'] ?? '',
                    'last_name' => $data['last_name'] ?? '',
                ]],
            ], 'users');

            View::json(['result' => $result]);
        } catch (\Throwable $e) {
            View::json(['error' => $e->getMessage()], 500);
        }
    }

    public function info(string $id): void
    {
        $this->requireAuth();

        try {
            $result = TelegramService::call('users.getFullUser', [
                'id' => ['_' => 'inputUser', 'user_id' => (int) $id, 'access_hash' => (int) ($_GET['access_hash'] ?? 0)],
            ], 'users');

            View::json(['user' => $result]);
        } catch (\Throwable $e) {
            View::json(['error' => $e->getMessage()], 500);
        }
    }
}
