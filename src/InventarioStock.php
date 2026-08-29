<?php

declare(strict_types=1);

namespace Crm;

use PDO;
use PDOException;

/**
 * Stock real de inventario.lpaezsis.cl (Prisma/SQLite Product.stock).
 * Solo SELECT. Si INV_SQLITE_PATH no está o falla, el caller usa productos.stock.
 */
final class InventarioStock
{
    /** @var PDO|null|false false = aún no intentado */
    private static $pdo = false;

    /**
     * @return void
     */
    public static function reset()
    {
        self::$pdo = false;
    }

    /**
     * @param string $codigo SKU (Product.code / productos.codigo)
     * @return float|null
     */
    public static function stockPorCodigo($codigo)
    {
        $codigo = trim((string) $codigo);
        if ($codigo === '') {
            return null;
        }
        $pdo = self::pdo();
        if (!($pdo instanceof PDO)) {
            return null;
        }
        try {
            $stmt = $pdo->prepare('SELECT stock FROM Product WHERE code = ? LIMIT 1');
            $stmt->execute(array($codigo));
            $val = $stmt->fetchColumn();
            if ($val === false || $val === null || $val === '') {
                return null;
            }
            return (float) $val;
        } catch (PDOException $e) {
            return null;
        }
    }

    /**
     * Sobrescribe stock de una fila CRM si el SQLite de inventario tiene el SKU.
     *
     * @param array $row
     * @return array
     */
    public static function aplicarAFila(array $row)
    {
        $codigo = '';
        if (isset($row['codigo']) && (string) $row['codigo'] !== '') {
            $codigo = (string) $row['codigo'];
        } elseif (isset($row['sku']) && (string) $row['sku'] !== '') {
            $codigo = (string) $row['sku'];
        }
        if (isset($row['stock'])) {
            $row['stock'] = (float) $row['stock'];
        }
        $live = self::stockPorCodigo($codigo);
        if ($live !== null) {
            $row['stock'] = $live;
        }
        return $row;
    }

    /**
     * @return PDO|null
     */
    private static function pdo()
    {
        if (self::$pdo !== false) {
            return self::$pdo instanceof PDO ? self::$pdo : null;
        }
        self::$pdo = null;
        $path = (string) crm_env('INV_SQLITE_PATH', '');
        $path = trim($path);
        if ($path === '') {
            return null;
        }
        if ($path !== ':memory:' && !str_starts_with($path, '/')) {
            $path = dirname(__DIR__) . '/' . ltrim($path, '/');
        }
        if ($path !== ':memory:' && !is_file($path)) {
            return null;
        }
        try {
            $pdo = new PDO('sqlite:' . $path, null, null, array(
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ));
            self::$pdo = $pdo;
            return $pdo;
        } catch (PDOException $e) {
            self::$pdo = null;
            return null;
        }
    }
}
