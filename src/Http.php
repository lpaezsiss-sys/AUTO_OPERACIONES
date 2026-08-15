<?php

declare(strict_types=1);

namespace Crm;

final class Http
{
    public static function jsonHeaders()
    {
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store');
        if (function_exists('crm_cors_headers')) {
            crm_cors_headers();
        }
    }

    /**
     * @return array
     */
    public static function body()
    {
        $raw = file_get_contents('php://input');
        if (!is_string($raw)) {
            $raw = '';
        }
        if ($raw !== '') {
            $data = json_decode($raw, true);
            if (is_array($data)) {
                return $data;
            }
        }
        return is_array($_POST) ? $_POST : array();
    }

    /**
     * @return string
     */
    public static function method()
    {
        $override = isset($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE']) ? $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] : '';
        if (is_string($override) && $override !== '') {
            return strtoupper($override);
        }
        $body = self::body();
        $fromBody = isset($body['_method']) ? $body['_method'] : null;
        if (is_string($fromBody) && $fromBody !== '') {
            return strtoupper($fromBody);
        }
        return strtoupper((string) (isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET'));
    }

    /**
     * @return int
     */
    public static function idParam()
    {
        if (isset($_GET['id'])) {
            return (int) $_GET['id'];
        }
        $uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
        $path = (string) parse_url((string) $uri, PHP_URL_PATH);
        if (preg_match('#/(\d+)/?$#', $path, $m)) {
            return (int) $m[1];
        }
        return 0;
    }

    /**
     * @param array $data
     * @param int $code
     * @return void
     */
    public static function json(array $data, $code = 200)
    {
        http_response_code((int) $code);
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * @param string $message
     * @param int $code
     * @param array $extra
     * @return void
     */
    public static function fail($message, $code = 400, array $extra = array())
    {
        throw new ApiException($message, (int) $code, $extra);
    }

    /**
     * @param array $data
     * @param int $code
     * @return void
     */
    public static function ok(array $data = array(), $code = 200)
    {
        self::json(array('ok' => true) + $data, (int) $code);
    }

    /**
     * @param callable $handler
     * @return void
     */
    public static function handle($handler)
    {
        self::jsonHeaders();
        try {
            $result = call_user_func($handler);
            if (is_array($result)) {
                self::ok($result);
            }
            self::ok();
        } catch (ApiException $e) {
            self::json(array('ok' => false, 'error' => $e->getMessage()) + $e->extra, $e->status);
        } catch (\PDOException $e) {
            $pdo = crm_pdo();
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $payload = array('ok' => false, 'error' => 'Error de base de datos');
            if (crm_debug()) {
                $payload['detail'] = $e->getMessage();
            }
            self::json($payload, 500);
        }
    }
}
