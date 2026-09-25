<?php

declare(strict_types=1);

namespace Crm\Comex;

use Crm\ApiException;
use Crm\Database\Connection;
use Crm\Inventory\Catalogo;
use Crm\Inventory\StockSync;
use Crm\Storage\DocumentoPdf;
use Crm\Storage\ItemImagen;
use PDO;

/**
 * Operaciones de comercio exterior vinculadas a SKU de prod.db.
 * Confirmar sincroniza Movement ENTRADA/SALIDA en el SQLite compartido.
 */
final class Operaciones
{
    public const IMPORTACION = 'IMPORTACION';
    public const EXPORTACION = 'EXPORTACION';

    /**
     * @return list<array<string, mixed>>
     */
    public static function listar(): array
    {
        $rows = Connection::app()->query(
            'SELECT * FROM comex_operaciones ORDER BY id DESC'
        )->fetchAll(PDO::FETCH_ASSOC);
        $out = [];
        foreach ($rows as $row) {
            if (is_array($row)) {
                $out[] = self::hidratar((int) $row['id'], $row);
            }
        }
        return $out;
    }

    /** @return array<string, mixed>|null */
    public static function porId(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        $stmt = Connection::app()->prepare('SELECT * FROM comex_operaciones WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? self::hidratar($id, $row) : null;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function crear(array $data): array
    {
        $tipo = strtoupper(trim((string) ($data['tipo'] ?? '')));
        if ($tipo !== self::IMPORTACION && $tipo !== self::EXPORTACION) {
            throw new ApiException('tipo debe ser IMPORTACION o EXPORTACION', 400);
        }
        $items = $data['items'] ?? [];
        if (!is_array($items)) {
            throw new ApiException('Ítems inválidos', 400);
        }
        $folio = trim((string) ($data['folio'] ?? ''));
        if ($folio === '') {
            $folio = self::nuevoFolio($tipo);
        }
        $fecha = trim((string) ($data['fecha'] ?? date('Y-m-d')));
        $referencia = trim((string) ($data['referencia'] ?? ''));
        $now = crm_now();
        $pdo = Connection::app();
        $pdo->beginTransaction();
        try {
            $ins = $pdo->prepare(
                'INSERT INTO comex_operaciones (tipo, folio, estado, fecha, referencia, pdf_path, synced_at, movimiento_ids, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $ins->execute([$tipo, $folio, 'borrador', $fecha, $referencia, '', null, '', $now, $now]);
            $id = (int) $pdo->lastInsertId();
            if ($items !== []) {
                self::insertarItems($pdo, $id, $items);
            }
            Pipeline::sembrar($id, $tipo, $fecha);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
        $op = self::porId($id);
        if ($op === null) {
            throw new ApiException('No se pudo crear la operación', 500);
        }
        return $op;
    }

    /**
     * Confirma, escribe stock en prod.db y genera PDF + imágenes en uploads/.
     *
     * @return array<string, mixed>
     */
    public static function confirmar(int $id): array
    {
        $op = self::porId($id);
        if ($op === null) {
            throw new ApiException('Operación no encontrada', 404);
        }
        if ((string) $op['estado'] !== 'borrador') {
            throw new ApiException('Solo se confirma una operación en borrador', 409);
        }
        $items = $op['items'];
        if ($items === []) {
            throw new ApiException('La operación requiere ítems con SKU para confirmar', 400);
        }
        $lineas = [];
        foreach ($items as $it) {
            $lineas[] = [
                'sku' => (string) $it['sku'],
                'cantidad' => (float) $it['cantidad'],
                'precio_unitario' => (float) $it['precio_unitario'],
            ];
        }
        $movTipo = (string) $op['tipo'] === self::IMPORTACION ? StockSync::ENTRADA : StockSync::SALIDA;
        $movimientos = StockSync::aplicarDocumento($movTipo, (string) $op['folio'], $lineas, (string) $op['fecha']);

        foreach ($items as $it) {
            $sku = (string) $it['sku'];
            if (trim((string) ($it['imagen_path'] ?? '')) === '') {
                $path = ItemImagen::generarFicha($sku, (string) ($it['descripcion'] ?: $sku));
                $u = Connection::app()->prepare('UPDATE comex_operacion_items SET imagen_path = ? WHERE id = ?');
                $u->execute([$path, (int) $it['id']]);
            }
            $ficha = Fichas::porSku($sku);
            if ($ficha === null) {
                Fichas::guardar(['sku' => $sku]);
            }
        }

        $op = self::porId($id);
        if ($op === null) {
            throw new ApiException('Operación desapareció tras sincronizar', 500);
        }
        $pdf = DocumentoPdf::operacion($op);

        $now = crm_now();
        $ids = array_map(static fn (array $m) => $m['movement_id'], $movimientos);
        $upd = Connection::app()->prepare(
            'UPDATE comex_operaciones
             SET estado = ?, synced_at = ?, movimiento_ids = ?, pdf_path = ?, updated_at = ?
             WHERE id = ?'
        );
        $upd->execute(['confirmada', $now, json_encode($ids, JSON_UNESCAPED_UNICODE), $pdf, $now, $id]);

        $final = self::porId($id);
        if ($final === null) {
            throw new ApiException('No se pudo recargar la operación', 500);
        }
        $final['movimientos'] = $movimientos;
        return $final;
    }

    private static function nuevoFolio(string $tipo): string
    {
        $pref = $tipo === self::IMPORTACION ? 'IMP' : 'EXP';
        return $pref . '-' . date('Ymd-His');
    }

    /**
     * @param list<mixed> $items
     */
    private static function insertarItems(PDO $pdo, int $operacionId, array $items): void
    {
        $ins = $pdo->prepare(
            'INSERT INTO comex_operacion_items
                (operacion_id, ficha_id, sku, inventario_id, descripcion, cantidad, precio_unitario, imagen_path)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        foreach ($items as $raw) {
            if (!is_array($raw)) {
                throw new ApiException('Ítem inválido', 400);
            }
            $sku = trim((string) ($raw['sku'] ?? ''));
            $qty = (float) ($raw['cantidad'] ?? 0);
            if ($sku === '' || $qty <= 0) {
                throw new ApiException('Cada ítem necesita sku y cantidad > 0', 400);
            }
            $inv = Catalogo::porCodigo($sku);
            if ($inv === null) {
                throw new ApiException('SKU no existe en inventario: ' . $sku, 404);
            }
            $ficha = Fichas::porSku($sku);
            $desc = trim((string) ($raw['descripcion'] ?? ''));
            if ($desc === '') {
                $desc = is_array($ficha) ? (string) ($ficha['nombre'] ?? $inv['name']) : (string) $inv['name'];
            }
            $img = trim((string) ($raw['imagen_path'] ?? ''));
            if ($img === '' && is_array($ficha)) {
                $img = trim((string) ($ficha['imagen_path'] ?? ''));
            }
            $ins->execute([
                $operacionId,
                is_array($ficha) ? ($ficha['id'] ?? null) : null,
                $sku,
                $inv['id'],
                $desc,
                $qty,
                (float) ($raw['precio_unitario'] ?? 0),
                $img,
            ]);
        }
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function hidratar(int $id, array $row): array
    {
        $stmt = Connection::app()->prepare(
            'SELECT * FROM comex_operacion_items WHERE operacion_id = ? ORDER BY id ASC'
        );
        $stmt->execute([$id]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $row['items'] = is_array($items) ? $items : [];
        foreach ($row['items'] as $i => $it) {
            if (!is_array($it)) {
                continue;
            }
            $sku = (string) ($it['sku'] ?? '');
            $inv = Catalogo::porCodigo($sku);
            $row['items'][$i]['stock'] = $inv['stock'] ?? null;
            $row['items'][$i]['vinculado'] = $inv !== null;
        }
        return $row;
    }
}
