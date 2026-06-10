<?php

/**
 * Захват MTProto-обмена: распарсенный TL и сырой бинарник (hex).
 */

declare(strict_types=1);

namespace App\Services;

use danog\MadelineProto\API;

final class MtProtoExchangeCapture
{
    /**
     * @param array<string, mixed> $params
     * @return array{parsed: mixed, raw: array<string, mixed>}
     */
    public static function request(API $api, string $method, array $params): array
    {
        $parsed = self::normalize($params);

        return [
            'parsed' => $parsed,
            'raw' => self::serializeRequestRaw($api, $method, $params, $parsed),
        ];
    }

    /**
     * @return array{parsed: mixed, raw: array<string, mixed>}
     */
    public static function response(API $api, string $method, mixed $response): array
    {
        $parsed = self::normalize($response);

        return [
            'parsed' => $parsed,
            'raw' => self::serializeResponseRaw($api, $method, $response, $parsed),
        ];
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private static function serializeRequestRaw(API $api, string $method, array $params, mixed $parsed): array
    {
        $raw = [
            'encoding' => 'mtproto_tl',
            'method' => $method,
            'json_compact' => self::compactJson($parsed),
            'hex' => null,
            'size_bytes' => 0,
        ];

        try {
            $bytes = $api->getTL()->serializeMethod($method, self::normalizeParamsForSerialize($method, $params));
            $raw['hex'] = bin2hex($bytes);
            $raw['size_bytes'] = strlen($bytes);
        } catch (\Throwable $e) {
            $raw['encoding'] = 'json_fallback';
            $raw['error'] = $e->getMessage();
        }

        return $raw;
    }

    private static function serializeResponseRaw(API $api, string $method, mixed $response, mixed $parsed): array
    {
        $raw = [
            'encoding' => 'mtproto_tl',
            'method' => $method,
            'json_compact' => self::compactJson($parsed),
            'hex' => null,
            'size_bytes' => 0,
        ];

        if (!is_array($response)) {
            $raw['encoding'] = 'scalar';
            $raw['value'] = $response;

            return $raw;
        }

        try {
            $bytes = self::serializeResponseBytes($api, $method, $response);
            $raw['hex'] = bin2hex($bytes);
            $raw['size_bytes'] = strlen($bytes);
        } catch (\Throwable $e) {
            $raw['encoding'] = 'json_fallback';
            $raw['error'] = $e->getMessage();
        }

        return $raw;
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private static function normalizeParamsForSerialize(string $method, array $params): array
    {
        if ($method === 'messages.getDialogs' && ($params['hash'] ?? null) === []) {
            $params['hash'] = 0;
        }

        return $params;
    }

    /**
     * @param array<string, mixed> $response
     */
    private static function serializeResponseBytes(API $api, string $method, array $response): string
    {
        $methodInfo = $api->getTL()->getMethods()->findByMethod($method);
        if ($methodInfo && !empty($methodInfo['type'])) {
            try {
                return $api->getTL()->serializeObject(
                    ['type' => $methodInfo['type']],
                    $response,
                    $method
                );
            } catch (\Throwable) {
                // fallback ниже
            }
        }

        $predicate = $response['_'] ?? null;
        if (!is_string($predicate) || $predicate === '') {
            throw new \RuntimeException('Unknown method return type');
        }

        return $api->getTL()->serializeObject(
            ['type' => $predicate],
            $response,
            $predicate
        );
    }

    private static function compactJson(mixed $data): ?string
    {
        if ($data === null) {
            return null;
        }

        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($json === false) {
            return null;
        }

        return $json;
    }

    private static function normalize(mixed $data): mixed
    {
        if ($data === null || is_scalar($data)) {
            return $data;
        }

        if (!is_array($data)) {
            return (string) $data;
        }

        return self::deepNormalize($data);
    }

    /**
     * @param array<mixed> $data
     * @return array<mixed>
     */
    private static function deepNormalize(array $data): array
    {
        $out = [];
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $out[$key] = self::deepNormalize($value);
            } elseif (is_object($value)) {
                $out[$key] = method_exists($value, 'jsonSerialize')
                    ? $value->jsonSerialize()
                    : (string) $value;
            } else {
                $out[$key] = $value;
            }
        }

        return $out;
    }
}
