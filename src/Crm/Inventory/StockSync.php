<?php

declare(strict_types=1);

namespace Crm\Inventory;

use Crm\ApiException;
use PDO;

/**
 * Escribe Movement Prisma y actualiza stock/CUP en prod.db.
 * IMPORTACION → ENTRADA; EXPORTACION → SALIDA.
 * Transacción BEGIN IMMEDIATE + reintentos SQLITE_BUSY (PHP-FPM / CageFS).
 */
final class StockSync
{
    public const ENTRADA = 'ENTRADA';
    public const SALIDA = 'SALIDA';

    public static function nuevoCostoPromedio(
        float $stockActual,
        float $cupActual,
        float $cantidad,
        float $precioUnitario
    ): float {
        $total = $stockActual + $cantidad;
        if ($total <= 0) {
            return 0.0;
        }
        return (($stockActual * $cupActual) + ($cantidad * $precioUnitario)) / $total;
    }

    public static function newId(): string
    {
        return 'c' . bin2hex(random_bytes(12));
    }

    public static function nowIso(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.000\Z');
    }

    /**
     * @param list<array{sku: string, cantidad: float, precio_unitario?: float}> $items
     * @return list<array<string, mixed>>
     */
    public static function aplicarDocumento(string $tipoMovimiento, string $documento, array $items, ?string $fecha = null): array
    {
        $tipoMovimiento = strtoupper(trim($tipoMovimiento));
        if ($tipoMovimiento !== self::ENTRADA && $tipoMovimiento !== self::SALIDA) {
            throw new ApiException('Tipo de movimiento inválido', 400);
        }
        $documento = trim($documento);
        if ($documento === '') {
            throw new ApiException('Documento de inventario requerido', 400);
        }
        if ($items === []) {
            throw new ApiException('La operación no tiene ítems', 400);
        }
        $fecha = $fecha !== null && trim($fecha) !== '' ? trim($fecha) : self::nowIso();

        try {
            return SqliteConnector::transaction(static function (PDO $pdo) use ($tipoMovimiento, $documento, $items, $fecha) {
                $out = [];
                foreach ($items as $item) {
                    $out[] = self::aplicarLinea($pdo, $tipoMovimiento, $documento, $item, $fecha);
                }
                return $out;
            });
        } catch (\PDOException $e) {
            if (SqliteConnector::isBusy($e)) {
                throw new ApiException('Inventario ocupado (prod.db bloqueado). Reintente.', 503);
            }
            throw $e;
        }
    }

    /**
     * @param array{sku: string, cantidad: float, precio_unitario?: float} $item
     * @return array<string, mixed>
     */
    private static function aplicarLinea(PDO $pdo, string $tipo, string $documento, array $item, string $fecha): array
    {
        $sku = trim((string) ($item['sku'] ?? ''));
        $qty = (float) ($item['cantidad'] ?? 0);
        $price = (float) ($item['precio_unitario'] ?? 0);
        if ($sku === '' || $qty <= 0) {
            throw new ApiException('Ítem inválido: SKU y cantidad > 0', 400);
        }

        $stmt = $pdo->prepare('SELECT * FROM Product WHERE code = ? LIMIT 1');
        $stmt->execute([$sku]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw new ApiException('SKU no existe en inventario: ' . $sku, 404);
        }
        $product = Catalogo::mapProduct($row);
        if ($product === null) {
            throw new ApiException('Producto de inventario ilegible: ' . $sku, 500);
        }

        $stock = (float) $product['stock'];
        $cup = (float) $product['averageUnitCost'];
        if ($tipo === self::SALIDA && $stock + 1e-9 < $qty) {
            throw new ApiException('Stock insuficiente para ' . $sku . ' (disponible ' . $stock . ')', 409);
        }

        if ($tipo === self::ENTRADA) {
            $newCup = self::nuevoCostoPromedio($stock, $cup, $qty, $price);
            $newStock = $stock + $qty;
            $unitPrice = $price;
        } else {
            $newCup = $cup;
            $newStock = $stock - $qty;
            $unitPrice = $cup;
        }

        $now = self::nowIso();
        $upd = $pdo->prepare(
            'UPDATE Product SET stock = ?, averageUnitCost = ?, updatedAt = ? WHERE id = ?'
        );
        $upd->execute([$newStock, $newCup, $now, $product['id']]);

        $movId = self::newId();
        $ins = $pdo->prepare(
            'INSERT INTO Movement (id, type, documentNumber, productId, quantity, unitPrice, date, createdAt)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $ins->execute([$movId, $tipo, $documento, $product['id'], $qty, $unitPrice, $fecha, $now]);

        return [
            'movement_id' => $movId,
            'product_id' => $product['id'],
            'sku' => $sku,
            'type' => $tipo,
            'quantity' => $qty,
            'stock_after' => $newStock,
            'averageUnitCost' => $newCup,
        ];
    }
}
