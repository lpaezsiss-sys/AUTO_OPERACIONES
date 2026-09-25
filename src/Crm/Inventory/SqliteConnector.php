<?php

declare(strict_types=1);

namespace Crm\Inventory;

use Crm\Env;
use PDO;
use PDOException;
use RuntimeException;

/**
 * Conector de `prod.db` (INV_SQLITE_PATH) para PHP-FPM / CageFS.
 *
 * Lectura: PRAGMA query_only + busy_timeout (no bloquea al escritor de inventario).
 * Escritura/sincronización: WAL + busy_timeout + BEGIN IMMEDIATE con reintentos
 * ante SQLITE_BUSY / SQLITE_LOCKED (varios workers FPM + Next.js).
 */
final class SqliteConnector
{
    public const BUSY_TIMEOUT_MS_DEFAULT = 8000;
    public const MAX_RETRIES_DEFAULT = 8;
    public const RETRY_BASE_US = 25000;

    private static ?PDO $read = null;

    /** @var PDO|null|false false = aún no intentado */
    private static PDO|null|false $write = false;

    public static function reset(): void
    {
        self::$read = null;
        self::$write = false;
    }

    public static function path(): string
    {
        $path = trim((string) Env::getInstance()->get('INV_SQLITE_PATH', ''));
        if ($path === '' || $path === ':memory:' || str_starts_with($path, '/')) {
            return $path;
        }
        return Env::getInstance()->root() . '/' . ltrim($path, '/');
    }

    public static function busyTimeoutMs(): int
    {
        $raw = Env::getInstance()->get('INV_SQLITE_BUSY_TIMEOUT_MS');
        if ($raw !== null && is_numeric($raw) && (int) $raw >= 0) {
            return (int) $raw;
        }
        return self::BUSY_TIMEOUT_MS_DEFAULT;
    }

    public static function maxRetries(): int
    {
        $raw = Env::getInstance()->get('INV_SQLITE_RETRIES');
        if ($raw !== null && is_numeric($raw) && (int) $raw >= 0) {
            return (int) $raw;
        }
        return self::MAX_RETRIES_DEFAULT;
    }

    public static function walEnabled(): bool
    {
        $raw = Env::getInstance()->get('INV_SQLITE_WAL', '1');
        return !in_array(strtolower((string) $raw), ['0', 'off', 'false', 'no'], true);
    }

    public static function readOnlyForced(): bool
    {
        $raw = Env::getInstance()->get('INV_SQLITE_READ_ONLY', '0');
        return in_array(strtolower((string) $raw), ['1', 'on', 'true', 'yes'], true);
    }

    public static function read(): ?PDO
    {
        if (self::$read instanceof PDO) {
            return self::$read;
        }
        try {
            self::$read = self::open(false);
        } catch (\Throwable) {
            self::$read = null;
        }
        return self::$read;
    }

    public static function write(): PDO
    {
        if (self::$write instanceof PDO) {
            return self::$write;
        }
        if (self::readOnlyForced()) {
            throw new RuntimeException('INV_SQLITE_READ_ONLY=1: escritura a prod.db deshabilitada.');
        }
        self::$write = self::open(true);
        return self::$write;
    }

    public static function writable(): bool
    {
        if (self::readOnlyForced()) {
            return false;
        }
        $path = self::path();
        if ($path === ':memory:') {
            return true;
        }
        if ($path === '' || !is_file($path) || !is_writable($path)) {
            return false;
        }
        return is_writable(dirname($path));
    }

    /**
     * Nueva conexión (tests de bloqueo concurrente). El PDO de escritura
     * habitual se reutiliza por request PHP-FPM.
     */
    public static function open(bool $writable): PDO
    {
        $path = self::path();
        if ($path === '') {
            throw new RuntimeException('INV_SQLITE_PATH no configurado.');
        }

        $opts = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => max(1, (int) ceil(self::busyTimeoutMs() / 1000)),
        ];

        if ($path === ':memory:') {
            $pdo = new PDO('sqlite::memory:', null, null, $opts);
            self::applyPragmas($pdo, $writable);
            return $pdo;
        }

        if (!is_file($path)) {
            throw new RuntimeException('SQLite de inventario no encontrado.');
        }
        if (!is_readable($path)) {
            throw new RuntimeException('SQLite de inventario no es legible (CageFS).');
        }
        if ($writable && !is_writable($path)) {
            throw new RuntimeException('SQLite de inventario no es escribible (permisos CageFS).');
        }
        if ($writable && !is_writable(dirname($path))) {
            throw new RuntimeException('El directorio de prod.db no es escribible (WAL necesita -wal/-shm).');
        }

        $pdo = new PDO('sqlite:' . $path, null, null, $opts);
        self::applyPragmas($pdo, $writable);
        return $pdo;
    }

    /**
     * @template T
     * @param callable(PDO): T $fn
     * @return T
     */
    public static function transaction(callable $fn): mixed
    {
        return self::retry(static function () use ($fn) {
            $pdo = self::write();
            $pdo->exec('BEGIN IMMEDIATE');
            try {
                $result = $fn($pdo);
                $pdo->exec('COMMIT');
                return $result;
            } catch (\Throwable $e) {
                try {
                    $pdo->exec('ROLLBACK');
                } catch (\Throwable) {
                }
                throw $e;
            }
        });
    }

    /**
     * @template T
     * @param callable(): T $fn
     * @return T
     */
    public static function retry(callable $fn): mixed
    {
        $attempt = 0;
        $max = self::maxRetries();
        while (true) {
            try {
                return $fn();
            } catch (PDOException $e) {
                if (!self::isBusy($e) || $attempt >= $max) {
                    throw $e;
                }
                $attempt++;
                $delay = self::RETRY_BASE_US * (2 ** ($attempt - 1));
                $delay = min($delay, 500000);
                usleep($delay + random_int(0, 15000));
            }
        }
    }

    public static function isBusy(PDOException $e): bool
    {
        $code = (int) ($e->errorInfo[1] ?? 0);
        if ($code === 5 || $code === 6) {
            return true;
        }
        $msg = strtolower($e->getMessage());
        return str_contains($msg, 'database is locked')
            || str_contains($msg, 'database schema is locked');
    }

    /**
     * @return array<string, mixed>
     */
    public static function status(bool $revealPath = false): array
    {
        $path = self::path();
        $configured = $path !== '';
        $exists = $configured && ($path === ':memory:' || is_file($path));
        $readable = $exists && ($path === ':memory:' || is_readable($path));
        $fileWritable = $exists && ($path === ':memory:' || is_writable($path));
        $dirWritable = $path === ':memory:'
            || ($path !== '' && is_dir(dirname($path)) && is_writable(dirname($path)));

        $journal = null;
        $busy = null;
        $read = self::read();
        if ($read instanceof PDO) {
            try {
                $journal = strtolower((string) $read->query('PRAGMA journal_mode')->fetchColumn());
            } catch (\Throwable) {
                $journal = null;
            }
            try {
                $busy = (int) $read->query('PRAGMA busy_timeout')->fetchColumn();
            } catch (\Throwable) {
                $busy = null;
            }
        }

        $perm = '';
        $dirPerm = '';
        if ($path !== '' && $path !== ':memory:' && is_file($path)) {
            $perm = substr(sprintf('%o', (int) fileperms($path)), -4);
            $dirPerm = substr(sprintf('%o', (int) fileperms(dirname($path))), -4);
        }

        return [
            'configured' => $configured,
            'path' => $revealPath ? $path : ($configured ? basename($path) : ''),
            'exists' => $exists,
            'readable' => $readable,
            'writable' => $fileWritable && $dirWritable && !self::readOnlyForced(),
            'dir_writable' => $dirWritable,
            'perm' => $perm,
            'dir_perm' => $dirPerm,
            'journal_mode' => $journal,
            'wal' => $journal === 'wal',
            'busy_timeout_ms' => $busy ?? self::busyTimeoutMs(),
            'connected' => $read instanceof PDO,
            'read_only_forced' => self::readOnlyForced(),
        ];
    }

    private static function applyPragmas(PDO $pdo, bool $writable): void
    {
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA busy_timeout = ' . self::busyTimeoutMs());
        $pdo->exec('PRAGMA temp_store = MEMORY');

        if (!$writable) {
            $pdo->exec('PRAGMA query_only = ON');
            return;
        }

        $pdo->exec('PRAGMA synchronous = NORMAL');
        if (self::walEnabled()) {
            try {
                $mode = strtolower((string) $pdo->query('PRAGMA journal_mode = WAL')->fetchColumn());
                if ($mode === 'wal') {
                    $pdo->exec('PRAGMA wal_autocheckpoint = 1000');
                }
            } catch (PDOException) {
                // FS sin WAL (algunos mounts): se mantiene el journal por defecto + busy_timeout.
            }
        }
    }
}
