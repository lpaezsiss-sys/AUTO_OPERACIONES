<?php

declare(strict_types=1);

namespace Crm\Comex;

use Crm\ApiException;
use Crm\Database\Connection;
use Crm\Storage\PlanillaPdf;
use PDO;

/**
 * Persistencia Estimada vs Real de landed cost por operación COMEX.
 */
final class LandedCostStore
{
    /**
     * @return array<string, mixed>
     */
    public static function paraOperacion(int $operacionId): array
    {
        $op = Operaciones::porId($operacionId);
        if ($op === null) {
            throw new ApiException('Operación no encontrada', 404);
        }
        $estimada = self::porVersion($operacionId, LandedCost::VERSION_ESTIMADA);
        $real = self::porVersion($operacionId, LandedCost::VERSION_REAL);
        return [
            'operacion' => $op,
            'catalogo_gastos' => LandedCost::GASTOS_CATALOGO,
            'estimada' => $estimada,
            'real' => $real,
            'comparacion' => LandedCost::comparar(
                is_array($estimada) ? ($estimada['calculo'] ?? null) : null,
                is_array($real) ? ($real['calculo'] ?? null) : null
            ),
        ];
    }

    /** @return array<string, mixed>|null */
    public static function porId(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        $stmt = Connection::app()->prepare('SELECT * FROM comex_landed_cost WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? self::hidratar($row) : null;
    }

    /** @return array<string, mixed>|null */
    public static function porVersion(int $operacionId, string $version): ?array
    {
        $stmt = Connection::app()->prepare(
            'SELECT * FROM comex_landed_cost WHERE operacion_id = ? AND version = ? LIMIT 1'
        );
        $stmt->execute([$operacionId, strtoupper($version)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? self::hidratar($row) : null;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function calcularDesde(array $data): array
    {
        $opId = (int) ($data['operacion_id'] ?? 0);
        $op = Operaciones::porId($opId);
        if ($op === null) {
            throw new ApiException('Operación no encontrada', 404);
        }
        $items = self::mezclarItems($op['items'] ?? [], $data['items'] ?? []);
        return LandedCost::calcular([
            'version' => $data['version'] ?? LandedCost::VERSION_ESTIMADA,
            'moneda_origen' => $data['moneda_origen'] ?? 'USD',
            'tipo_cambio_usd' => $data['tipo_cambio_usd'] ?? 0,
            'tipo_cambio_eur' => $data['tipo_cambio_eur'] ?? 0,
            'iva_pct' => $data['iva_pct'] ?? null,
            'gastos' => $data['gastos'] ?? LandedCost::gastosCero(),
            'items' => $items,
        ]);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function guardar(array $data): array
    {
        $opId = (int) ($data['operacion_id'] ?? 0);
        $calculo = self::calcularDesde($data);
        $version = (string) $calculo['version'];
        $now = crm_now();
        $pdo = Connection::app();
        $existente = self::porVersion($opId, $version);
        $notas = trim((string) ($data['notas'] ?? ''));

        $pdo->beginTransaction();
        try {
            if ($existente === null) {
                $ins = $pdo->prepare(
                    'INSERT INTO comex_landed_cost
                        (operacion_id, version, moneda_origen, tipo_cambio_usd, tipo_cambio_eur, iva_pct, notas, pdf_path, created_at, updated_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $ins->execute([
                    $opId,
                    $version,
                    $calculo['moneda_origen'],
                    $calculo['tipo_cambio_usd'],
                    $calculo['tipo_cambio_eur'],
                    $calculo['iva_pct'],
                    $notas,
                    '',
                    $now,
                    $now,
                ]);
                $id = (int) $pdo->lastInsertId();
            } else {
                $id = (int) $existente['id'];
                $upd = $pdo->prepare(
                    'UPDATE comex_landed_cost SET
                        moneda_origen = ?, tipo_cambio_usd = ?, tipo_cambio_eur = ?, iva_pct = ?, notas = ?, updated_at = ?
                     WHERE id = ?'
                );
                $upd->execute([
                    $calculo['moneda_origen'],
                    $calculo['tipo_cambio_usd'],
                    $calculo['tipo_cambio_eur'],
                    $calculo['iva_pct'],
                    $notas,
                    $now,
                    $id,
                ]);
                $pdo->prepare('DELETE FROM comex_landed_gastos WHERE landed_id = ?')->execute([$id]);
                $pdo->prepare('DELETE FROM comex_landed_items WHERE landed_id = ?')->execute([$id]);
            }

            $gIns = $pdo->prepare(
                'INSERT INTO comex_landed_gastos (landed_id, codigo, nombre, ambito, moneda, monto, monto_clp, cif)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            foreach ($calculo['gastos'] as $g) {
                $gIns->execute([
                    $id,
                    $g['codigo'],
                    $g['nombre'],
                    $g['ambito'],
                    $g['moneda'],
                    $g['monto'],
                    $g['monto_clp'],
                    $g['cif'] ? 1 : 0,
                ]);
            }
            $iIns = $pdo->prepare(
                'INSERT INTO comex_landed_items
                    (landed_id, operacion_item_id, sku, descripcion, cantidad, fob_unitario, fob_origen, fob_clp, share,
                     gastos_cif_clp, cif_clp, iva_clp, gastos_locales_clp, landed_unitario_clp, landed_total_clp)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            foreach ($calculo['items'] as $it) {
                $iIns->execute([
                    $id,
                    $it['operacion_item_id'],
                    $it['sku'],
                    $it['descripcion'],
                    $it['cantidad'],
                    $it['fob_unitario'],
                    $it['fob_origen'],
                    $it['fob_clp'],
                    $it['share'],
                    $it['gastos_cif_clp'],
                    $it['cif_clp'],
                    $it['iva_clp'],
                    $it['gastos_locales_clp'],
                    $it['landed_unitario_clp'],
                    $it['landed_total_clp'],
                ]);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        $row = self::porId($id);
        if ($row === null) {
            throw new ApiException('No se pudo persistir la evaluación', 500);
        }
        return $row;
    }

    /**
     * @return array<string, mixed>
     */
    public static function exportarPdf(int $id): array
    {
        $row = self::porId($id);
        if ($row === null) {
            throw new ApiException('Evaluación no encontrada', 404);
        }
        $op = Operaciones::porId((int) $row['operacion_id']);
        $pack = self::paraOperacion((int) $row['operacion_id']);
        $path = PlanillaPdf::evaluacion($row, is_array($op) ? $op : [], $pack['comparacion'] ?? []);
        $upd = Connection::app()->prepare(
            'UPDATE comex_landed_cost SET pdf_path = ?, updated_at = ? WHERE id = ?'
        );
        $upd->execute([$path, crm_now(), $id]);
        $row['pdf_path'] = $path;
        $row['calculo']['pdf_path'] = $path;
        return $row;
    }

    /**
     * @param list<mixed> $opItems
     * @param list<mixed> $overrides
     * @return list<array<string, mixed>>
     */
    private static function mezclarItems(array $opItems, array $overrides): array
    {
        $byId = [];
        $bySku = [];
        foreach ($overrides as $ov) {
            if (!is_array($ov)) {
                continue;
            }
            if (isset($ov['operacion_item_id']) || isset($ov['id'])) {
                $byId[(int) ($ov['operacion_item_id'] ?? $ov['id'])] = $ov;
            }
            if (isset($ov['sku'])) {
                $bySku[(string) $ov['sku']] = $ov;
            }
        }
        $out = [];
        foreach ($opItems as $it) {
            if (!is_array($it)) {
                continue;
            }
            $id = (int) ($it['id'] ?? 0);
            $sku = (string) ($it['sku'] ?? '');
            $ov = $byId[$id] ?? $bySku[$sku] ?? [];
            $out[] = [
                'id' => $id,
                'sku' => $sku,
                'descripcion' => (string) ($ov['descripcion'] ?? $it['descripcion'] ?? ''),
                'cantidad' => $ov['cantidad'] ?? $it['cantidad'] ?? 0,
                'fob_unitario' => $ov['fob_unitario'] ?? $ov['precio_unitario'] ?? $it['precio_unitario'] ?? 0,
            ];
        }
        return $out;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function hidratar(array $row): array
    {
        $id = (int) $row['id'];
        $g = Connection::app()->prepare('SELECT * FROM comex_landed_gastos WHERE landed_id = ? ORDER BY id ASC');
        $g->execute([$id]);
        $gastos = $g->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($gastos as $i => $gr) {
            if (is_array($gr)) {
                $gastos[$i]['cif'] = (int) ($gr['cif'] ?? 0) === 1;
            }
        }
        $it = Connection::app()->prepare('SELECT * FROM comex_landed_items WHERE landed_id = ? ORDER BY id ASC');
        $it->execute([$id]);
        $items = $it->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $totales = [
            'fob_origen' => 0.0,
            'fob_clp' => 0.0,
            'gastos_origen_clp' => 0.0,
            'gastos_cif_clp' => 0.0,
            'cif_clp' => 0.0,
            'iva_clp' => 0.0,
            'gastos_locales_clp' => 0.0,
            'landed_clp' => 0.0,
        ];
        foreach ($items as $linea) {
            if (!is_array($linea)) {
                continue;
            }
            $totales['fob_origen'] += (float) $linea['fob_origen'];
            $totales['fob_clp'] += (float) $linea['fob_clp'];
            $totales['gastos_cif_clp'] += (float) $linea['gastos_cif_clp'];
            $totales['cif_clp'] += (float) $linea['cif_clp'];
            $totales['iva_clp'] += (float) $linea['iva_clp'];
            $totales['gastos_locales_clp'] += (float) $linea['gastos_locales_clp'];
            $totales['landed_clp'] += (float) $linea['landed_total_clp'];
        }
        foreach ($gastos as $gr) {
            if (is_array($gr) && (string) ($gr['ambito'] ?? '') === 'ORIGEN') {
                $totales['gastos_origen_clp'] += (float) ($gr['monto_clp'] ?? 0);
            }
        }
        foreach ($totales as $k => $v) {
            $totales[$k] = round((float) $v, 4);
        }
        $row['gastos'] = $gastos;
        $row['items'] = $items;
        $row['calculo'] = [
            'version' => $row['version'],
            'moneda_origen' => $row['moneda_origen'],
            'tipo_cambio_usd' => (float) $row['tipo_cambio_usd'],
            'tipo_cambio_eur' => (float) $row['tipo_cambio_eur'],
            'iva_pct' => (float) $row['iva_pct'],
            'gastos' => $gastos,
            'items' => $items,
            'totales' => $totales,
            'notas' => $row['notas'] ?? '',
            'pdf_path' => $row['pdf_path'] ?? '',
        ];
        return $row;
    }
}
