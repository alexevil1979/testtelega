<?php

/**
 * API Логгер / Отладка MTProto-вызовов.
 */

declare(strict_types=1);

namespace App\Controllers;

use App\Bootstrap;
use App\Database;
use App\View;

final class LoggerController extends BaseController
{
    public function page(): void
    {
        View::render('pages/logger', [
            'title' => 'API Логгер',
            'page' => 'logger',
        ]);
    }

    /**
     * Server-Sent Events для realtime-логов.
     */
    public function stream(): void
    {
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');

        $logFile = Bootstrap::config('app')['paths']['logs'] . '/mtproto_' . date('Y-m-d') . '.jsonl';
        $lastPos = (int) ($_GET['pos'] ?? 0);

        if (!file_exists($logFile)) {
            echo "data: " . json_encode(['type' => 'ping']) . "\n\n";
            flush();
            return;
        }

        $fp = fopen($logFile, 'r');
        if ($fp === false) {
            return;
        }

        fseek($fp, $lastPos);

        $timeout = 30;
        $start = time();

        while (time() - $start < $timeout) {
            $line = fgets($fp);
            if ($line !== false) {
                $entry = json_decode(trim($line), true);
                if ($entry) {
                    // Фильтр по категории
                    $category = $_GET['category'] ?? '';
                    if ($category && ($entry['category'] ?? '') !== $category) {
                        continue;
                    }

                    echo "data: " . json_encode($entry, JSON_UNESCAPED_UNICODE) . "\n\n";
                    flush();
                }
            } else {
                // Нет новых данных — ждём
                usleep(500000);
                clearstatcache(true, $logFile);

                // Проверяем, не пересоздан ли файл
                if (filesize($logFile) < $lastPos) {
                    fseek($fp, 0);
                    $lastPos = 0;
                }
            }
        }

        echo "data: " . json_encode(['type' => 'reconnect', 'pos' => ftell($fp)]) . "\n\n";
        flush();
        fclose($fp);
    }

    public function list(): void
    {
        $limit = min((int) ($_GET['limit'] ?? 100), 500);
        $offset = (int) ($_GET['offset'] ?? 0);
        $category = $_GET['category'] ?? '';
        $method = $_GET['method'] ?? '';

        try {
            $pdo = Database::connection();
            $where = ['1=1'];
            $params = [];

            if ($category) {
                $where[] = 'category = :category';
                $params['category'] = $category;
            }
            if ($method) {
                $where[] = 'method LIKE :method';
                $params['method'] = '%' . $method . '%';
            }

            $sql = 'SELECT * FROM mtproto_logs WHERE ' . implode(' AND ', $where)
                . ' ORDER BY id DESC LIMIT :limit OFFSET :offset';

            $stmt = $pdo->prepare($sql);
            foreach ($params as $k => $v) {
                $stmt->bindValue($k, $v);
            }
            $stmt->bindValue('limit', $limit, \PDO::PARAM_INT);
            $stmt->bindValue('offset', $offset, \PDO::PARAM_INT);
            $stmt->execute();

            $logs = $stmt->fetchAll();

            // Декодируем JSON-поля
            foreach ($logs as &$log) {
                $log['params'] = json_decode($log['params'] ?? '{}', true);
                $log['response'] = json_decode($log['response'] ?? 'null', true);
            }

            View::json(['logs' => $logs, 'count' => count($logs)]);
        } catch (\Throwable $e) {
            View::json(['error' => $e->getMessage(), 'logs' => []], 500);
        }
    }

    public function export(): void
    {
        $format = $_GET['format'] ?? 'json';

        try {
            $pdo = Database::connection();
            $stmt = $pdo->query('SELECT * FROM mtproto_logs ORDER BY id DESC LIMIT 10000');
            $logs = $stmt->fetchAll();

            if ($format === 'csv') {
                header('Content-Type: text/csv');
                header('Content-Disposition: attachment; filename="mtproto_logs_' . date('Y-m-d') . '.csv"');
                echo "id,method,category,duration_ms,error,created_at\n";
                foreach ($logs as $log) {
                    echo implode(',', [
                        $log['id'],
                        '"' . str_replace('"', '""', $log['method']) . '"',
                        $log['category'],
                        $log['duration_ms'],
                        '"' . str_replace('"', '""', $log['error'] ?? '') . '"',
                        $log['created_at'],
                    ]) . "\n";
                }
            } else {
                header('Content-Type: application/json');
                header('Content-Disposition: attachment; filename="mtproto_logs_' . date('Y-m-d') . '.json"');
                echo json_encode($logs, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            }
        } catch (\Throwable $e) {
            View::json(['error' => $e->getMessage()], 500);
        }
    }

    public function clear(): void
    {
        try {
            $pdo = Database::connection();
            $pdo->exec('TRUNCATE TABLE mtproto_logs');

            // Очистка файловых логов
            $logDir = Bootstrap::config('app')['paths']['logs'];
            foreach (glob($logDir . '/mtproto_*.jsonl') as $file) {
                unlink($file);
            }

            View::json(['status' => 'ok']);
        } catch (\Throwable $e) {
            View::json(['error' => $e->getMessage()], 500);
        }
    }
}
