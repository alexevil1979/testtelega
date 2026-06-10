<?php

/**
 * Логирование MTProto-обмена: отправлено/принято (parsed + raw hex).
 */

declare(strict_types=1);

namespace App\Services;

use App\Bootstrap;
use App\Database;
use danog\MadelineProto\API;

final class MtProtoLogger
{
    /**
     * @param array<string, mixed> $params
     */
    public static function log(
        string $method,
        array $params,
        mixed $response,
        float $durationMs,
        ?string $error = null,
        string $category = 'general',
        ?API $api = null,
    ): void {
        $config = Bootstrap::config('app');
        if (!$config['mtproto_log']['enabled']) {
            return;
        }

        $api ??= self::safeApi();

        $request = $api
            ? MtProtoExchangeCapture::request($api, $method, $params)
            : ['parsed' => self::sanitize($params), 'raw' => ['encoding' => 'unavailable']];

        $responseBlock = null;
        if ($response !== null && $api) {
            $responseBlock = MtProtoExchangeCapture::response($api, $method, $response);
        } elseif ($response !== null) {
            $responseBlock = [
                'parsed' => self::sanitize(self::normalizeSimple($response)),
                'raw' => ['encoding' => 'json_compact', 'json_compact' => json_encode($response, JSON_UNESCAPED_UNICODE)],
            ];
        }

        $entry = [
            'method' => $method,
            'direction' => 'rpc',
            'request' => [
                'parsed' => self::sanitize($request['parsed']),
                'raw' => $request['raw'],
            ],
            'response' => $responseBlock ? [
                'parsed' => self::sanitize($responseBlock['parsed']),
                'raw' => $responseBlock['raw'],
            ] : null,
            // обратная совместимость для старого UI/экспорта
            'params' => self::sanitize($request['parsed']),
            'response' => $responseBlock ? self::sanitize($responseBlock['parsed']) : null,
            'duration_ms' => round($durationMs, 2),
            'error' => $error,
            'category' => $category,
            'session_id' => $_SESSION['telegram_session_id'] ?? null,
            'created_at' => date('Y-m-d H:i:s'),
        ];

        $entry = self::attachPayloadFile($entry, $config);

        self::writeJsonl($entry, $config);
        self::writeDatabase($entry);
    }

    private static function safeApi(): ?API
    {
        try {
            return TelegramService::getApi();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param array<string, mixed> $entry
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    private static function attachPayloadFile(array $entry, array $config): array
    {
        $fullLog = $config['mtproto_log']['full_payload'] ?? true;
        if (!$fullLog) {
            $entry['request']['parsed'] = self::summarize($entry['request']['parsed']);
            if ($entry['response']) {
                $entry['response']['parsed'] = self::summarize($entry['response']['parsed']);
            }
            $entry['params'] = $entry['request']['parsed'];
            $entry['response'] = $entry['response']['parsed'] ?? null;

            return $entry;
        }

        $encoded = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        $maxInline = (int) ($config['mtproto_log']['max_inline_bytes'] ?? 2_000_000);

        if ($encoded !== false && strlen($encoded) <= $maxInline) {
            return $entry;
        }

        $dir = $config['paths']['logs'] . '/exchange/' . date('Y-m-d');
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $id = bin2hex(random_bytes(8));
        $path = $dir . '/' . $id . '.json';
        file_put_contents($path, json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE), LOCK_EX);

        $entry['payload_file'] = $path;
        $entry['payload_size'] = strlen($encoded ?: '');
        $entry['request']['parsed'] = self::summarize($entry['request']['parsed']);
        $entry['response']['parsed'] = $entry['response'] ? self::summarize($entry['response']['parsed']) : null;
        $entry['params'] = $entry['request']['parsed'];
        $entry['response'] = $entry['response']['parsed'] ?? null;
        // raw hex остаётся в файле; в stream — флаг
        unset($entry['request']['raw'], $entry['response']['raw']);

        return $entry;
    }

    /**
     * @param array<string, mixed> $entry
     * @param array<string, mixed> $config
     */
    private static function writeJsonl(array $entry, array $config): void
    {
        $logFile = $config['paths']['logs'] . '/mtproto_' . date('Y-m-d') . '.jsonl';
        file_put_contents(
            $logFile,
            json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) . "\n",
            FILE_APPEND | LOCK_EX
        );
    }

    /**
     * @param array<string, mixed> $entry
     */
    private static function writeDatabase(array $entry): void
    {
        try {
            $pdo = Database::connection();
            $stmt = $pdo->prepare(
                'INSERT INTO mtproto_logs (method, params, response, duration_ms, error, category, session_id, created_at, exchange)
                 VALUES (:method, :params, :response, :duration_ms, :error, :category, :session_id, :created_at, :exchange)'
            );
            $stmt->execute([
                'method' => $entry['method'],
                'params' => json_encode($entry['params'], JSON_UNESCAPED_UNICODE),
                'response' => json_encode($entry['response'], JSON_UNESCAPED_UNICODE),
                'duration_ms' => $entry['duration_ms'],
                'error' => $entry['error'],
                'category' => $entry['category'],
                'session_id' => $entry['session_id'],
                'created_at' => $entry['created_at'],
                'exchange' => json_encode([
                    'request' => $entry['request'] ?? null,
                    'response' => $entry['response'] ?? null,
                    'payload_file' => $entry['payload_file'] ?? null,
                    'payload_size' => $entry['payload_size'] ?? null,
                ], JSON_UNESCAPED_UNICODE),
            ]);
        } catch (\Throwable) {
            // fallback без колонки exchange
            try {
                $pdo = Database::connection();
                $stmt = $pdo->prepare(
                    'INSERT INTO mtproto_logs (method, params, response, duration_ms, error, category, session_id, created_at)
                     VALUES (:method, :params, :response, :duration_ms, :error, :category, :session_id, :created_at)'
                );
                $stmt->execute([
                    'method' => $entry['method'],
                    'params' => json_encode($entry, JSON_UNESCAPED_UNICODE),
                    'response' => null,
                    'duration_ms' => $entry['duration_ms'],
                    'error' => $entry['error'],
                    'category' => $entry['category'],
                    'session_id' => $entry['session_id'],
                    'created_at' => $entry['created_at'],
                ]);
            } catch (\Throwable) {
            }
        }
    }

    /**
     * Загрузить полную запись по id (из БД + файла).
     *
     * @return array<string, mixed>|null
     */
    public static function loadEntry(int $id): ?array
    {
        try {
            $pdo = Database::connection();
            $stmt = $pdo->prepare('SELECT * FROM mtproto_logs WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $id]);
            $row = $stmt->fetch();
            if (!$row) {
                return null;
            }

            return self::hydrateRow($row);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function hydrateRow(array $row): array
    {
        $exchange = json_decode($row['exchange'] ?? '{}', true);
        if (!is_array($exchange)) {
            $exchange = [];
        }

        $entry = [
            'id' => $row['id'],
            'method' => $row['method'],
            'category' => $row['category'],
            'duration_ms' => $row['duration_ms'],
            'error' => $row['error'],
            'session_id' => $row['session_id'],
            'created_at' => $row['created_at'],
            'request' => $exchange['request'] ?? ['parsed' => json_decode($row['params'] ?? '{}', true)],
            'response' => $exchange['response'] ?? ['parsed' => json_decode($row['response'] ?? 'null', true)],
            'params' => json_decode($row['params'] ?? '{}', true),
            'response_legacy' => json_decode($row['response'] ?? 'null', true),
            'payload_file' => $exchange['payload_file'] ?? null,
            'payload_size' => $exchange['payload_size'] ?? null,
        ];

        if (!empty($entry['payload_file']) && is_file($entry['payload_file'])) {
            $full = json_decode((string) file_get_contents($entry['payload_file']), true);
            if (is_array($full)) {
                return array_merge($entry, $full, ['id' => $row['id']]);
            }
        }

        // Полная jsonl-запись могла содержать raw inline
        if (empty($entry['request']['raw'])) {
            $entry['request']['raw'] = ['encoding' => 'inline_missing', 'note' => 'См. payload_file или новые записи'];
        }

        return $entry;
    }

    private static function sanitize(mixed $data): mixed
    {
        if (!is_array($data)) {
            return $data;
        }

        $sensitive = ['password', 'phone_code_hash', 'phone_code', 'token', 'secret', 'session_password'];
        $result = [];

        foreach ($data as $key => $value) {
            if (in_array(strtolower((string) $key), $sensitive, true)) {
                $result[$key] = '***REDACTED***';
            } elseif (is_array($value)) {
                $result[$key] = self::sanitize($value);
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    private static function normalizeSimple(mixed $data): mixed
    {
        if (!is_array($data)) {
            return $data;
        }

        return $data;
    }

    private static function summarize(mixed $data): mixed
    {
        if (!is_array($data)) {
            return $data;
        }

        $encoded = json_encode($data, JSON_UNESCAPED_UNICODE);
        if ($encoded !== false && strlen($encoded) <= 32_000) {
            return $data;
        }

        if (isset($data['dialogs']) && is_array($data['dialogs'])) {
            return [
                '_' => $data['_'] ?? null,
                'summary' => true,
                'dialogs_count' => count($data['dialogs']),
                'messages_count' => count($data['messages'] ?? []),
                'users_count' => count($data['users'] ?? []),
                'chats_count' => count($data['chats'] ?? []),
            ];
        }

        if (isset($data['messages']) && is_array($data['messages'])) {
            return [
                '_' => $data['_'] ?? null,
                'summary' => true,
                'messages_count' => count($data['messages']),
            ];
        }

        return [
            'summary' => true,
            'keys' => array_keys($data),
            'json_bytes' => strlen($encoded ?: ''),
        ];
    }
}
