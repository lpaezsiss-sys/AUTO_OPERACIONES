<?php

declare(strict_types=1);

namespace Crm;

use PDO;

final class Respaldo
{
    /**
     * Dump SQL + ZIP público. PHP 7.4 / ZipArchive o CLI zip.
     *
     * @return array
     */
    public static function generar()
    {
        $root = dirname(__DIR__);
        $sqlPath = $root . '/sql/respaldo_completo_local.sql';
        $dlDir = $root . '/downloads';
        if (!is_dir($dlDir) && !mkdir($dlDir, 0775, true) && !is_dir($dlDir)) {
            Http::fail('No se pudo crear downloads/', 500);
        }

        $bytesSql = self::escribirDump($sqlPath);
        $stamp = date('Ymd_Hi');
        $nombre = 'crm_backup_' . $stamp . '.zip';
        $zipPath = $dlDir . '/' . $nombre;
        if (is_file($zipPath)) {
            unlink($zipPath);
        }

        $archivos = self::listarArchivos($root);
        if (!in_array('sql/respaldo_completo_local.sql', $archivos, true)) {
            $archivos[] = 'sql/respaldo_completo_local.sql';
        }
        sort($archivos);
        self::comprimir($root, $zipPath, $archivos);

        $bytesZip = is_file($zipPath) ? (int) filesize($zipPath) : 0;
        $incluyeSql = self::zipContiene($zipPath, 'sql/respaldo_completo_local.sql');
        if ($bytesZip <= 0 || !$incluyeSql) {
            Http::fail('El ZIP no se generó correctamente o falta el dump SQL.', 500);
        }

        $url = self::urlDescarga($nombre);
        return array(
            'archivo' => $nombre,
            'ruta' => 'downloads/' . $nombre,
            'url' => $url,
            'bytes' => $bytesZip,
            'mb' => round($bytesZip / 1048576, 2),
            'sql' => 'sql/respaldo_completo_local.sql',
            'sql_bytes' => $bytesSql,
            'incluye_sql' => $incluyeSql,
            'archivos' => count($archivos),
            'driver' => crm_pdo_driver(),
        );
    }

    /**
     * @return array
     */
    public static function ultimo()
    {
        $dir = dirname(__DIR__) . '/downloads';
        $latest = null;
        $mtime = 0;
        if (is_dir($dir)) {
            $list = glob($dir . '/crm_backup_*.zip');
            if (is_array($list)) {
                foreach ($list as $file) {
                    $t = (int) filemtime($file);
                    if ($t >= $mtime) {
                        $mtime = $t;
                        $latest = $file;
                    }
                }
            }
        }
        if ($latest === null) {
            return array('archivo' => null, 'url' => null);
        }
        $nombre = basename($latest);
        $bytes = (int) filesize($latest);
        return array(
            'archivo' => $nombre,
            'ruta' => 'downloads/' . $nombre,
            'url' => self::urlDescarga($nombre),
            'bytes' => $bytes,
            'mb' => round($bytes / 1048576, 2),
            'incluye_sql' => self::zipContiene($latest, 'sql/respaldo_completo_local.sql'),
        );
    }

    /**
     * @param string $nombre
     * @return string
     */
    public static function urlDescarga($nombre)
    {
        $nombre = basename((string) $nombre);
        return rtrim(self::urlBase(), '/') . '/downloads/' . $nombre;
    }

    /**
     * @return string
     */
    public static function urlBase()
    {
        $env = crm_env('APP_URL', '');
        if (is_string($env) && $env !== '') {
            return rtrim($env, '/');
        }
        return 'http://localhost:8000';
    }

    /**
     * @param string $sqlPath
     * @return int
     */
    public static function escribirDump($sqlPath)
    {
        $pdo = crm_pdo();
        $driver = crm_pdo_driver();
        $lines = array();
        $lines[] = '-- CRM LPAEZsis respaldo completo local';
        $lines[] = '-- Generado: ' . crm_now();
        $lines[] = '-- Driver: ' . $driver;
        $lines[] = '-- PHP: ' . PHP_VERSION;
        $lines[] = '';
        if ($driver === 'sqlite') {
            $lines[] = 'PRAGMA foreign_keys=OFF;';
            $lines[] = 'BEGIN TRANSACTION;';
            $lines[] = '';
            self::dumpSqlite($pdo, $lines);
            $lines[] = 'COMMIT;';
            $lines[] = 'PRAGMA foreign_keys=ON;';
        } else {
            $lines[] = 'SET NAMES utf8mb4;';
            $lines[] = 'SET FOREIGN_KEY_CHECKS=0;';
            $lines[] = '';
            self::dumpMysql($pdo, $lines);
            $lines[] = 'SET FOREIGN_KEY_CHECKS=1;';
        }
        $lines[] = '';
        $sql = implode("\n", $lines);
        $ok = file_put_contents($sqlPath, $sql);
        if ($ok === false) {
            Http::fail('No se pudo escribir sql/respaldo_completo_local.sql', 500);
        }
        return (int) $ok;
    }

    /**
     * @param array $lines
     * @return void
     */
    private static function dumpSqlite(PDO $pdo, array &$lines)
    {
        $objs = $pdo->query(
            "SELECT type, name, sql FROM sqlite_master
             WHERE name NOT LIKE 'sqlite_%' AND sql IS NOT NULL
             ORDER BY CASE type WHEN 'table' THEN 0 WHEN 'index' THEN 1 ELSE 2 END, name"
        )->fetchAll(PDO::FETCH_ASSOC);
        if (!is_array($objs)) {
            $objs = array();
        }
        $tables = array();
        foreach ($objs as $obj) {
            if ((string) $obj['type'] === 'table') {
                $tables[] = (string) $obj['name'];
            }
        }
        foreach ($tables as $table) {
            $lines[] = 'DROP TABLE IF EXISTS ' . self::identSqlite($table) . ';';
        }
        $lines[] = '';
        foreach ($objs as $obj) {
            $lines[] = rtrim((string) $obj['sql'], ';') . ';';
            $lines[] = '';
        }
        foreach ($tables as $table) {
            self::dumpFilas($pdo, $table, $lines);
        }
    }

    /**
     * @param array $lines
     * @return void
     */
    private static function dumpMysql(PDO $pdo, array &$lines)
    {
        $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_NUM);
        if (!is_array($tables)) {
            $tables = array();
        }
        $nombres = array();
        foreach ($tables as $row) {
            $nombres[] = (string) $row[0];
        }
        foreach ($nombres as $table) {
            $lines[] = 'DROP TABLE IF EXISTS `' . str_replace('`', '``', $table) . '`;';
            $stmt = $pdo->query('SHOW CREATE TABLE `' . str_replace('`', '``', $table) . '`');
            $create = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
            $ddl = '';
            if (is_array($create)) {
                foreach ($create as $k => $v) {
                    if (strpos((string) $k, 'Create') !== false) {
                        $ddl = (string) $v;
                    }
                }
            }
            if ($ddl !== '') {
                $lines[] = rtrim($ddl, ';') . ';';
                $lines[] = '';
            }
            self::dumpFilas($pdo, $table, $lines);
        }
    }

    /**
     * @param string $table
     * @param array $lines
     * @return void
     */
    private static function dumpFilas(PDO $pdo, $table, array &$lines)
    {
        $table = (string) $table;
        $quoted = crm_pdo_driver() === 'sqlite' ? self::identSqlite($table) : '`' . str_replace('`', '``', $table) . '`';
        $stmt = $pdo->query('SELECT * FROM ' . $quoted);
        if (!$stmt) {
            return;
        }
        $n = 0;
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (!is_array($row) || count($row) === 0) {
                continue;
            }
            $cols = array();
            $vals = array();
            foreach ($row as $col => $val) {
                if (crm_pdo_driver() === 'sqlite') {
                    $cols[] = self::identSqlite((string) $col);
                } else {
                    $cols[] = '`' . str_replace('`', '``', (string) $col) . '`';
                }
                $vals[] = self::sqlValor($pdo, $val);
            }
            $lines[] = 'INSERT INTO ' . $quoted . ' (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $vals) . ');';
            $n++;
        }
        if ($n > 0) {
            $lines[] = '';
        }
    }

    /**
     * @param mixed $value
     * @return string
     */
    private static function sqlValor(PDO $pdo, $value)
    {
        if ($value === null) {
            return 'NULL';
        }
        if (is_int($value)) {
            return (string) $value;
        }
        if (is_float($value)) {
            return (string) $value;
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        return $pdo->quote((string) $value);
    }

    /**
     * @param string $name
     * @return string
     */
    private static function identSqlite($name)
    {
        return '"' . str_replace('"', '""', (string) $name) . '"';
    }

    /**
     * @param string $root
     * @return array
     */
    private static function listarArchivos($root)
    {
        $out = array();
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($it as $file) {
            if (!$file->isFile()) {
                continue;
            }
            $full = $file->getPathname();
            $rel = substr($full, strlen($root) + 1);
            $rel = str_replace('\\', '/', (string) $rel);
            if (self::excluir($rel)) {
                continue;
            }
            $out[] = $rel;
        }
        return $out;
    }

    /**
     * @param string $rel
     * @return bool
     */
    public static function excluir($rel)
    {
        $rel = str_replace('\\', '/', (string) $rel);
        $base = basename($rel);
        $top = strtolower((string) strtok($rel, '/'));
        $skipTop = array(
            '.git' => true,
            'data' => true,
            'downloads' => true,
            'tests' => true,
            'vendor' => true,
            'node_modules' => true,
            '.idea' => true,
            '.vscode' => true,
            '.cursor' => true,
        );
        if (isset($skipTop[$top])) {
            return true;
        }
        if ($base === '.env' || $base === '.env.local') {
            return true;
        }
        if (preg_match('/\.(log|sqlite|zip)$/i', $base)) {
            return true;
        }
        if (preg_match('/\.sqlite-(wal|shm|journal)$/i', $base)) {
            return true;
        }
        if (strpos($rel, '/.git/') !== false) {
            return true;
        }
        return false;
    }

    /**
     * @param string $root
     * @param string $zipPath
     * @param array $archivos
     * @return void
     */
    private static function comprimir($root, $zipPath, array $archivos)
    {
        if (class_exists('\ZipArchive')) {
            $zip = new \ZipArchive();
            if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
                Http::fail('No se pudo crear el archivo ZIP', 500);
            }
            foreach ($archivos as $rel) {
                $full = $root . '/' . $rel;
                if (is_file($full)) {
                    $zip->addFile($full, $rel);
                }
            }
            $zip->close();
            return;
        }

        $bin = trim((string) shell_exec('command -v zip'));
        if ($bin === '') {
            Http::fail('Se requiere la extensión zip de PHP o el comando zip en PATH.', 500);
        }
        $listFile = sys_get_temp_dir() . '/crm-zip-list-' . uniqid('', true) . '.txt';
        file_put_contents($listFile, implode("\n", $archivos) . "\n");
        $cmd = 'cd ' . escapeshellarg($root) . ' && ' . escapeshellarg($bin)
            . ' -q ' . escapeshellarg($zipPath) . ' -@ < ' . escapeshellarg($listFile);
        $out = array();
        $code = 0;
        exec($cmd . ' 2>&1', $out, $code);
        if (is_file($listFile)) {
            unlink($listFile);
        }
        if ($code !== 0 || !is_file($zipPath)) {
            Http::fail('zip CLI falló: ' . implode("\n", $out), 500);
        }
    }

    /**
     * @param string $zipPath
     * @param string $nombre
     * @return bool
     */
    public static function zipContiene($zipPath, $nombre)
    {
        $zipPath = (string) $zipPath;
        $nombre = (string) $nombre;
        if (class_exists('\ZipArchive')) {
            $zip = new \ZipArchive();
            if ($zip->open($zipPath) !== true) {
                return false;
            }
            $idx = $zip->locateName($nombre);
            $zip->close();
            return $idx !== false;
        }
        $bin = trim((string) shell_exec('command -v zipinfo'));
        if ($bin !== '') {
            $out = array();
            exec(escapeshellarg($bin) . ' -1 ' . escapeshellarg($zipPath) . ' 2>/dev/null', $out);
            return in_array($nombre, $out, true);
        }
        $zipBin = trim((string) shell_exec('command -v zip'));
        if ($zipBin === '') {
            return false;
        }
        $out = array();
        exec(escapeshellarg($zipBin) . ' -sf ' . escapeshellarg($zipPath) . ' 2>/dev/null', $out);
        foreach ($out as $line) {
            if (trim((string) $line) === $nombre) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param string $nombre
     * @return string
     */
    public static function rutaSegura($nombre)
    {
        $nombre = basename((string) $nombre);
        if (!preg_match('/^crm_backup_\d{8}_\d{4}\.zip$/', $nombre)) {
            Http::fail('Archivo de respaldo inválido', 400);
        }
        $full = dirname(__DIR__) . '/downloads/' . $nombre;
        if (!is_file($full)) {
            Http::fail('Respaldo no encontrado', 404);
        }
        return $full;
    }
}
