<?php

declare(strict_types=1);

/**
 * CORS para php -S (localhost) y preflight OPTIONS.
 * En producción (sin Origin local) no abre el API a terceros.
 *
 * @return void
 */
function crm_cors_headers()
{
    $origin = isset($_SERVER['HTTP_ORIGIN']) ? (string) $_SERVER['HTTP_ORIGIN'] : '';
    $esLocal = $origin !== '' && (bool) preg_match(
        '#^https?://(localhost|127\.0\.0\.1)(:[0-9]+)?$#',
        $origin
    );

    if ($esLocal) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Credentials: true');
        header('Vary: Origin');
    }

    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Accept, X-HTTP-Method-Override');
    header('Access-Control-Max-Age: 600');
}

/**
 * @return void
 */
function crm_cors_preflight()
{
    $method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper((string) $_SERVER['REQUEST_METHOD']) : 'GET';
    if ($method !== 'OPTIONS') {
        return;
    }
    crm_cors_headers();
    http_response_code(204);
    exit;
}
