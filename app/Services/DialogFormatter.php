<?php

/**
 * Нормализация диалогов MadelineProto 8.x для UI.
 * peer может быть int|string (bot API id), не только peerUser/peerChannel.
 */

declare(strict_types=1);

namespace App\Services;

use danog\MadelineProto\API;

final class DialogFormatter
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function fromGetDialogsResponse(API $api, array $raw, int $limit = 100): array
    {
        if (($raw['_'] ?? '') === 'messages.dialogsNotModified') {
            $raw = TelegramService::call('messages.getDialogs', [
                'offset_date' => 0,
                'offset_id' => 0,
                'offset_peer' => ['_' => 'inputPeerEmpty'],
                'limit' => $limit,
                'hash' => [],
            ], 'messages');
        }

        $items = [];
        foreach ($raw['dialogs'] ?? [] as $dialog) {
            $formatted = self::formatDialog($api, $dialog);
            if ($formatted !== null) {
                $items[] = $formatted;
            }
        }

        return $items;
    }

    /**
     * @param array<string, mixed> $dialog
     * @return array<string, mixed>|null
     */
    public static function formatDialog(API $api, array $dialog): ?array
    {
        $peer = $dialog['peer'] ?? null;
        if ($peer === null) {
            return null;
        }

        try {
            $info = $api->getInfo($peer);
        } catch (\Throwable) {
            return null;
        }

        if (!is_array($info)) {
            return null;
        }

        return [
            'id' => (string) ($info['bot_api_id'] ?? ''),
            'type' => (string) ($info['type'] ?? 'unknown'),
            'title' => self::titleFromInfo($info),
            'access_hash' => self::accessHashFromInfo($info),
            'unread_count' => (int) ($dialog['unread_count'] ?? 0),
            'top_message' => (int) ($dialog['top_message'] ?? 0),
        ];
    }

    /**
     * @param array<string, mixed> $info
     */
    public static function titleFromInfo(array $info): string
    {
        if (!empty($info['User'])) {
            $u = $info['User'];
            $name = trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''));
            if (!empty($u['username'])) {
                $name = trim($name . ' (@' . $u['username'] . ')');
            }
            if ($name !== '') {
                return $name;
            }
        }

        if (!empty($info['Chat']['title'])) {
            return (string) $info['Chat']['title'];
        }

        if (!empty($info['Channel']['title'])) {
            return (string) $info['Channel']['title'];
        }

        $id = $info['bot_api_id'] ?? '?';
        $type = $info['type'] ?? 'peer';

        return ucfirst((string) $type) . ' ' . $id;
    }

    /**
     * @param array<string, mixed> $info
     */
    public static function accessHashFromInfo(array $info): int
    {
        return (int) (
            $info['User']['access_hash']
            ?? $info['Channel']['access_hash']
            ?? $info['Chat']['access_hash']
            ?? 0
        );
    }

    /**
     * inputPeer для messages.getHistory / sendMessage.
     *
     * @return array<string, mixed>
     */
    public static function resolveInputPeer(API $api, string $id): array
    {
        $id = trim($id);
        if ($id === '') {
            throw new \InvalidArgumentException('Empty peer id');
        }

        // legacy: user_123, channel_456
        if (preg_match('/^(user|chat|channel)_(\d+)$/', $id, $m)) {
            $num = (int) $m[2];
            $id = match ($m[1]) {
                'channel' => (string) (-1_000_000_000_000 - $num),
                'chat' => (string) (-$num),
                default => (string) $num,
            };
        }

        $peer = $api->getInfo(
            str_contains($id, '-') ? (int) $id : $id,
            API::INFO_TYPE_PEER
        );

        if (!is_array($peer)) {
            throw new \RuntimeException('Cannot resolve peer: ' . $id);
        }

        return $peer;
    }
}
