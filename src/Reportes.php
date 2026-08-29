<?php

declare(strict_types=1);

namespace Crm;

use PDO;

final class Reportes
{
    /**
     * @param array $filtros
     * @return array
     */
    public static function filtros(array $filtros = array())
    {
        $desde = crm_str(isset($filtros['desde']) ? $filtros['desde'] : '', 10);
        $hasta = crm_str(isset($filtros['hasta']) ? $filtros['hasta'] : '', 10);
        if ($desde === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) {
            $desde = date('Y-m-01');
        }
        if ($hasta === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) {
            $hasta = date('Y-m-d');
        }
        if ($desde > $hasta) {
            $tmp = $desde;
            $desde = $hasta;
            $hasta = $tmp;
        }
        return array(
            'desde' => $desde,
            'hasta' => $hasta,
            'vendedor_id' => crm_int(isset($filtros['vendedor_id']) ? $filtros['vendedor_id'] : 0, 0),
        );
    }

    /**
     * @param string $tipo
     * @param array $filtros
     * @return array
     */
    public static function obtener($tipo, array $filtros = array())
    {
        $tipo = crm_str($tipo, 40);
        $f = self::filtros($filtros);
        if ($tipo === 'resumen_kpis') {
            return self::resumenKpis($f);
        }
        if ($tipo === 'pipeline') {
            return self::pipeline($f);
        }
        if ($tipo === 'vendedores') {
            return self::vendedores($f);
        }
        if ($tipo === 'productos_top') {
            return self::productosTop($f);
        }
        Http::fail('Tipo de reporte inválido');
        return array();
    }

    /**
     * @param array $f
     * @return array
     */
    public static function resumenKpis(array $f)
    {
        $pdo = crm_pdo();
        $f = self::filtros($f);
        $where = self::whereCotizaciones($f);
        $sql = 'SELECT
                    COUNT(*) AS n_cotizado,
                    COALESCE(SUM(total), 0) AS monto_cotizado,
                    SUM(CASE WHEN estado = \'aceptada\' THEN 1 ELSE 0 END) AS n_ganado,
                    COALESCE(SUM(CASE WHEN estado = \'aceptada\' THEN total ELSE 0 END), 0) AS ventas_ganadas
                FROM crm_cotizaciones c
                WHERE ' . $where['sql'];
        $stmt = $pdo->prepare($sql);
        $stmt->execute($where['params']);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            $row = array();
        }
        $montoCotizado = round((float) (isset($row['monto_cotizado']) ? $row['monto_cotizado'] : 0), 2);
        $ventasGanadas = round((float) (isset($row['ventas_ganadas']) ? $row['ventas_ganadas'] : 0), 2);
        $nCotizado = (int) (isset($row['n_cotizado']) ? $row['n_cotizado'] : 0);
        $nGanado = (int) (isset($row['n_ganado']) ? $row['n_ganado'] : 0);
        $conversion = $montoCotizado > 0 ? round(($ventasGanadas / $montoCotizado) * 100, 2) : 0.0;

        $comWhere = self::whereComisiones($f);
        $comSql = 'SELECT COALESCE(SUM(monto_comision), 0) FROM crm_comisiones cm
                   INNER JOIN crm_cotizaciones c ON c.id = cm.cotizacion_id
                   WHERE cm.estado <> \'anulada\' AND ' . $comWhere['sql'];
        $comStmt = $pdo->prepare($comSql);
        $comStmt->execute($comWhere['params']);
        $comisiones = round((float) $comStmt->fetchColumn(), 2);

        return array(
            'tipo' => 'resumen_kpis',
            'filtros' => $f,
            'kpis' => array(
                'monto_cotizado' => $montoCotizado,
                'ventas_ganadas' => $ventasGanadas,
                'conversion_pct' => $conversion,
                'comisiones' => $comisiones,
                'n_cotizado' => $nCotizado,
                'n_ganado' => $nGanado,
            ),
        );
    }

    /**
     * @param array $f
     * @return array
     */
    public static function pipeline(array $f)
    {
        $pdo = crm_pdo();
        $f = self::filtros($f);
        $map = array(
            'prospecto' => 'lead',
            'calificacion' => 'lead',
            'propuesta' => 'cotizacion',
            'negociacion' => 'negociacion',
            'ganada' => 'ganado',
            'perdida' => 'perdido',
        );
        $orden = array('lead', 'cotizacion', 'negociacion', 'ganado', 'perdido');
        $labels = array(
            'lead' => 'Lead',
            'cotizacion' => 'Cotización',
            'negociacion' => 'Negociación',
            'ganado' => 'Ganado',
            'perdido' => 'Perdido',
        );
        $out = array();
        foreach ($orden as $key) {
            $out[$key] = array(
                'etapa' => $key,
                'label' => $labels[$key],
                'cantidad' => 0,
                'monto' => 0.0,
            );
        }

        $sql = 'SELECT o.etapa, COUNT(*) AS cantidad, COALESCE(SUM(o.valor_estimado), 0) AS monto
                FROM crm_oportunidades o';
        $params = array();
        $conds = array('o.created_at >= ?', 'o.created_at < ?');
        $params[] = $f['desde'] . ' 00:00:00';
        $params[] = self::diaSiguiente($f['hasta']) . ' 00:00:00';
        if ($f['vendedor_id'] > 0) {
            $sql .= ' LEFT JOIN crm_vendedores v ON v.usuario_id = o.ejecutivo_id';
            $conds[] = 'v.id = ?';
            $params[] = $f['vendedor_id'];
        }
        $sql .= ' WHERE ' . implode(' AND ', $conds) . ' GROUP BY o.etapa';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!is_array($rows)) {
            $rows = array();
        }
        foreach ($rows as $row) {
            $et = (string) $row['etapa'];
            $key = isset($map[$et]) ? $map[$et] : '';
            if ($key === '' || !isset($out[$key])) {
                continue;
            }
            $out[$key]['cantidad'] += (int) $row['cantidad'];
            $out[$key]['monto'] += round((float) $row['monto'], 2);
        }

        $etapas = array();
        foreach ($orden as $key) {
            $out[$key]['monto'] = round((float) $out[$key]['monto'], 2);
            $etapas[] = $out[$key];
        }

        return array(
            'tipo' => 'pipeline',
            'filtros' => $f,
            'etapas' => $etapas,
        );
    }

    /**
     * @param array $f
     * @return array
     */
    public static function vendedores(array $f)
    {
        $pdo = crm_pdo();
        $f = self::filtros($f);
        $vendedores = $pdo->query(
            'SELECT id, nombre_completo, email, comision_porcentaje, activo
             FROM crm_vendedores
             ORDER BY nombre_completo ASC'
        )->fetchAll(PDO::FETCH_ASSOC);
        if (!is_array($vendedores)) {
            $vendedores = array();
        }

        $where = self::whereCotizaciones(array('desde' => $f['desde'], 'hasta' => $f['hasta'], 'vendedor_id' => 0));
        $sql = 'SELECT c.vendedor_id,
                       COUNT(*) AS n_cotizado,
                       COALESCE(SUM(c.total), 0) AS total_cotizado,
                       SUM(CASE WHEN c.estado = \'aceptada\' THEN 1 ELSE 0 END) AS n_cerrado,
                       COALESCE(SUM(CASE WHEN c.estado = \'aceptada\' THEN c.total ELSE 0 END), 0) AS total_cerrado
                FROM crm_cotizaciones c
                WHERE ' . $where['sql'] . '
                  AND c.vendedor_id IS NOT NULL
                GROUP BY c.vendedor_id';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($where['params']);
        $porVend = array();
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $porVend[(int) $row['vendedor_id']] = $row;
        }

        $comWhere = self::whereComisiones(array('desde' => $f['desde'], 'hasta' => $f['hasta'], 'vendedor_id' => 0));
        $comSql = 'SELECT cm.vendedor_id, COALESCE(SUM(cm.monto_comision), 0) AS comisiones
                   FROM crm_comisiones cm
                   INNER JOIN crm_cotizaciones c ON c.id = cm.cotizacion_id
                   WHERE cm.estado <> \'anulada\' AND ' . $comWhere['sql'] . '
                   GROUP BY cm.vendedor_id';
        $comStmt = $pdo->prepare($comSql);
        $comStmt->execute($comWhere['params']);
        $comPorVend = array();
        foreach ($comStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $comPorVend[(int) $row['vendedor_id']] = round((float) $row['comisiones'], 2);
        }

        $ranking = array();
        foreach ($vendedores as $v) {
            $id = (int) $v['id'];
            if ($f['vendedor_id'] > 0 && $id !== $f['vendedor_id']) {
                continue;
            }
            $agg = isset($porVend[$id]) ? $porVend[$id] : array();
            $totalCot = round((float) (isset($agg['total_cotizado']) ? $agg['total_cotizado'] : 0), 2);
            $totalCer = round((float) (isset($agg['total_cerrado']) ? $agg['total_cerrado'] : 0), 2);
            $nCot = (int) (isset($agg['n_cotizado']) ? $agg['n_cotizado'] : 0);
            $nCer = (int) (isset($agg['n_cerrado']) ? $agg['n_cerrado'] : 0);
            $tasa = $nCot > 0 ? round(($nCer / $nCot) * 100, 2) : 0.0;
            $ranking[] = array(
                'vendedor_id' => $id,
                'nombre' => (string) $v['nombre_completo'],
                'email' => (string) $v['email'],
                'n_cotizado' => $nCot,
                'total_cotizado' => $totalCot,
                'n_cerrado' => $nCer,
                'total_cerrado' => $totalCer,
                'tasa_cierre_pct' => $tasa,
                'comisiones' => isset($comPorVend[$id]) ? $comPorVend[$id] : 0.0,
            );
        }

        usort($ranking, static function ($a, $b) {
            if ($a['total_cerrado'] === $b['total_cerrado']) {
                if ($a['total_cotizado'] === $b['total_cotizado']) {
                    return 0;
                }
                return ($a['total_cotizado'] < $b['total_cotizado']) ? 1 : -1;
            }
            return ($a['total_cerrado'] < $b['total_cerrado']) ? 1 : -1;
        });

        return array(
            'tipo' => 'vendedores',
            'filtros' => $f,
            'vendedores' => $ranking,
        );
    }

    /**
     * @param array $f
     * @return array
     */
    public static function productosTop(array $f)
    {
        $pdo = crm_pdo();
        $f = self::filtros($f);
        $where = self::whereCotizaciones($f);
        $sql = 'SELECT i.tipo_item, i.codigo, i.descripcion,
                       COALESCE(SUM(i.cantidad), 0) AS cantidad,
                       COALESCE(SUM(i.subtotal), 0) AS monto,
                       COUNT(*) AS lineas
                FROM crm_cotizacion_items i
                INNER JOIN crm_cotizaciones c ON c.id = i.cotizacion_id
                WHERE ' . $where['sql'] . '
                GROUP BY i.tipo_item, i.codigo, i.descripcion
                ORDER BY monto DESC
                LIMIT 10';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($where['params']);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!is_array($items)) {
            $items = array();
        }
        $top = array();
        foreach ($items as $row) {
            $top[] = array(
                'tipo_item' => (string) $row['tipo_item'],
                'codigo' => (string) $row['codigo'],
                'descripcion' => (string) $row['descripcion'],
                'cantidad' => round((float) $row['cantidad'], 2),
                'monto' => round((float) $row['monto'], 2),
                'lineas' => (int) $row['lineas'],
            );
        }

        $propSql = 'SELECT i.tipo_item, COALESCE(SUM(i.subtotal), 0) AS monto, COALESCE(SUM(i.cantidad), 0) AS cantidad
                    FROM crm_cotizacion_items i
                    INNER JOIN crm_cotizaciones c ON c.id = i.cotizacion_id
                    WHERE ' . $where['sql'] . '
                    GROUP BY i.tipo_item';
        $propStmt = $pdo->prepare($propSql);
        $propStmt->execute($where['params']);
        $proporcion = array(
            'producto' => array('monto' => 0.0, 'cantidad' => 0.0),
            'servicio' => array('monto' => 0.0, 'cantidad' => 0.0),
        );
        foreach ($propStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $tipo = (string) $row['tipo_item'];
            if (!isset($proporcion[$tipo])) {
                continue;
            }
            $proporcion[$tipo] = array(
                'monto' => round((float) $row['monto'], 2),
                'cantidad' => round((float) $row['cantidad'], 2),
            );
        }

        return array(
            'tipo' => 'productos_top',
            'filtros' => $f,
            'items' => $top,
            'proporcion' => $proporcion,
        );
    }

    /**
     * @param array $f
     * @return array
     */
    private static function whereCotizaciones(array $f)
    {
        $sql = 'c.fecha_emision >= ? AND c.fecha_emision <= ?';
        $params = array($f['desde'], $f['hasta']);
        if ($f['vendedor_id'] > 0) {
            $sql .= ' AND c.vendedor_id = ?';
            $params[] = $f['vendedor_id'];
        }
        return array('sql' => $sql, 'params' => $params);
    }

    /**
     * @param array $f
     * @return array
     */
    private static function whereComisiones(array $f)
    {
        $sql = 'c.fecha_emision >= ? AND c.fecha_emision <= ?';
        $params = array($f['desde'], $f['hasta']);
        if ($f['vendedor_id'] > 0) {
            $sql .= ' AND cm.vendedor_id = ?';
            $params[] = $f['vendedor_id'];
        }
        return array('sql' => $sql, 'params' => $params);
    }

    /**
     * @param string $ymd
     * @return string
     */
    private static function diaSiguiente($ymd)
    {
        $ts = strtotime($ymd . ' 00:00:00');
        if ($ts === false) {
            return $ymd;
        }
        return date('Y-m-d', $ts + 86400);
    }
}
