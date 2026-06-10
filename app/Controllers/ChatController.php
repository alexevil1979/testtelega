<?php

/**
 * Диалоги, чаты, сообщения.
 */

declare(strict_types=1);

namespace App\Controllers;

use App\Services\TelegramService;
use App\View;

final class ChatController extends BaseController
{
    public function page(): void
    {
        View::render('pages/chats', [
            'title' => 'Диалоги и чаты',
            'page' => 'chats',
            'isLoggedIn' => TelegramService::getLoginState()['logged_in'],
        ]);
    }

    public function list(): void
    {
        $this->requireAuth();

        try {
            $dialogs = TelegramService::call('messages.getDialogs', [
                'offset_date' => 0,
                'offset_id' => 0,
                'offset_peer' => ['_' => 'inputPeerEmpty'],
                'limit' => (int) ($_GET['limit'] ?? 50),
                'hash' => 0,
            ], 'messages');

            View::json(['dialogs' => $dialogs]);
        } catch (\Throwable $e) {
            View::json(['error' => $e->getMessage()], 500);
        }
    }

    public function messages(string $id): void
    {
        $this->requireAuth();

        try {
            $peer = $this->resolvePeer($id);
            $limit = (int) ($_GET['limit'] ?? 50);
            $offsetId = (int) ($_GET['offset_id'] ?? 0);

            $history = TelegramService::call('messages.getHistory', [
                'peer' => $peer,
                'offset_id' => $offsetId,
                'offset_date' => 0,
                'add_offset' => 0,
                'limit' => $limit,
                'max_id' => 0,
                'min_id' => 0,
                'hash' => 0,
            ], 'messages');

            View::json(['history' => $history]);
        } catch (\Throwable $e) {
            View::json(['error' => $e->getMessage()], 500);
        }
    }

    public function send(string $id): void
    {
        $this->requireAuth();
        $data = $this->getJsonBody();
        $message = trim($data['message'] ?? '');

        if (empty($message)) {
            View::json(['error' => 'Message required'], 400);
            return;
        }

        try {
            $peer = $this->resolvePeer($id);
            $params = [
                'peer' => $peer,
                'message' => $message,
                'random_id' => random_int(1, PHP_INT_MAX),
            ];

            if (!empty($data['reply_to'])) {
                $params['reply_to'] = ['_' => 'inputReplyToMessage', 'reply_to_msg_id' => (int) $data['reply_to']];
            }

            $result = TelegramService::call('messages.sendMessage', $params, 'messages');
            View::json(['result' => $result]);
        } catch (\Throwable $e) {
            View::json(['error' => $e->getMessage()], 500);
        }
    }

    public function edit(string $id): void
    {
        $this->requireAuth();
        $data = $this->getJsonBody();

        try {
            $peer = $this->resolvePeer($id);
            $result = TelegramService::call('messages.editMessage', [
                'peer' => $peer,
                'id' => (int) ($data['msg_id'] ?? 0),
                'message' => $data['message'] ?? '',
            ], 'messages');

            View::json(['result' => $result]);
        } catch (\Throwable $e) {
            View::json(['error' => $e->getMessage()], 500);
        }
    }

    public function delete(string $id): void
    {
        $this->requireAuth();
        $data = $this->getJsonBody();
        $msgIds = $data['msg_ids'] ?? [];

        try {
            $peer = $this->resolvePeer($id);
            $result = TelegramService::call('messages.deleteMessages', [
                'revoke' => true,
                'id' => array_map('intval', $msgIds),
            ], 'messages');

            View::json(['result' => $result]);
        } catch (\Throwable $e) {
            View::json(['error' => $e->getMessage()], 500);
        }
    }

    public function forward(string $id): void
    {
        $this->requireAuth();
        $data = $this->getJsonBody();

        try {
            $fromPeer = $this->resolvePeer($data['from_peer'] ?? $id);
            $toPeer = $this->resolvePeer($data['to_peer'] ?? $id);

            $result = TelegramService::call('messages.forwardMessages', [
                'from_peer' => $fromPeer,
                'id' => array_map('intval', $data['msg_ids'] ?? []),
                'to_peer' => $toPeer,
                'random_id' => [random_int(1, PHP_INT_MAX)],
            ], 'messages');

            View::json(['result' => $result]);
        } catch (\Throwable $e) {
            View::json(['error' => $e->getMessage()], 500);
        }
    }

    public function pin(string $id): void
    {
        $this->requireAuth();
        $data = $this->getJsonBody();

        try {
            $peer = $this->resolvePeer($id);
            $method = ($data['unpin'] ?? false) ? 'messages.unpinAllMessages' : 'messages.updatePinnedMessage';

            $params = ['peer' => $peer];
            if ($method === 'messages.updatePinnedMessage') {
                $params['id'] = (int) ($data['msg_id'] ?? 0);
                $params['unpin'] = false;
            }

            $result = TelegramService::call($method, $params, 'messages');
            View::json(['result' => $result]);
        } catch (\Throwable $e) {
            View::json(['error' => $e->getMessage()], 500);
        }
    }

    public function upload(string $id): void
    {
        $this->requireAuth();

        if (empty($_FILES['file'])) {
            View::json(['error' => 'File required'], 400);
            return;
        }

        try {
            $api = TelegramService::getApi();
            $file = $_FILES['file'];
            $peer = $this->resolvePeer($id);

            // Загрузка файла через MadelineProto
            $uploaded = $api->upload($file['tmp_name'], $file['name']);

            $caption = $_POST['caption'] ?? '';
            $result = TelegramService::call('messages.sendMedia', [
                'peer' => $peer,
                'media' => $uploaded,
                'message' => $caption,
                'random_id' => random_int(1, PHP_INT_MAX),
            ], 'messages');

            View::json(['result' => $result]);
        } catch (\Throwable $e) {
            View::json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Преобразовать ID чата в inputPeer.
     *
     * @return array<string, mixed>
     */
    private function resolvePeer(string $id): array
    {
        // Формат: user_123, chat_456, channel_789
        if (str_starts_with($id, 'user_')) {
            return ['_' => 'inputPeerUser', 'user_id' => (int) substr($id, 5), 'access_hash' => (int) ($_GET['access_hash'] ?? 0)];
        }
        if (str_starts_with($id, 'chat_')) {
            return ['_' => 'inputPeerChat', 'chat_id' => (int) substr($id, 5)];
        }
        if (str_starts_with($id, 'channel_')) {
            return ['_' => 'inputPeerChannel', 'channel_id' => (int) substr($id, 8), 'access_hash' => (int) ($_GET['access_hash'] ?? 0)];
        }

        // Числовой ID — пробуем как user
        return ['_' => 'inputPeerUser', 'user_id' => (int) $id, 'access_hash' => 0];
    }
}
