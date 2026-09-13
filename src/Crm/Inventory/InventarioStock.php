<?php

declare(strict_types=1);

namespace Crm\Inventory;

use Crm\Database\Connection;
use PDO;
use PDOException;

/**
 * Lectura del SQLite de inventario.lpaezsis.cl (Prisma, tabla Product).
 * Solo SELECT. Nunca escribe stock ni mueve el archivo prod.db.
 */
final class InventarioStock
{
    public static function disponible(): bool
    {
        return Connection::inventory() instanceof PDO;
    }

    /**
     * @return array{
     *   configured: bool,
     *   path: string,
     *   readable: bool,
     *   connected: bool
     * }
     */
    public static function status(bool $revealPath = false): array
    {
        $path = Connection::inventoryPath();
        $configured = $path !== '';
        $readable = $configured && ($path === ':memory:' || (is_file($path) && is_readable($path)));

        return [
            'configured' => $configured,
            'path' => $revealPath ? $path : ($configured ? basename($path) : ''),
            'readable' => $readable,
            'connected' => self::disponible(),
        ];
    }

    public static function stockPorCodigo(string $codigo): ?float
    {
        $codigo = trim($codigo);
        if ($codigo === '') {
            return null;
        }
        $pdo = Connection::inventory();
        if (!($pdo instanceof PDO)) {
            return null;
        }
        try {
            $stmt = $pdo->prepare('SELECT stock FROM Product WHERE code = ? LIMIT 1');
            $stmt->execute([$codigo]);
            $val = $stmt->fetchColumn();
            if ($val === false || $val === null || $val === '') {
                return null;
            }
            return (float) $val;
        } catch (PDOException) {
            return null;
        }
    }

    /**
     * Búsqueda en Product por código exacto, código/nombre/descripcion LIKE.
     * Escapa % y _ del LIKE. El guion del SKU (p. ej. 12852-48) es literal.
     *
     * @return list<array{code: string, name: string, description: string, stock: float, averageUnitCost: float|null}>
     */
    public static function buscar(string $q, int $limit = 20): array
    {
        $q = trim($q);
        $limit = max(1, min(50, $limit));
        if ($q === '') {
            return [];
        }
        $pdo = Connection::inventory();
        if (!($pdo instanceof PDO)) {
            return [];
        }
        $cols = self::columnasProduct($pdo);
        if (!isset($cols['code'])) {
            return [];
        }
        $codeCol = $cols['code'];
        $like = self::likeNeedle($q);
        $esc = self::likeEscapeSql();
        $sql = 'SELECT * FROM Product WHERE ' . self::quoteIdent($codeCol) . ' = ?';
        $params = [$q];
        $sql .= ' OR ' . self::quoteIdent($codeCol) . ' LIKE ?' . $esc;
        $params[] = $like;
        if (isset($cols['name'])) {
            $sql .= ' OR ' . self::quoteIdent($cols['name']) . ' LIKE ?' . $esc;
            $params[] = $like;
        }
        if (isset($cols['description'])) {
            $sql .= ' OR IFNULL(' . self::quoteIdent($cols['description']) . ", '') LIKE ?" . $esc;
            $params[] = $like;
        }
        $orderName = isset($cols['name']) ? self::quoteIdent($cols['name']) : self::quoteIdent($codeCol);
        $sql .= ' ORDER BY CASE WHEN ' . self::quoteIdent($codeCol) . ' = ? THEN 0 ELSE 1 END, '
            . $orderName . ' ASC LIMIT ' . $limit;
        $params[] = $q;

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException) {
            return [];
        }
        if (!is_array($raw)) {
            return [];
        }

        $out = [];
        $seen = [];
        foreach ($raw as $row) {
            $mapped = self::mapearFila($row);
            if ($mapped === null) {
                continue;
            }
            $key = strtoupper($mapped['code']);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $mapped;
        }
        return $out;
    }

    public static function likeNeedle(string $q): string
    {
        $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $q);
        return '%' . $escaped . '%';
    }

    public static function likeEscapeSql(): string
    {
        return " ESCAPE '!'";
    }

    /**
     * @return array<string, string> logical => actual name
     */
    private static function columnasProduct(PDO $pdo): array
    {
        $logical = [
            'code' => ['code'],
            'name' => ['name'],
            'description' => ['description', 'desc'],
            'stock' => ['stock'],
            'averageunitcost' => ['averageunitcost', 'average_unit_cost'],
        ];
        try {
            $stmt = $pdo->query('PRAGMA table_info("Product")');
            $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        } catch (PDOException) {
            return [];
        }
        if (!is_array($rows) || $rows === []) {
            return [];
        }
        $byLower = [];
        foreach ($rows as $r) {
            if (!isset($r['name'])) {
                continue;
            }
            $byLower[strtolower((string) $r['name'])] = (string) $r['name'];
        }
        $actual = [];
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

    private static function quoteIdent(string $ident): string
    {
        return '"' . str_replace('"', '""', $ident) . '"';
    }

    /**
     * @param array<string, mixed> $row
     * @return array{code: string, name: string, description: string, stock: float, averageUnitCost: float|null}|null
     */
    private static function mapearFila(array $row): ?array
    {
        $lower = [];
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
        foreach (['averageunitcost', 'average_unit_cost'] as $ck) {
            if (isset($lower[$ck]) && $lower[$ck] !== null && $lower[$ck] !== '') {
                $costo = (float) $lower[$ck];
                break;
            }
        }
        return [
            'code' => $code,
            'name' => $name,
            'description' => $description,
            'stock' => $stock,
            'averageUnitCost' => $costo,
        ];
    }
}
