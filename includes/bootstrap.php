<?php

declare(strict_types=1);

use Crm\Autoloader;
use Crm\Comex\Schema;
use Crm\Database\Connection;
use Crm\Env;
use Crm\Platform;
use Crm\Storage\Uploads;

$crmRoot = dirname(__DIR__);

require_once $crmRoot . '/includes/cors.php';
require_once $crmRoot . '/src/Crm/Autoloader.php';

Autoloader::register($crmRoot);
Env::boot($crmRoot);

date_default_timezone_set(Env::getInstance()->string('APP_TZ', 'America/Santiago'));

$crmDisplay = strtolower(Env::getInstance()->string('DISPLAY_ERRORS', ''));
$crmShowErrors = in_array($crmDisplay, ['1', 'on', 'true', 'yes'], true);
if (crm_is_production() || $crmDisplay === 'off' || $crmDisplay === '0') {
    $crmShowErrors = false;
}
ini_set('display_errors', $crmShowErrors ? '1' : '0');
ini_set('display_startup_errors', $crmShowErrors ? '1' : '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

if (PHP_SAPI !== 'cli') {
    crm_cors_headers();
    crm_cors_preflight();
}

if (PHP_SAPI !== 'cli' && session_status() !== PHP_SESSION_ACTIVE) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443)
        || str_starts_with(Env::getInstance()->string('APP_URL', ''), 'https://');
    session_name('comex_lpaezsis');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

try {
    Platform::assertReady();
} catch (\Throwable $e) {
    if (PHP_SAPI === 'cli') {
        throw $e;
    }
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => false,
        'success' => false,
        'error' => crm_debug() ? $e->getMessage() : 'Runtime PHP 8.1 no disponible',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    Uploads::ensure();
} catch (\Throwable) {
    // El health reportará uploads no escribible; no bloquear el arranque.
}

try {
    $driverName = strtolower((string) Env::getInstance()->get('COMEX_DB_DRIVER', 'mysql'));
    if ($driverName === 'sqlite' || (string) Env::getInstance()->get('DB_NAME', '') !== '') {
        Schema::install();
    }
} catch (\Throwable) {
    // Health reportará db error; no bloquear el arranque HTTP.
}

function crm_env(string $key, ?string $default = null): ?string
{
    return Env::getInstance()->get($key, $default);
}

function crm_is_production(): bool
{
    return strtolower((string) crm_env('APP_ENV', '')) === 'production';
}

function crm_debug(): bool
{
    if (crm_is_production()) {
        return false;
    }
    return in_array(strtolower((string) crm_env('APP_DEBUG', '0')), ['1', 'true', 'yes'], true);
}

function crm_pdo(): \PDO
{
    return Connection::app();
}

function crm_pdo_driver(): string
{
    return Connection::driver();
}

function crm_h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function crm_now(): string
{
    return (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
}
