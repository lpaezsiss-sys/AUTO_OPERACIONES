<?php

declare(strict_types=1);

/**
 * Router del servidor integrado: php -S localhost:8080 router.php
 * Replica las reglas de .htaccess: 403 a secretos/config, estáticos, front controller.
 */
require __DIR__ . '/includes/cors.php';

crm_cors_headers();
crm_cors_preflight();

$uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '/';
$path = parse_url($uri, PHP_URL_PATH);
$path = is_string($path) ? $path : '/';
$path = '/' . ltrim($path, '/');

$blockedExact = [
    '/.env' => true,
    '/.env.example' => true,
    '/.env.production' => true,
    '/.webdavignore' => true,
    '/composer.json' => true,
    '/composer.lock' => true,
];

$blockedPrefixes = [
    '/config/',
    '/src/',
    '/includes/',
    '/sql/',
    '/tests/',
    '/data/',
    '/database/',
    '/scripts/',
    '/deploy/',
    '/vendor/',
];

$deny = false;
$base = basename($path);

if (isset($blockedExact[$path]) || str_starts_with($path, '/.')) {
    $deny = true;
} elseif (preg_match('/\.(env|ini|conf|config|sql|sqlite|db|log|bak)$/i', $base) === 1) {
    $deny = true;
} else {
    foreach ($blockedPrefixes as $prefix) {
        if (str_starts_with($path, $prefix) || $path === rtrim($prefix, '/')) {
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

require __DIR__ . '/index.php';
