<?php

declare(strict_types=1);

/**
 * Router del servidor integrado: php -S localhost:8000 router.php
 * Sirve estáticos y PHP existentes; bloquea rutas sensibles; CORS + OPTIONS.
 */
require __DIR__ . '/includes/cors.php';

crm_cors_headers();
crm_cors_preflight();

$uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '/';
$path = parse_url($uri, PHP_URL_PATH);
$path = is_string($path) ? $path : '/';
$path = '/' . ltrim($path, '/');

$blocked = array(
    '/.env' => true,
    '/config/' => true,
    '/src/' => true,
    '/includes/' => true,
    '/sql/' => true,
    '/tests/' => true,
    '/data/' => true,
    '/database/' => true,
    '/scripts/' => true,
);

$deny = false;
if (isset($blocked[$path]) || strpos($path, '/.') === 0) {
    $deny = true;
} else {
    foreach ($blocked as $prefix => $ok) {
        if ($prefix !== '/.env' && strpos($path, $prefix) === 0) {
            $deny = true;
            break;
        }
    }
}

if ($deny) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Forbidden\n";
    exit;
}

if ($path === '/' || $path === '') {
    $path = '/index.php';
}

$file = __DIR__ . $path;
if (is_file($file)) {
    return false;
}

http_response_code(404);
header('Content-Type: text/plain; charset=utf-8');
echo "Not found\n";
exit;
