<?php

declare(strict_types=1);

namespace Crm\Inventory;

use PDO;
use PDOException;

/**
 * Catálogo Prisma (Product + Movement) en prod.db.
 * Lectura para fichas COMEX; no muta stock (eso es StockSync).
 */
final class Catalogo
{
    /**
     * @return array<string, mixed>|null
     */
    public static function porCodigo(string $codigo): ?array
    {
        $codigo = trim($codigo);
        if ($codigo === '') {
            return null;
        }
        $pdo = SqliteConnector::read();
        if (!($pdo instanceof PDO)) {
            return null;
        }
        try {
            $stmt = $pdo->prepare('SELECT * FROM Product WHERE code = ? LIMIT 1');
            $stmt->execute([$codigo]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException) {
            return null;
        }
        return is_array($row) ? self::mapProduct($row) : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function porId(string $id): ?array
    {
        $id = trim($id);
        if ($id === '') {
            return null;
        }
        $pdo = SqliteConnector::read();
        if (!($pdo instanceof PDO)) {
            return null;
        }
        try {
            $stmt = $pdo->prepare('SELECT * FROM Product WHERE id = ? LIMIT 1');
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException) {
            return null;
        }
        return is_array($row) ? self::mapProduct($row) : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function listar(int $limit = 200): array
    {
        $pdo = SqliteConnector::read();
        if (!($pdo instanceof PDO)) {
            return [];
        }
        $limit = max(1, min(1000, $limit));
        try {
            $stmt = $pdo->query('SELECT * FROM Product ORDER BY name ASC LIMIT ' . $limit);
            $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        } catch (PDOException) {
            return [];
        }
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $mapped = self::mapProduct($row);
            if ($mapped !== null) {
                $out[] = $mapped;
            }
        }
        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function movimientosPorProducto(string $productId, int $limit = 50): array
    {
        $pdo = SqliteConnector::read();
        if (!($pdo instanceof PDO) || trim($productId) === '') {
            return [];
        }
        $limit = max(1, min(200, $limit));
        try {
            $stmt = $pdo->prepare(
                'SELECT * FROM Movement WHERE productId = ? ORDER BY date DESC, createdAt DESC LIMIT ' . $limit
            );
            $stmt->execute([$productId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException) {
            return [];
        }
        $out = [];
        foreach ($rows as $row) {
            if (is_array($row)) {
                $out[] = self::mapMovement($row);
            }
        }
        return $out;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>|null
     */
    public static function mapProduct(array $row): ?array
    {
        $lower = [];
        foreach ($row as $k => $v) {
            $lower[strtolower((string) $k)] = $v;
        }
        $code = trim((string) ($lower['code'] ?? ''));
        if ($code === '') {
            return null;
        }
        $name = trim((string) ($lower['name'] ?? ''));
        $desc = trim((string) ($lower['description'] ?? $lower['desc'] ?? ''));
        return [
            'id' => (string) ($lower['id'] ?? ''),
            'code' => $code,
            'name' => $name !== '' ? $name : $code,
            'description' => $desc,
            'stock' => isset($lower['stock']) && $lower['stock'] !== '' ? (float) $lower['stock'] : 0.0,
            'averageUnitCost' => isset($lower['averageunitcost']) && $lower['averageunitcost'] !== ''
                ? (float) $lower['averageunitcost']
                : 0.0,
            'lowStockThreshold' => isset($lower['lowstockthreshold']) && $lower['lowstockthreshold'] !== ''
                ? (float) $lower['lowstockthreshold']
                : null,
            'createdAt' => isset($lower['createdat']) ? (string) $lower['createdat'] : null,
            'updatedAt' => isset($lower['updatedat']) ? (string) $lower['updatedat'] : null,
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function mapMovement(array $row): array
    {
        $lower = [];
        foreach ($row as $k => $v) {
            $lower[strtolower((string) $k)] = $v;
        }
        return [
            'id' => (string) ($lower['id'] ?? ''),
            'type' => (string) ($lower['type'] ?? ''),
            'documentNumber' => (string) ($lower['documentnumber'] ?? ''),
            'productId' => (string) ($lower['productid'] ?? ''),
            'quantity' => isset($lower['quantity']) ? (float) $lower['quantity'] : 0.0,
            'unitPrice' => isset($lower['unitprice']) ? (float) $lower['unitprice'] : 0.0,
            'date' => (string) ($lower['date'] ?? ''),
            'createdAt' => (string) ($lower['createdat'] ?? ''),
        ];
    }
}
