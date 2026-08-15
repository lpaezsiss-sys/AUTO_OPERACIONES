<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/polyfills.php';
require_once dirname(__DIR__) . '/config/db.php';

date_default_timezone_set('America/Santiago');

spl_autoload_register(static function ($class) {
    $prefix = 'Crm\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $rel = str_replace('\\', '/', substr($class, strlen($prefix)));
    $file = dirname(__DIR__) . '/src/' . $rel . '.php';
    if (is_file($file)) {
        require $file;
    }
});

if (session_status() !== PHP_SESSION_ACTIVE) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ((int) (isset($_SERVER['SERVER_PORT']) ? $_SERVER['SERVER_PORT'] : 0) === 443);
    session_name('crm_lpaezsis');
    session_set_cookie_params(array(
        'lifetime' => 0,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ));
    session_start();
}

if (crm_env('CRM_DB_DRIVER', 'mysql') === 'sqlite') {
    try {
        crm_pdo()->query('SELECT 1 FROM crm_usuarios LIMIT 1');
    } catch (Exception $e) {
        \Crm\Schema::install();
    }
}

function crm_now()
{
    return (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
}

/**
 * @param mixed $value
 * @param int $max
 */
function crm_str($value, $max = 500)
{
    $s = trim((string) $value);
    if (function_exists('mb_substr')) {
        return mb_substr($s, 0, (int) $max);
    }
    return substr($s, 0, (int) $max);
}

/**
 * @param mixed $value
 * @param int $default
 */
function crm_int($value, $default = 0)
{
    if (is_int($value)) {
        return $value;
    }
    if (is_numeric($value)) {
        return (int) $value;
    }
    return (int) $default;
}

/**
 * @param mixed $value
 * @param float $default
 */
function crm_float($value, $default = 0.0)
{
    if (is_int($value) || is_float($value)) {
        return (float) $value;
    }
    if (!is_string($value)) {
        return (float) $default;
    }
    $value = trim($value);
    if ($value === '') {
        return (float) $default;
    }
    if (preg_match('/^-?\d+(\.\d+)?$/', $value)) {
        return (float) $value;
    }
    $stripped = preg_replace('/[^\d,.\-]/', '', $value);
    if (!is_string($stripped)) {
        $stripped = '';
    }
    $normalized = str_replace(array('.', ','), array('', '.'), $stripped);
    return is_numeric($normalized) ? (float) $normalized : (float) $default;
}

/**
 * @param mixed $value
 */
function crm_h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function crm_page_user()
{
    $user = \Crm\Auth::user();
    if ($user === null) {
        header('Location: login.php');
        exit;
    }
    return $user;
}
