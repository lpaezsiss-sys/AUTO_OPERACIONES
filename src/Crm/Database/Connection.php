<?php

declare(strict_types=1);

namespace Crm\Database;

use Crm\Env;
use PDO;
use PDOException;
use RuntimeException;

/**
 * PDO de la app COMEX (MySQL en producción, SQLite en tests)
 * y PDO de solo lectura al SQLite de inventario.
 */
final class Connection
{
    private static ?PDO $app = null;

    /** @var PDO|null|false false = aún no intentado */
    private static PDO|null|false $inventory = false;

    public static function reset(): void
    {
        self::$app = null;
        self::$inventory = false;
    }

    /** @return array<int, mixed> */
    public static function options(): array
    {
        return [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
    }

    public static function app(): PDO
    {
        if (self::$app instanceof PDO) {
            return self::$app;
        }

        $env = Env::getInstance();
        $driver = strtolower($env->string('COMEX_DB_DRIVER', 'mysql'));

        if ($driver === 'sqlite') {
            $path = $env->string('COMEX_SQLITE_PATH', $env->root() . '/data/comex.sqlite');
            self::$app = self::sqlite($path, true);
            return self::$app;
        }

        $host = $env->string('DB_HOST', 'localhost');
        $port = $env->string('DB_PORT', '3306');
        $name = $env->string('DB_NAME', 'sistem29_comex');
        $user = $env->string('DB_USER', '');
        $pass = $env->string('DB_PASS', '');
        $charset = $env->string('DB_CHARSET', 'utf8mb4');
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $host, $port, $name, $charset);

        try {
            self::$app = new PDO($dsn, $user, $pass, self::options());
        } catch (PDOException $e) {
            if (function_exists('crm_debug') && crm_debug()) {
                throw $e;
            }
            throw new RuntimeException('No se pudo conectar a MySQL/MariaDB.');
        }

        return self::$app;
    }

    public static function appOrNull(): ?PDO
    {
        try {
            return self::app();
        } catch (\Throwable) {
            return self::$app instanceof PDO ? self::$app : null;
        }
    }

    public static function driver(): string
    {
        return self::app()->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? 'sqlite' : 'mysql';
    }

    public static function inventory(): ?PDO
    {
        if (self::$inventory !== false) {
            return self::$inventory instanceof PDO ? self::$inventory : null;
        }

        self::$inventory = null;
        $path = trim((string) Env::getInstance()->get('INV_SQLITE_PATH', ''));
        if ($path === '') {
            return null;
        }

        try {
            self::$inventory = self::sqlite($path, false);
        } catch (\Throwable) {
            self::$inventory = null;
        }

        return self::$inventory;
    }

    public static function inventoryPath(): string
    {
        $path = trim((string) Env::getInstance()->get('INV_SQLITE_PATH', ''));
        if ($path === '' || $path === ':memory:' || str_starts_with($path, '/')) {
            return $path;
        }
        return Env::getInstance()->root() . '/' . ltrim($path, '/');
    }

    private static function sqlite(string $path, bool $createDir): PDO
    {
        $path = trim($path);
        if ($path === '') {
            throw new RuntimeException('Ruta SQLite vacía.');
        }

        if ($path === ':memory:') {
            $pdo = new PDO('sqlite::memory:', null, null, self::options());
            $pdo->exec('PRAGMA foreign_keys = ON');
            return $pdo;
        }

        if (!str_starts_with($path, '/')) {
            $path = Env::getInstance()->root() . '/' . ltrim($path, '/');
        }

        $dir = dirname($path);
        if ($createDir && !is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('No se pudo crear el directorio SQLite.');
        }

        if (!$createDir && !is_file($path)) {
            throw new RuntimeException('SQLite de inventario no encontrado: ' . $path);
        }

        $pdo = new PDO('sqlite:' . $path, null, null, self::options());
        $pdo->exec('PRAGMA foreign_keys = ON');
        if ($createDir) {
            $pdo->exec('PRAGMA journal_mode = WAL');
        } else {
            $pdo->exec('PRAGMA query_only = ON');
        }

        return $pdo;
    }
}
