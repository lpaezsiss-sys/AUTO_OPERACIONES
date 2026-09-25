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
 * Confirmar sincroniza Movement ENTRADA/SALIDA en el SQLite compartido
 * solo para ítems de catálogo. Los SKU de evaluación (is_custom) se
 * prorratean en landed cost y se vinculan antes de Entrega/Cierre.
 */
final class Operaciones
{
    public const IMPORTACION = 'IMPORTACION';
    public const EXPORTACION = 'EXPORTACION';

    public const ORIGEN_INVENTARIO = 'inventario';
    public const ORIGEN_EVALUACION = 'evaluacion';

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
     * Agrega líneas a una operación existente (catálogo o evaluación).
     *
     * @param list<mixed> $items
     * @return array<string, mixed>
     */
    public static function agregarItems(int $id, array $items): array
    {
        $op = self::porId($id);
        if ($op === null) {
            throw new ApiException('Operación no encontrada', 404);
        }
        if ($items === []) {
            throw new ApiException('Indique al menos un ítem', 400);
        }
        $pdo = Connection::app();
        $pdo->beginTransaction();
        try {
            self::insertarItems($pdo, $id, $items);
            $pdo->prepare('UPDATE comex_operaciones SET updated_at = ? WHERE id = ?')->execute([crm_now(), $id]);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
        $final = self::porId($id);
        if ($final === null) {
            throw new ApiException('No se pudo recargar la operación', 500);
        }
        return $final;
    }

    /**
     * Vincula un SKU de evaluación al catálogo oficial (prod.db).
     *
     * @return array<string, mixed>
     */
    public static function vincularItem(int $itemId, string $skuOficial): array
    {
        $itemId = (int) $itemId;
        $skuOficial = trim($skuOficial);
        if ($itemId <= 0) {
            throw new ApiException('Ítem inválido', 400);
        }
        if ($skuOficial === '') {
            throw new ApiException('SKU oficial requerido', 400);
        }
        $inv = Catalogo::porCodigo($skuOficial);
        if ($inv === null) {
            throw new ApiException(
                'SKU oficial no existe en inventario: ' . $skuOficial . '. Créelo en el catálogo (prod.db) y luego vincule.',
                404
            );
        }
        $stmt = Connection::app()->prepare('SELECT * FROM comex_operacion_items WHERE id = ? LIMIT 1');
        $stmt->execute([$itemId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw new ApiException('Ítem no encontrado', 404);
        }
        if (!self::esPendienteCatalogo($row)) {
            throw new ApiException('El ítem ya está vinculado al catálogo oficial', 409);
        }
        $ficha = Fichas::porSku($skuOficial);
        if ($ficha === null) {
            $ficha = Fichas::guardar([
                'sku' => $skuOficial,
                'nombre' => (string) ($row['descripcion'] !== '' ? $row['descripcion'] : ($inv['name'] ?? $skuOficial)),
            ]);
        }
        $skuActual = (string) ($row['sku'] ?? '');
        $skuTemporal = trim((string) ($row['sku_temporal'] ?? ''));
        if ($skuActual !== $skuOficial) {
            $skuTemporal = $skuTemporal !== '' ? $skuTemporal : $skuActual;
        }
        $desc = trim((string) ($row['descripcion'] ?? ''));
        if ($desc === '') {
            $desc = (string) ($inv['name'] ?? $skuOficial);
        }
        $upd = Connection::app()->prepare(
            'UPDATE comex_operacion_items
             SET sku = ?, inventario_id = ?, ficha_id = ?, descripcion = ?, is_custom = 0, sku_temporal = ?
             WHERE id = ?'
        );
        $upd->execute([
            $skuOficial,
            $inv['id'],
            $ficha['id'] ?? null,
            $desc,
            $skuTemporal,
            $itemId,
        ]);
        $op = self::porId((int) $row['operacion_id']);
        if ($op === null) {
            throw new ApiException('Operación no encontrada', 404);
        }
        return $op;
    }

    /**
     * Confirma, escribe stock en prod.db (solo SKU de catálogo) y genera PDF.
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

        $movimientos = self::sincronizarStockItems($op, false);

        foreach ($items as $it) {
            if (!is_array($it)) {
                continue;
            }
            $sku = (string) $it['sku'];
            if (trim((string) ($it['imagen_path'] ?? '')) === '') {
                $path = ItemImagen::generarFicha($sku, (string) ($it['descripcion'] ?: $sku));
                $u = Connection::app()->prepare('UPDATE comex_operacion_items SET imagen_path = ? WHERE id = ?');
                $u->execute([$path, (int) $it['id']]);
            }
            if (self::esPendienteCatalogo($it)) {
                continue;
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
        $existentes = self::idsMovimiento((string) ($op['movimiento_ids'] ?? ''));
        $ids = array_values(array_unique(array_merge($existentes, $ids)));
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

    /**
     * ENTRADA/SALIDA de ítems ya vinculados que aún no tienen movimiento.
     *
     * @return list<array<string, mixed>>
     */
    public static function aplicarStockPendiente(int $operacionId): array
    {
        $op = self::porId($operacionId);
        if ($op === null) {
            throw new ApiException('Operación no encontrada', 404);
        }
        self::exigirCatalogoOficial($operacionId);
        $movimientos = self::sincronizarStockItems($op, true);
        if ($movimientos === []) {
            return [];
        }
        $ids = array_map(static fn (array $m) => $m['movement_id'], $movimientos);
        $merged = array_values(array_unique(array_merge(self::idsMovimiento((string) ($op['movimiento_ids'] ?? '')), $ids)));
        $upd = Connection::app()->prepare(
            'UPDATE comex_operaciones SET movimiento_ids = ?, synced_at = ?, updated_at = ? WHERE id = ?'
        );
        $upd->execute([json_encode($merged, JSON_UNESCAPED_UNICODE), crm_now(), crm_now(), $operacionId]);
        return $movimientos;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function itemsEvaluacionPendientes(int $operacionId): array
    {
        $op = self::porId($operacionId);
        if ($op === null) {
            throw new ApiException('Operación no encontrada', 404);
        }
        $out = [];
        foreach ($op['items'] as $it) {
            if (is_array($it) && self::esPendienteCatalogo($it)) {
                $out[] = $it;
            }
        }
        return $out;
    }

    public static function exigirCatalogoOficial(int $operacionId): void
    {
        $pend = self::itemsEvaluacionPendientes($operacionId);
        if ($pend === []) {
            return;
        }
        $skus = [];
        foreach ($pend as $it) {
            $skus[] = (string) ($it['sku'] ?? '');
        }
        throw new ApiException(
            'Hay SKUs de evaluación sin vincular al catálogo oficial: ' . implode(', ', $skus)
            . '. Créelos en inventario o vincúlelos en la pestaña Ítems antes de Entrega/Cierre y la ENTRADA a stock.',
            409,
            ['skus' => $skus, 'codigo' => 'SKU_EVALUACION']
        );
    }

    /** @param array<string, mixed> $item */
    public static function esPendienteCatalogo(array $item): bool
    {
        if ((int) ($item['is_custom'] ?? 0) === 1) {
            return true;
        }
        $origen = (string) ($item['origen'] ?? '');
        $invId = trim((string) ($item['inventario_id'] ?? ''));
        return $origen === self::ORIGEN_EVALUACION && $invId === '' && empty($item['vinculado']);
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
                (operacion_id, ficha_id, sku, inventario_id, descripcion, cantidad, precio_unitario, imagen_path,
                 origen, is_custom, sku_temporal, movimiento_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
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
            if (strlen($sku) > 64) {
                throw new ApiException('SKU demasiado largo (máx. 64)', 400);
            }
            $nombre = trim((string) ($raw['nombre'] ?? ''));
            $desc = trim((string) ($raw['descripcion'] ?? ''));
            if ($desc === '') {
                $desc = $nombre;
            }
            $inv = Catalogo::porCodigo($sku);

            if ($inv !== null) {
                $ficha = Fichas::porSku($sku);
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
                    self::ORIGEN_INVENTARIO,
                    0,
                    '',
                    '',
                ]);
                continue;
            }

            if ($desc === '') {
                throw new ApiException(
                    'El SKU ' . $sku . ' no está en el catálogo. Indique Nombre/Descripción para evaluarlo como ítem temporal.',
                    400
                );
            }
            $img = trim((string) ($raw['imagen_path'] ?? ''));
            $ins->execute([
                $operacionId,
                null,
                $sku,
                $inv['id'] ?? null,
                $desc,
                $qty,
                (float) ($raw['precio_unitario'] ?? 0),
                $img,
                self::ORIGEN_EVALUACION,
                1,
                $sku,
                '',
            ]);
        }
    }

    /**
     * @param array<string, mixed> $op
     * @return list<array<string, mixed>>
     */
    private static function sincronizarStockItems(array $op, bool $soloPendientes): array
    {
        $lineas = [];
        $itemIds = [];
        foreach ($op['items'] as $it) {
            if (!is_array($it)) {
                continue;
            }
            $mov = trim((string) ($it['movimiento_id'] ?? ''));
            if ($mov !== '') {
                continue;
            }
            if (self::esPendienteCatalogo($it)) {
                continue;
            }
            if ($soloPendientes && (string) ($it['origen'] ?? '') !== self::ORIGEN_EVALUACION) {
                continue;
            }
            $lineas[] = [
                'sku' => (string) $it['sku'],
                'cantidad' => (float) $it['cantidad'],
                'precio_unitario' => (float) $it['precio_unitario'],
            ];
            $itemIds[] = (int) $it['id'];
        }
        if ($lineas === []) {
            return [];
        }
        $movTipo = (string) $op['tipo'] === self::IMPORTACION ? StockSync::ENTRADA : StockSync::SALIDA;
        $movimientos = StockSync::aplicarDocumento($movTipo, (string) $op['folio'], $lineas, (string) $op['fecha']);
        $u = Connection::app()->prepare('UPDATE comex_operacion_items SET movimiento_id = ? WHERE id = ?');
        foreach ($movimientos as $j => $m) {
            if (!isset($itemIds[$j]) || !is_array($m)) {
                continue;
            }
            $u->execute([(string) ($m['movement_id'] ?? ''), $itemIds[$j]]);
        }
        return $movimientos;
    }

    /** @return list<string> */
    private static function idsMovimiento(string $json): array
    {
        $json = trim($json);
        if ($json === '') {
            return [];
        }
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return [];
        }
        $out = [];
        foreach ($decoded as $id) {
            $s = trim((string) $id);
            if ($s !== '') {
                $out[] = $s;
            }
        }
        return $out;
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
        $pendientes = 0;
        foreach ($row['items'] as $i => $it) {
            if (!is_array($it)) {
                continue;
            }
            $sku = (string) ($it['sku'] ?? '');
            $inv = Catalogo::porCodigo($sku);
            $origen = trim((string) ($it['origen'] ?? self::ORIGEN_INVENTARIO));
            if ($origen === '') {
                $origen = self::ORIGEN_INVENTARIO;
            }
            $isCustom = (int) ($it['is_custom'] ?? 0) === 1;
            if ($origen === self::ORIGEN_EVALUACION && trim((string) ($it['inventario_id'] ?? '')) === '' && $inv === null) {
                $isCustom = true;
            }
            $row['items'][$i]['origen'] = $origen;
            $row['items'][$i]['is_custom'] = $isCustom;
            $row['items'][$i]['sku_temporal'] = (string) ($it['sku_temporal'] ?? '');
            $row['items'][$i]['movimiento_id'] = (string) ($it['movimiento_id'] ?? '');
            $row['items'][$i]['stock'] = $inv['stock'] ?? null;
            $row['items'][$i]['vinculado'] = $inv !== null && !$isCustom;
            if ($isCustom) {
                $pendientes++;
            }
        }
        $row['items_evaluacion_pendientes'] = $pendientes;
        return $row;
    }
}
