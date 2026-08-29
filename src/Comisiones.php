<?php

declare(strict_types=1);

namespace Crm;

use PDO;

final class Comisiones
{
    /**
     * @param float|int|string $montoNeto
     * @param float|int|string $porcentaje
     * @return float
     */
    public static function calcularMonto($montoNeto, $porcentaje)
    {
        $neto = round((float) $montoNeto, 2);
        $pct = round((float) $porcentaje, 2);
        if ($neto < 0) {
            $neto = 0.0;
        }
        if ($pct < 0) {
            $pct = 0.0;
        }
        return round($neto * ($pct / 100.0), 2);
    }

    /**
     * @param array $filtros
     * @return array
     */
    public static function index(array $filtros = array())
    {
        $pdo = crm_pdo();
        $sql = 'SELECT c.*, v.nombre_completo AS vendedor_nombre, v.email AS vendedor_email,
                       q.folio AS cotizacion_folio, q.estado AS cotizacion_estado
                FROM crm_comisiones c
                INNER JOIN crm_vendedores v ON v.id = c.vendedor_id
                INNER JOIN crm_cotizaciones q ON q.id = c.cotizacion_id
                WHERE 1=1';
        $params = array();
        $estado = isset($filtros['estado']) ? crm_str($filtros['estado'], 20) : '';
        if ($estado !== '') {
            $sql .= ' AND c.estado = ?';
            $params[] = $estado;
        }
        $vendedorId = crm_int(isset($filtros['vendedor_id']) ? $filtros['vendedor_id'] : 0, 0);
        if ($vendedorId > 0) {
            $sql .= ' AND c.vendedor_id = ?';
            $params[] = $vendedorId;
        }
        $sql .= ' ORDER BY c.id DESC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array('comisiones' => is_array($rows) ? $rows : array());
    }

    /**
     * @param array $body
     * @param int $ejecutivoId
     * @return int
     */
    public static function resolverVendedorId(PDO $pdo, array $body, $ejecutivoId)
    {
        $id = crm_int(isset($body['vendedor_id']) ? $body['vendedor_id'] : 0, 0);
        if ($id > 0) {
            $row = Vendedores::obtener($pdo, $id);
            if (!$row) {
                Http::fail('Vendedor no encontrado', 404);
            }
            return $id;
        }
        $mapped = Vendedores::porUsuario($pdo, (int) $ejecutivoId);
        return $mapped ? (int) $mapped['id'] : 0;
    }

    /**
     * Registra o actualiza comisión al aceptar una cotización. Llamar dentro de la misma transacción.
     *
     * @param int $cotizacionId
     * @param int $vendedorId
     * @param float $montoVentaNeto
     * @return array|null
     */
    public static function registrarDesdeCotizacion(PDO $pdo, $cotizacionId, $vendedorId, $montoVentaNeto)
    {
        $cotizacionId = (int) $cotizacionId;
        $vendedorId = (int) $vendedorId;
        $neto = round((float) $montoVentaNeto, 2);
        if ($cotizacionId <= 0 || $vendedorId <= 0) {
            return null;
        }

        $stmt = $pdo->prepare('SELECT id, comision_porcentaje, activo FROM crm_vendedores WHERE id = ? LIMIT 1');
        $stmt->execute(array($vendedorId));
        $vendedor = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$vendedor || (int) $vendedor['activo'] !== 1) {
            return null;
        }

        $pct = round((float) $vendedor['comision_porcentaje'], 2);
        $monto = self::calcularMonto($neto, $pct);

        $exist = $pdo->prepare('SELECT id, estado FROM crm_comisiones WHERE cotizacion_id = ? AND vendedor_id = ? LIMIT 1');
        $exist->execute(array($cotizacionId, $vendedorId));
        $prev = $exist->fetch(PDO::FETCH_ASSOC);
        if ($prev) {
            if (!in_array((string) $prev['estado'], array('pendiente', 'anulada'), true)) {
                return $prev;
            }
            $upd = $pdo->prepare(
                'UPDATE crm_comisiones
                 SET monto_venta_neto = ?, porcentaje_aplicado = ?, monto_comision = ?, estado = ?
                 WHERE id = ?'
            );
            $upd->execute(array($neto, $pct, $monto, 'pendiente', (int) $prev['id']));
            $row = $pdo->prepare('SELECT * FROM crm_comisiones WHERE id = ?');
            $row->execute(array((int) $prev['id']));
            $found = $row->fetch(PDO::FETCH_ASSOC);
            return $found ? $found : null;
        }

        $ins = $pdo->prepare(
            'INSERT INTO crm_comisiones
                (cotizacion_id, vendedor_id, monto_venta_neto, porcentaje_aplicado, monto_comision, estado)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $ins->execute(array($cotizacionId, $vendedorId, $neto, $pct, $monto, 'pendiente'));
        $id = (int) $pdo->lastInsertId();
        $row = $pdo->prepare('SELECT * FROM crm_comisiones WHERE id = ?');
        $row->execute(array($id));
        $found = $row->fetch(PDO::FETCH_ASSOC);
        return $found ? $found : null;
    }

    /**
     * @param int $cotizacionId
     * @param int $vendedorId
     * @param float $montoNeto
     * @param string $estado
     * @return array|null
     */
    public static function sincronizarConCotizacion(PDO $pdo, $cotizacionId, $vendedorId, $montoNeto, $estado)
    {
        $estado = (string) $estado;
        if ($estado === 'aceptada') {
            return self::registrarDesdeCotizacion($pdo, $cotizacionId, $vendedorId, $montoNeto);
        }
        if ($estado === 'rechazada' || $estado === 'vencida') {
            self::anularPendientesDeCotizacion($pdo, $cotizacionId);
        }
        return null;
    }

    /**
     * @param int $cotizacionId
     * @return void
     */
    public static function anularPendientesDeCotizacion(PDO $pdo, $cotizacionId)
    {
        $stmt = $pdo->prepare(
            'UPDATE crm_comisiones SET estado = ? WHERE cotizacion_id = ? AND estado = ?'
        );
        $stmt->execute(array('anulada', (int) $cotizacionId, 'pendiente'));
    }

    /**
     * @param int $id
     * @param string $estado
     * @param string|null $fechaLiquidacion
     * @return array
     */
    public static function actualizarEstado($id, $estado, $fechaLiquidacion = null)
    {
        $pdo = crm_pdo();
        $permitidos = array('pendiente', 'aprobada', 'pagada', 'anulada');
        if (!in_array($estado, $permitidos, true)) {
            Http::fail('Estado de comisión inválido.');
        }
        $fecha = null;
        if ($estado === 'pagada') {
            $fecha = $fechaLiquidacion ? crm_str($fechaLiquidacion, 10) : date('Y-m-d');
        }
        $stmt = $pdo->prepare('UPDATE crm_comisiones SET estado = ?, fecha_liquidacion = ? WHERE id = ?');
        $stmt->execute(array($estado, $fecha, (int) $id));
        $row = $pdo->prepare('SELECT * FROM crm_comisiones WHERE id = ?');
        $row->execute(array((int) $id));
        $found = $row->fetch(PDO::FETCH_ASSOC);
        if (!$found) {
            Http::fail('Comisión no encontrada.', 404);
        }
        return array('comision' => $found);
    }
}
