<?php

/**
 * Универсальные действия и RPC-вызовы MTProto.
 */

declare(strict_types=1);

namespace App\Controllers;

use App\Services\TelegramService;
use App\View;

final class ApiController extends BaseController
{
    public function createGroup(): void
    {
        $this->requireAuth();
        $data = $this->getJsonBody();

        try {
            $result = TelegramService::call('messages.createChat', [
                'users' => $data['users'] ?? [],
                'title' => $data['title'] ?? 'New Group',
            ], 'chats');

            View::json(['result' => $result]);
        } catch (\Throwable $e) {
            View::json(['error' => $e->getMessage()], 500);
        }
    }

    public function createChannel(): void
    {
        $this->requireAuth();
        $data = $this->getJsonBody();

        try {
            $result = TelegramService::call('channels.createChannel', [
                'title' => $data['title'] ?? 'New Channel',
                'about' => $data['about'] ?? '',
                'megagroup' => $data['megagroup'] ?? false,
                'broadcast' => $data['broadcast'] ?? true,
            ], 'chats');

            View::json(['result' => $result]);
        } catch (\Throwable $e) {
            View::json(['error' => $e->getMessage()], 500);
        }
    }

    public function invite(): void
    {
        $this->requireAuth();
        $data = $this->getJsonBody();

        try {
            $result = TelegramService::call('channels.inviteToChannel', [
                'channel' => $this->resolveChannel($data['channel_id'] ?? 0, $data['access_hash'] ?? 0),
                'users' => $data['users'] ?? [],
            ], 'chats');

            View::json(['result' => $result]);
        } catch (\Throwable $e) {
            View::json(['error' => $e->getMessage()], 500);
        }
    }

    public function kick(): void
    {
        $this->requireAuth();
        $data = $this->getJsonBody();

        try {
            $result = TelegramService::call('channels.editBanned', [
                'channel' => $this->resolveChannel($data['channel_id'] ?? 0, $data['access_hash'] ?? 0),
                'participant' => ['_' => 'inputPeerUser', 'user_id' => (int) ($data['user_id'] ?? 0), 'access_hash' => (int) ($data['user_access_hash'] ?? 0)],
                'banned_rights' => [
                    '_' => 'chatBannedRights',
                    'view_messages' => true,
                    'until_date' => 0,
                ],
            ], 'chats');

            View::json(['result' => $result]);
        } catch (\Throwable $e) {
            View::json(['error' => $e->getMessage()], 500);
        }
    }

    public function block(): void
    {
        $this->requireAuth();
        $data = $this->getJsonBody();

        try {
            $result = TelegramService::call('contacts.block', [
                'id' => ['_' => 'inputPeerUser', 'user_id' => (int) ($data['user_id'] ?? 0), 'access_hash' => (int) ($data['access_hash'] ?? 0)],
            ], 'users');

            View::json(['result' => $result]);
        } catch (\Throwable $e) {
            View::json(['error' => $e->getMessage()], 500);
        }
    }

    public function unblock(): void
    {
        $this->requireAuth();
        $data = $this->getJsonBody();

        try {
            $result = TelegramService::call('contacts.unblock', [
                'id' => ['_' => 'inputPeerUser', 'user_id' => (int) ($data['user_id'] ?? 0), 'access_hash' => (int) ($data['access_hash'] ?? 0)],
            ], 'users');

            View::json(['result' => $result]);
        } catch (\Throwable $e) {
            View::json(['error' => $e->getMessage()], 500);
        }
    }

    public function updates(): void
    {
        $this->requireAuth();

        try {
            $api = TelegramService::getApi();
            $updates = $api->getUpdates(['limit' => (int) ($_GET['limit'] ?? 10)]);
            View::json(['updates' => $updates]);
        } catch (\Throwable $e) {
            View::json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Универсальный RPC-вызов любого MTProto-метода (для отладки).
     */
    public function rpc(): void
    {
        $this->requireAuth();
        $data = $this->getJsonBody();
        $method = $data['method'] ?? '';
        $params = $data['params'] ?? [];
        $category = $data['category'] ?? 'rpc';

        if (empty($method)) {
            View::json(['error' => 'Method required'], 400);
            return;
        }

        try {
            $result = TelegramService::call($method, $params, $category);
            View::json(['result' => $result]);
        } catch (\Throwable $e) {
            View::json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveChannel(int $channelId, int $accessHash): array
    {
        return ['_' => 'inputPeerChannel', 'channel_id' => $channelId, 'access_hash' => $accessHash];
    }
}
