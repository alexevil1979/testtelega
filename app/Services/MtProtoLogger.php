<?php

/**
 * Логирование всех MTProto-вызовов в MySQL и файл.
 * Используется как обёртка вокруг MadelineProto API.
 */

declare(strict_types=1);

namespace App\Services;

use App\Bootstrap;
use App\Database;

final class MtProtoLogger
{
    /**
     * Записать вызов MTProto-метода.
     *
     * @param array<string, mixed> $params
     * @param mixed $response
     */
    public static function log(
        string $method,
        array $params,
        mixed $response,
        float $durationMs,
        ?string $error = null,
        string $category = 'general'
    ): void {
        $config = Bootstrap::config('app');
        if (!$config['mtproto_log']['enabled']) {
            return;
        }

        $entry = [
            'method' => $method,
            'params' => self::sanitize($params),
            'response' => self::sanitize($response),
            'duration_ms' => round($durationMs, 2),
            'error' => $error,
            'category' => $category,
            'session_id' => $_SESSION['telegram_session_id'] ?? null,
            'created_at' => date('Y-m-d H:i:s'),
        ];

        // Запись в файл (для realtime stream)
        $logFile = $config['paths']['logs'] . '/mtproto_' . date('Y-m-d') . '.jsonl';
        file_put_contents($logFile, json_encode($entry, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX);

        // Запись в MySQL
        try {
            $pdo = Database::connection();
            $stmt = $pdo->prepare(
                'INSERT INTO mtproto_logs (method, params, response, duration_ms, error, category, session_id, created_at)
                 VALUES (:method, :params, :response, :duration_ms, :error, :category, :session_id, :created_at)'
            );
            $stmt->execute([
                'method' => $method,
                'params' => json_encode($entry['params'], JSON_UNESCAPED_UNICODE),
                'response' => json_encode($entry['response'], JSON_UNESCAPED_UNICODE),
                'duration_ms' => $entry['duration_ms'],
                'error' => $error,
                'category' => $category,
                'session_id' => $entry['session_id'],
                'created_at' => $entry['created_at'],
            ]);
        } catch (\Throwable) {
            // БД может быть недоступна при первом запуске — не блокируем работу
        }
    }

    /**
     * Удалить чувствительные данные из логов.
     */
    private static function sanitize(mixed $data): mixed
    {
        if (!is_array($data)) {
            return $data;
        }

        $sensitive = ['password', 'phone_code_hash', 'phone_code', 'token', 'secret'];
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
}
