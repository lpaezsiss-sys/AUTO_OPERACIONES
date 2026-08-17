<?php

declare(strict_types=1);

namespace Crm;

use PDO;

final class EstadisticasAPedido
{
    /**
     * @param array $filtros
     * @return array
     */
    public static function filtros(array $filtros = array())
    {
        $periodo = crm_lower(crm_str(isset($filtros['periodo']) ? $filtros['periodo'] : '', 20));
        $desde = crm_str(isset($filtros['desde']) ? $filtros['desde'] : '', 10);
        $hasta = crm_str(isset($filtros['hasta']) ? $filtros['hasta'] : '', 10);
        $hoy = date('Y-m-d');
        if ($periodo === 'trimestre') {
            $mes = (int) date('n');
            $q = (int) floor(($mes - 1) / 3);
            $desdeQ = ($q * 3) + 1;
            $desde = date('Y') . '-' . str_pad((string) $desdeQ, 2, '0', STR_PAD_LEFT) . '-01';
            $hasta = $hoy;
        } elseif ($periodo === 'anio' || $periodo === 'año') {
            $desde = date('Y') . '-01-01';
            $hasta = $hoy;
        } elseif ($periodo === 'mes' || $desde === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) {
            if ($periodo === 'mes' || $desde === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) {
                $desde = date('Y-m-01');
            }
        }
        if ($hasta === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) {
            $hasta = $hoy;
        }
        if ($desde > $hasta) {
            $tmp = $desde;
            $desde = $hasta;
            $hasta = $tmp;
        }
        return array(
            'periodo' => $periodo !== '' ? $periodo : 'mes',
            'desde' => $desde,
            'hasta' => $hasta,
            'marca' => crm_str(isset($filtros['marca']) ? $filtros['marca'] : '', 150),
            'marca_id' => crm_int(isset($filtros['marca_id']) ? $filtros['marca_id'] : 0, 0),
        );
    }

    /**
     * @param array $filtros
     * @return array
     */
    public static function obtener(array $filtros = array())
    {
        $f = self::filtros($filtros);
        $pdo = crm_pdo();
        $where = self::whereItems($f);
        $kpis = self::kpis($pdo, $where);
        $porMarca = self::porMarca($pdo, $where);
        $top = self::topProductos($pdo, $where);
        $sugerencias = array();
        foreach ($top as $row) {
            if ((int) $row['veces'] < 2) {
                continue;
            }
            $row['ya_en_catalogo'] = Productos::existePorNombre($row['descripcion']) ? 1 : 0;
            $sugerencias[] = $row;
        }
        $marcasFiltro = $pdo->query(
            "SELECT DISTINCT COALESCE(NULLIF(marca_nombre, ''), 'Sin marca') AS marca
             FROM crm_cotizacion_items
             WHERE tipo_item = 'a_pedido' OR es_a_pedido = 1
             ORDER BY marca ASC"
        )->fetchAll(PDO::FETCH_COLUMN);
        if (!is_array($marcasFiltro)) {
            $marcasFiltro = array();
        }
        return array(
            'filtros' => $f,
            'kpis' => $kpis,
            'por_marca' => $porMarca,
            'top' => $top,
            'sugerencias' => $sugerencias,
            'marcas' => $marcasFiltro,
        );
    }

    /**
     * @param array $body
     * @return array
     */
    public static function convertirInventario(array $body)
    {
        return Productos::altaCatalogo($body);
    }

    /**
     * @param array $f
     * @return array{sql:string,params:array}
     */
    private static function whereItems(array $f)
    {
        $sql = "c.fecha_emision >= ? AND c.fecha_emision <= ? AND (i.tipo_item = 'a_pedido' OR i.es_a_pedido = 1)";
        $params = array($f['desde'], $f['hasta']);
        if ($f['marca_id'] > 0) {
            $sql .= ' AND i.marca_id = ?';
            $params[] = $f['marca_id'];
        } elseif ($f['marca'] !== '') {
            $sql .= ' AND LOWER(COALESCE(i.marca_nombre, \'\')) = LOWER(?)';
            $params[] = $f['marca'];
        }
        return array('sql' => $sql, 'params' => $params);
    }

    /**
     * @param PDO $pdo
     * @param array $where
     * @return array
     */
    private static function kpis(PDO $pdo, array $where)
    {
        $sql = 'SELECT
                    COUNT(*) AS n_cotizados,
                    COALESCE(SUM(i.subtotal), 0) AS monto_cotizado,
                    SUM(CASE WHEN c.estado = \'aceptada\' THEN 1 ELSE 0 END) AS n_ganados,
                    COALESCE(SUM(CASE WHEN c.estado = \'aceptada\' THEN i.subtotal ELSE 0 END), 0) AS monto_ganado,
                    COALESCE(SUM(CASE WHEN i.costo_unitario > 0 THEN i.cantidad * i.precio_unitario ELSE 0 END), 0) AS base_margen,
                    COALESCE(SUM(CASE WHEN i.costo_unitario > 0 THEN i.cantidad * (i.precio_unitario - i.costo_unitario) ELSE 0 END), 0) AS margen_monto
                FROM crm_cotizacion_items i
                INNER JOIN crm_cotizaciones c ON c.id = i.cotizacion_id
                WHERE ' . $where['sql'];
        $stmt = $pdo->prepare($sql);
        $stmt->execute($where['params']);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $nCot = (int) (isset($row['n_cotizados']) ? $row['n_cotizados'] : 0);
        $nGan = (int) (isset($row['n_ganados']) ? $row['n_ganados'] : 0);
        $base = (float) (isset($row['base_margen']) ? $row['base_margen'] : 0);
        $margenMonto = (float) (isset($row['margen_monto']) ? $row['margen_monto'] : 0);
        $margenPct = $base > 0 ? round(($margenMonto / $base) * 100, 2) : null;
        return array(
            'n_cotizados' => $nCot,
            'n_ganados' => $nGan,
            'monto_cotizado' => (float) (isset($row['monto_cotizado']) ? $row['monto_cotizado'] : 0),
            'monto_ganado' => (float) (isset($row['monto_ganado']) ? $row['monto_ganado'] : 0),
            'margen_pct' => $margenPct,
            'conversion_pct' => $nCot > 0 ? round(($nGan / $nCot) * 100, 1) : 0.0,
        );
    }

    /**
     * @param PDO $pdo
     * @param array $where
     * @return list<array>
     */
    private static function porMarca(PDO $pdo, array $where)
    {
        $sql = 'SELECT
                    COALESCE(NULLIF(i.marca_nombre, \'\'), \'Sin marca\') AS marca,
                    MAX(CASE WHEN i.marca_id IS NULL OR i.marca_id = 0 THEN 0 ELSE 1 END) AS en_catalogo,
                    COUNT(*) AS n_cotizados,
                    SUM(CASE WHEN c.estado = \'aceptada\' THEN 1 ELSE 0 END) AS n_ganados,
                    COALESCE(SUM(i.subtotal), 0) AS monto_cotizado,
                    COALESCE(SUM(CASE WHEN c.estado = \'aceptada\' THEN i.subtotal ELSE 0 END), 0) AS monto_ganado
                FROM crm_cotizacion_items i
                INNER JOIN crm_cotizaciones c ON c.id = i.cotizacion_id
                WHERE ' . $where['sql'] . '
                GROUP BY COALESCE(NULLIF(i.marca_nombre, \'\'), \'Sin marca\')
                ORDER BY monto_cotizado DESC, n_cotizados DESC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($where['params']);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $out = array();
        foreach ($rows as $row) {
            $out[] = array(
                'marca' => (string) $row['marca'],
                'en_catalogo' => (int) $row['en_catalogo'] === 1 ? 1 : 0,
                'n_cotizados' => (int) $row['n_cotizados'],
                'n_ganados' => (int) $row['n_ganados'],
                'monto_cotizado' => (float) $row['monto_cotizado'],
                'monto_ganado' => (float) $row['monto_ganado'],
            );
        }
        return $out;
    }

    /**
     * @param PDO $pdo
     * @param array $where
     * @return list<array>
     */
    private static function topProductos(PDO $pdo, array $where)
    {
        $sql = 'SELECT
                    i.descripcion,
                    COALESCE(NULLIF(i.marca_nombre, \'\'), \'Sin marca\') AS marca,
                    MAX(i.marca_id) AS marca_id,
                    COUNT(*) AS veces,
                    COALESCE(SUM(i.cantidad), 0) AS cantidad,
                    COALESCE(SUM(i.subtotal), 0) AS monto_cotizado,
                    COALESCE(SUM(CASE WHEN c.estado = \'aceptada\' THEN i.subtotal ELSE 0 END), 0) AS monto_ganado,
                    AVG(i.precio_unitario) AS precio_promedio,
                    AVG(i.costo_unitario) AS costo_promedio
                FROM crm_cotizacion_items i
                INNER JOIN crm_cotizaciones c ON c.id = i.cotizacion_id
                WHERE ' . $where['sql'] . '
                GROUP BY i.descripcion, COALESCE(NULLIF(i.marca_nombre, \'\'), \'Sin marca\')
                ORDER BY veces DESC, monto_cotizado DESC
                LIMIT 20';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($where['params']);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $out = array();
        foreach ($rows as $row) {
            $out[] = array(
                'descripcion' => (string) $row['descripcion'],
                'marca' => (string) $row['marca'],
                'marca_id' => isset($row['marca_id']) && $row['marca_id'] !== '' ? (int) $row['marca_id'] : null,
                'veces' => (int) $row['veces'],
                'cantidad' => (float) $row['cantidad'],
                'monto_cotizado' => (float) $row['monto_cotizado'],
                'monto_ganado' => (float) $row['monto_ganado'],
                'precio_promedio' => (float) $row['precio_promedio'],
                'costo_promedio' => (float) $row['costo_promedio'],
            );
        }
        return $out;
    }
}
