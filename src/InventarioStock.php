<?php

declare(strict_types=1);

namespace Crm;

use PDO;
use PDOException;

/**
 * Lectura del SQLite de inventario.lpaezsis.cl (Prisma, tabla Product).
 * Solo SELECT sobre Product. El alta al catálogo CRM (stock 0) vive en Productos.
 * Si INV_SQLITE_PATH no está o falla, el caller usa productos.stock.
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
     * Búsqueda en Product por código exacto, código/nombre/descripcion LIKE.
     * Escapa % y _ del LIKE. El guion del SKU (p. ej. 12852-48) es literal.
     *
     * @param string $q
     * @param int $limit
     * @return list<array<string,mixed>>
     */
    public static function buscar($q, $limit = 20)
    {
        $q = trim((string) $q);
        $limit = (int) $limit;
        if ($limit < 1) {
            $limit = 20;
        }
        if ($limit > 50) {
            $limit = 50;
        }
        if ($q === '') {
            return array();
        }
        $pdo = self::pdo();
        if (!($pdo instanceof PDO)) {
            return array();
        }
        $cols = self::columnasProduct($pdo);
        if (!isset($cols['code'])) {
            return array();
        }
        $codeCol = $cols['code'];
        $like = self::likeNeedle($q);
        $sql = 'SELECT * FROM Product WHERE ' . self::quoteIdent($codeCol) . ' = ?';
        $params = array($q);
        $sql .= ' OR ' . self::quoteIdent($codeCol) . " LIKE ? ESCAPE '\\'";
        $params[] = $like;
        if (isset($cols['name'])) {
            $sql .= ' OR ' . self::quoteIdent($cols['name']) . " LIKE ? ESCAPE '\\'";
            $params[] = $like;
        }
        if (isset($cols['description'])) {
            $sql .= ' OR IFNULL(' . self::quoteIdent($cols['description']) . ", '') LIKE ? ESCAPE '\\'";
            $params[] = $like;
        }
        $sql .= ' ORDER BY CASE WHEN ' . self::quoteIdent($codeCol) . ' = ? THEN 0 ELSE 1 END, '
            . (isset($cols['name']) ? self::quoteIdent($cols['name']) : self::quoteIdent($codeCol))
            . ' ASC LIMIT ' . $limit;
        $params[] = $q;
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return array();
        }
        if (!is_array($raw)) {
            return array();
        }
        $out = array();
        $seen = array();
        foreach ($raw as $row) {
            $mapped = self::mapearFila($row);
            if ($mapped === null) {
                continue;
            }
            $key = strtoupper((string) $mapped['code']);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $mapped;
        }
        return $out;
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
     * @param string $q
     * @return string
     */
    public static function likeNeedle($q)
    {
        $q = str_replace(array('\\', '%', '_'), array('\\\\', '\\%', '\\_'), (string) $q);
        return '%' . $q . '%';
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

    /**
     * @param PDO $pdo
     * @return array<string,string> logical => actual name
     */
    private static function columnasProduct(PDO $pdo)
    {
        $logical = array(
            'code' => array('code'),
            'name' => array('name'),
            'description' => array('description', 'desc'),
            'stock' => array('stock'),
            'averageunitcost' => array('averageunitcost', 'average_unit_cost'),
        );
        $actual = array();
        try {
            $stmt = $pdo->query('PRAGMA table_info("Product")');
            $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : array();
        } catch (PDOException $e) {
            return array();
        }
        if (!is_array($rows) || $rows === array()) {
            return array();
        }
        $byLower = array();
        foreach ($rows as $r) {
            if (!isset($r['name'])) {
                continue;
            }
            $byLower[strtolower((string) $r['name'])] = (string) $r['name'];
        }
        foreach ($logical as $key => $aliases) {
            foreach ($aliases as $alias) {
                if (isset($byLower[$alias])) {
                    $actual[$key] = $byLower[$alias];
                    break;
                }
            }
        }
        return $actual;
    }

    /**
     * @param string $ident
     * @return string
     */
    private static function quoteIdent($ident)
    {
        return '"' . str_replace('"', '""', (string) $ident) . '"';
    }

    /**
     * @param array $row
     * @return array|null
     */
    private static function mapearFila(array $row)
    {
        $lower = array();
        foreach ($row as $k => $v) {
            $lower[strtolower((string) $k)] = $v;
        }
        $code = '';
        if (isset($lower['code']) && trim((string) $lower['code']) !== '') {
            $code = trim((string) $lower['code']);
        }
        if ($code === '') {
            return null;
        }
        $name = $code;
        if (isset($lower['name']) && trim((string) $lower['name']) !== '') {
            $name = trim((string) $lower['name']);
        }
        $description = $name;
        if (isset($lower['description']) && trim((string) $lower['description']) !== '') {
            $description = trim((string) $lower['description']);
        } elseif (isset($lower['desc']) && trim((string) $lower['desc']) !== '') {
            $description = trim((string) $lower['desc']);
        }
        $stock = 0.0;
        if (isset($lower['stock']) && $lower['stock'] !== null && $lower['stock'] !== '') {
            $stock = (float) $lower['stock'];
        }
        $costo = null;
        foreach (array('averageunitcost', 'average_unit_cost') as $ck) {
            if (isset($lower[$ck]) && $lower[$ck] !== null && $lower[$ck] !== '') {
                $costo = (float) $lower[$ck];
                break;
            }
        }
        return array(
            'code' => $code,
            'name' => $name,
            'description' => $description,
            'stock' => $stock,
            'averageUnitCost' => $costo,
        );
    }
}
