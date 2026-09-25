<?php

declare(strict_types=1);

namespace Crm\Comex;

use Crm\ApiException;
use Crm\Database\Connection;
use Crm\Inventory\Catalogo;
use Crm\Inventory\InventarioStock;
use Crm\Storage\ItemImagen;
use PDO;

/**
 * Fichas COMEX vinculadas al SKU de inventario (Product.code / Product.id).
 */
final class Fichas
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function listar(): array
    {
        $pdo = Connection::app();
        $rows = $pdo->query(
            'SELECT * FROM comex_fichas ORDER BY nombre ASC'
        )->fetchAll(PDO::FETCH_ASSOC);
        $out = [];
        foreach ($rows as $row) {
            if (is_array($row)) {
                $out[] = self::hidratar($row);
            }
        }
        return $out;
    }

    /** @return array<string, mixed>|null */
    public static function porSku(string $sku): ?array
    {
        $sku = trim($sku);
        if ($sku === '') {
            return null;
        }
        $stmt = Connection::app()->prepare('SELECT * FROM comex_fichas WHERE sku = ? LIMIT 1');
        $stmt->execute([$sku]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? self::hidratar($row) : null;
    }

    /** @return array<string, mixed>|null */
    public static function porId(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        $stmt = Connection::app()->prepare('SELECT * FROM comex_fichas WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? self::hidratar($row) : null;
    }

    /**
     * Alta/actualización local. El stock siempre se lee de prod.db.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function guardar(array $data): array
    {
        $sku = trim((string) ($data['sku'] ?? ''));
        if ($sku === '') {
            throw new ApiException('SKU requerido', 400);
        }
        $inv = Catalogo::porCodigo($sku);
        $nombre = trim((string) ($data['nombre'] ?? ''));
        if ($nombre === '') {
            $nombre = $inv['name'] ?? $sku;
        }
        $now = crm_now();
        $pdo = Connection::app();
        $existente = self::porSku($sku);

        $fields = [
            'sku' => $sku,
            'inventario_id' => $inv['id'] ?? ($existente['inventario_id'] ?? null),
            'nombre' => $nombre,
            'descripcion' => (string) ($data['descripcion'] ?? $inv['description'] ?? ($existente['descripcion'] ?? '')),
            'partida_arancelaria' => (string) ($data['partida_arancelaria'] ?? ($existente['partida_arancelaria'] ?? '')),
            'origen_pais' => (string) ($data['origen_pais'] ?? ($existente['origen_pais'] ?? '')),
            'unidad' => (string) ($data['unidad'] ?? ($existente['unidad'] ?? 'UN')),
            'imagen_path' => (string) ($data['imagen_path'] ?? ($existente['imagen_path'] ?? '')),
            'synced_at' => $inv !== null ? $now : ($existente['synced_at'] ?? null),
            'updated_at' => $now,
        ];

        if ($existente === null) {
            $stmt = $pdo->prepare(
                'INSERT INTO comex_fichas
                    (sku, inventario_id, nombre, descripcion, partida_arancelaria, origen_pais, unidad, imagen_path, synced_at, created_at, updated_at)
                 VALUES (:sku, :inventario_id, :nombre, :descripcion, :partida_arancelaria, :origen_pais, :unidad, :imagen_path, :synced_at, :created_at, :updated_at)'
            );
            $stmt->execute($fields + ['created_at' => $now]);
        } else {
            $stmt = $pdo->prepare(
                'UPDATE comex_fichas SET
                    inventario_id = :inventario_id,
                    nombre = :nombre,
                    descripcion = :descripcion,
                    partida_arancelaria = :partida_arancelaria,
                    origen_pais = :origen_pais,
                    unidad = :unidad,
                    imagen_path = :imagen_path,
                    synced_at = :synced_at,
                    updated_at = :updated_at
                 WHERE sku = :sku'
            );
            $stmt->execute($fields);
        }

        $ficha = self::porSku($sku);
        if ($ficha === null) {
            throw new ApiException('No se pudo persistir la ficha', 500);
        }
        if (($ficha['imagen_path'] ?? '') === '') {
            $path = ItemImagen::generarFicha($sku, (string) $ficha['nombre']);
            $upd = $pdo->prepare('UPDATE comex_fichas SET imagen_path = ?, updated_at = ? WHERE sku = ?');
            $upd->execute([$path, $now, $sku]);
            $ficha['imagen_path'] = $path;
        }
        return $ficha;
    }

    /**
     * Copia/actualiza fichas desde Product de inventario. No pisa partida arancelaria ni imagen locales.
     *
     * @return array{created: int, updated: int, total: int}
     */
    public static function sincronizarDesdeInventario(?string $sku = null): array
    {
        $productos = $sku !== null && trim($sku) !== ''
            ? array_values(array_filter([Catalogo::porCodigo($sku)]))
            : Catalogo::listar(1000);
        if ($productos === [] && $sku !== null && trim($sku) !== '') {
            throw new ApiException('SKU no encontrado en prod.db', 404);
        }
        $created = 0;
        $updated = 0;
        foreach ($productos as $p) {
            $antes = self::porSku((string) $p['code']);
            $descLocal = is_array($antes) ? trim((string) ($antes['descripcion'] ?? '')) : '';
            self::guardar([
                'sku' => $p['code'],
                'nombre' => $antes['nombre'] ?? $p['name'],
                'descripcion' => $descLocal !== '' ? $descLocal : $p['description'],
                'partida_arancelaria' => $antes['partida_arancelaria'] ?? '',
                'origen_pais' => $antes['origen_pais'] ?? '',
                'unidad' => $antes['unidad'] ?? 'UN',
                'imagen_path' => $antes['imagen_path'] ?? '',
            ]);
            if ($antes === null) {
                $created++;
            } else {
                $updated++;
            }
        }
        return ['created' => $created, 'updated' => $updated, 'total' => count($productos)];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function hidratar(array $row): array
    {
        $sku = (string) ($row['sku'] ?? '');
        $inv = $sku !== '' ? Catalogo::porCodigo($sku) : null;
        $row['stock'] = $inv['stock'] ?? InventarioStock::stockPorCodigo($sku);
        $row['averageUnitCost'] = $inv['averageUnitCost'] ?? null;
        $row['inventario'] = $inv;
        $row['vinculado'] = $inv !== null;
        return $row;
    }
}
