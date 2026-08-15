<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/polyfills.php';

/**
 * Conexión PDO única para el CRM (PHP 7.4 / MySQL utf8mb4).
 * ATTR_EMULATE_PREPARES = false (prepared statements nativos).
 * ATTR_ERRMODE = PDO::ERRMODE_EXCEPTION.
 * Producción BlueHosting: MySQL/MariaDB. Preview/tests: SQLite.
 */
function crm_pdo()
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $driver = crm_lower((string) crm_env('CRM_DB_DRIVER', 'mysql'));

    if ($driver === 'sqlite') {
        $path = (string) crm_env('CRM_SQLITE_PATH', dirname(__DIR__) . '/data/crm.sqlite');
        if ($path === ':memory:') {
            $pdo = new PDO('sqlite::memory:', null, null, crm_pdo_options());
        } else {
            if (!str_starts_with($path, '/')) {
                $path = dirname(__DIR__) . '/' . ltrim($path, '/');
            }
            $dir = dirname($path);
            if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
                throw new RuntimeException('No se pudo crear el directorio de datos SQLite.');
            }
            $pdo = new PDO('sqlite:' . $path, null, null, crm_pdo_options());
        }
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA journal_mode = WAL');
        return $pdo;
    }

    $host = (string) crm_env('DB_HOST', 'localhost');
    $port = (string) crm_env('DB_PORT', '3306');
    $name = (string) crm_env('DB_NAME', 'crm');
    $user = (string) crm_env('DB_USER', 'root');
    $pass = (string) crm_env('DB_PASS', '');
    $charset = (string) crm_env('DB_CHARSET', 'utf8mb4');

    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $host, $port, $name, $charset);

    try {
        $pdo = new PDO($dsn, $user, $pass, crm_pdo_options());
    } catch (PDOException $e) {
        if (crm_debug()) {
            throw $e;
        }
        throw new RuntimeException('No se pudo conectar a MySQL/MariaDB.');
    }

    return $pdo;
}

function crm_pdo_driver()
{
    return crm_pdo()->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? 'sqlite' : 'mysql';
}

function crm_pdo_options()
{
    return array(
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    );
}

/**
 * @param string $key
 * @param string|null $default
 * @return string|null
 */
function crm_env($key, $default = null)
{
    static $loaded = false;
    static $map = array();

    if (!$loaded) {
        $loaded = true;
        $file = dirname(__DIR__) . '/.env';
        if (is_file($file) && is_readable($file)) {
            $lines = file($file, FILE_IGNORE_NEW_LINES);
            if (!is_array($lines)) {
                $lines = array();
            }
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#')) {
                    continue;
                }
                $eq = strpos($line, '=');
                if ($eq === false) {
                    continue;
                }
                $k = trim(substr($line, 0, $eq));
                $v = trim(substr($line, $eq + 1));
                if (
                    (str_starts_with($v, '"') && str_ends_with($v, '"'))
                    || (str_starts_with($v, "'") && str_ends_with($v, "'"))
                ) {
                    $v = substr($v, 1, -1);
                }
                $map[$k] = $v;
            }
        }
    }

    $fromEnv = getenv($key);
    if ($fromEnv !== false && $fromEnv !== '') {
        return $fromEnv;
    }
    if (array_key_exists($key, $map) && $map[$key] !== '') {
        return $map[$key];
    }
    return $default;
}

function crm_debug()
{
    return in_array(crm_lower((string) crm_env('APP_DEBUG', '0')), array('1', 'true', 'yes'), true);
}

function crm_iva_pct()
{
    $raw = crm_env('IVA_PCT', '19');
    $pct = is_numeric($raw) ? (float) $raw : 19.0;
    return $pct > 0 ? $pct : 19.0;
}
