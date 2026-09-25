<?php

declare(strict_types=1);

namespace Crm\Comex;

use Crm\Database\Connection;
use DateTimeImmutable;
use PDO;

/**
 * KPIs del dashboard principal (equivalente a src/app/(dashboard)/[tenantId]/dashboard).
 */
final class Dashboard
{
    /** @var list<string> */
    private const MESES_ES = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

    /**
     * @return array<string, mixed>
     */
    public static function kpis(): array
    {
        $board = Pipeline::tablero();
        $total = (int) ($board['kpis']['operaciones'] ?? 0);
        $cerradas = (int) ($board['kpis']['completadas'] ?? 0);
        $activas = max(0, $total - $cerradas);
        $alertas = (int) ($board['kpis']['atrasadas'] ?? 0);

        $volumen = self::meses(12);
        foreach ($board['operaciones'] as $t) {
            if (!is_array($t)) {
                continue;
            }
            $ym = substr((string) ($t['fecha'] ?? ''), 0, 7);
            if ($ym !== '' && isset($volumen[$ym])) {
                $volumen[$ym]['cantidad']++;
            }
        }

        $ciclo = self::cicloPromedio();
        $costo = self::costoPromedio();

        return [
            'operaciones_activas' => $activas,
            'operaciones_cerradas' => $cerradas,
            'operaciones_totales' => $total,
            'ciclo_promedio_dias' => $ciclo['dias'],
            'ciclo_fuente' => $ciclo['fuente'],
            'ciclo_muestra' => $ciclo['muestra'],
            'costo_promedio_embarque_clp' => $costo['clp'],
            'costo_muestra' => $costo['muestra'],
            'alertas_retraso' => $alertas,
            'bloqueadas' => (int) ($board['kpis']['bloqueadas'] ?? 0),
            'volumen_mensual' => array_values($volumen),
        ];
    }

    /**
     * @return array{dias: float|null, fuente: string, muestra: int}
     */
    private static function cicloPromedio(): array
    {
        $pdo = Connection::app();
        $stmt = $pdo->query(
            "SELECT o.fecha, o.created_at, e.fecha_real, e.updated_at
             FROM comex_operacion_etapas e
             INNER JOIN comex_operaciones o ON o.id = e.operacion_id
             WHERE e.codigo = 'CIERRE' AND e.estado = 'COMPLETED'"
        );
        $rows = $stmt !== false ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        $dias = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $ini = self::soloFecha((string) ($row['fecha'] ?: $row['created_at']));
            $fin = self::soloFecha((string) ($row['fecha_real'] ?: $row['updated_at']));
            if ($ini === null || $fin === null) {
                continue;
            }
            $dias[] = (int) $ini->diff($fin)->days;
        }
        if ($dias !== []) {
            return [
                'dias' => round(array_sum($dias) / count($dias), 1),
                'fuente' => 'cierre',
                'muestra' => count($dias),
            ];
        }

        $abiertas = $pdo->query(
            "SELECT o.fecha, o.created_at
             FROM comex_operaciones o
             WHERE NOT EXISTS (
                SELECT 1 FROM comex_operacion_etapas e
                WHERE e.operacion_id = o.id AND e.codigo = 'CIERRE' AND e.estado = 'COMPLETED'
             )"
        );
        $openRows = $abiertas !== false ? $abiertas->fetchAll(PDO::FETCH_ASSOC) : [];
        $elapsed = [];
        $hoy = new DateTimeImmutable('today');
        foreach (is_array($openRows) ? $openRows : [] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $ini = self::soloFecha((string) ($row['fecha'] ?: $row['created_at']));
            if ($ini === null) {
                continue;
            }
            $elapsed[] = (int) $ini->diff($hoy)->days;
        }
        if ($elapsed === []) {
            return ['dias' => null, 'fuente' => 'n/d', 'muestra' => 0];
        }
        return [
            'dias' => round(array_sum($elapsed) / count($elapsed), 1),
            'fuente' => 'abiertas',
            'muestra' => count($elapsed),
        ];
    }

    /**
     * @return array{clp: float, muestra: int}
     */
    private static function costoPromedio(): array
    {
        $pdo = Connection::app();
        $sql = 'SELECT lc.operacion_id, lc.version, COALESCE(SUM(li.landed_total_clp), 0) AS landed_clp
                FROM comex_landed_cost lc
                LEFT JOIN comex_landed_items li ON li.landed_id = lc.id
                GROUP BY lc.operacion_id, lc.version';
        $stmt = $pdo->query($sql);
        $rows = $stmt !== false ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        $byOp = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $opId = (int) $row['operacion_id'];
            $ver = strtoupper((string) $row['version']);
            $clp = (float) $row['landed_clp'];
            if ($ver === LandedCost::VERSION_REAL) {
                $byOp[$opId] = $clp;
            } elseif (!isset($byOp[$opId])) {
                $byOp[$opId] = $clp;
            }
        }
        $vals = array_values(array_filter($byOp, static fn (float $v): bool => $v > 0));
        if ($vals === []) {
            return ['clp' => 0.0, 'muestra' => 0];
        }
        return [
            'clp' => round(array_sum($vals) / count($vals), 0),
            'muestra' => count($vals),
        ];
    }

    /**
     * @return array<string, array{mes: string, label: string, cantidad: int}>
     */
    private static function meses(int $n): array
    {
        $out = [];
        $cursor = new DateTimeImmutable('first day of this month');
        for ($i = $n - 1; $i >= 0; $i--) {
            $m = $cursor->modify('-' . $i . ' months');
            $key = $m->format('Y-m');
            $out[$key] = [
                'mes' => $key,
                'label' => self::MESES_ES[(int) $m->format('n') - 1] . ' ' . $m->format('Y'),
                'cantidad' => 0,
            ];
        }
        return $out;
    }

    private static function soloFecha(string $raw): ?DateTimeImmutable
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        $s = substr($raw, 0, 10);
        $dt = DateTimeImmutable::createFromFormat('Y-m-d', $s);
        return $dt instanceof DateTimeImmutable ? $dt : null;
    }
}
