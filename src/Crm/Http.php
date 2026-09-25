<?php

declare(strict_types=1);

namespace Crm;

final class Http
{
    public static function noCacheHeaders(): void
    {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');
    }

    public static function jsonHeaders(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        self::noCacheHeaders();
        if (function_exists('crm_cors_headers')) {
            crm_cors_headers();
        }
    }

    /** @return array<string, mixed> */
    public static function body(): array
    {
        $raw = file_get_contents('php://input');
        if (!is_string($raw)) {
            $raw = '';
        }
        if ($raw !== '') {
            $data = json_decode($raw, true);
            if (is_array($data)) {
                /** @var array<string, mixed> $data */
                return $data;
            }
        }
        return is_array($_POST) ? $_POST : [];
    }

    public static function method(): string
    {
        $override = $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] ?? '';
        if (is_string($override) && $override !== '') {
            return strtoupper($override);
        }
        $fromBody = self::body()['_method'] ?? null;
        if (is_string($fromBody) && $fromBody !== '') {
            return strtoupper($fromBody);
        }
        return strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    }

    /** @param array<string, mixed> $data */
    public static function payloadOk(array $data = []): array
    {
        return ['ok' => true, 'success' => true] + $data;
    }

    /** @param array<string, mixed> $extra */
    public static function payloadFail(string $message, array $extra = []): array
    {
        return ['ok' => false, 'success' => false, 'error' => $message] + $extra;
    }

    /** @param array<string, mixed> $data */
    public static function json(array $data, int $code = 200): never
    {
        http_response_code($code);
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /** @param array<string, mixed> $extra */
    public static function fail(string $message, int $code = 400, array $extra = []): never
    {
        throw new ApiException($message, $code, $extra);
    }

    /** @param array<string, mixed> $data */
    public static function ok(array $data = [], int $code = 200): never
    {
        self::json(self::payloadOk($data), $code);
    }

    public static function handle(callable $handler): never
    {
        self::jsonHeaders();
        try {
            $result = $handler();
            if (is_array($result)) {
                self::ok($result);
            }
            self::ok();
        } catch (ApiException $e) {
            self::json(self::payloadFail($e->getMessage(), $e->extra), $e->status);
        } catch (\PDOException $e) {
            self::rollbackQuietly();
            $msg = crm_debug() ? ('Error de base de datos: ' . $e->getMessage()) : 'Error de base de datos';
            self::json(self::payloadFail($msg), 500);
        } catch (\Throwable $e) {
            self::rollbackQuietly();
            $msg = crm_debug() ? $e->getMessage() : 'Error interno';
            self::json(self::payloadFail($msg), 500);
        }
    }

    private static function rollbackQuietly(): void
    {
        try {
            $pdo = \Crm\Database\Connection::appOrNull();
            if ($pdo instanceof \PDO && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
        } catch (\Throwable) {
        }
    }
}
