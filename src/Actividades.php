<?php

declare(strict_types=1);

namespace Crm;

use PDO;

final class Actividades
{
    /**
     * @param array $filtros
     * @return array
     */
    public static function index(array $filtros = array())
    {
        if (count($filtros) === 0) {
            $filtros = $_GET;
        }
        $pdo = crm_pdo();
        $sql = 'SELECT a.*, e.razon_social, u.nombre AS usuario_nombre,
                       v.nombre_completo AS vendedor_nombre, v.email AS vendedor_email,
                       q.folio AS cotizacion_folio, o.codigo AS oportunidad_codigo
                FROM crm_actividades a
                LEFT JOIN crm_empresas e ON e.id = a.empresa_id
                LEFT JOIN crm_usuarios u ON u.id = a.usuario_id
                LEFT JOIN crm_vendedores v ON v.id = a.vendedor_id
                LEFT JOIN crm_cotizaciones q ON q.id = a.cotizacion_id
                LEFT JOIN crm_oportunidades o ON o.id = a.oportunidad_id
                WHERE 1=1';
        $params = array();

        $canal = crm_str(isset($filtros['canal']) ? $filtros['canal'] : '', 40);
        if ($canal !== '') {
            $sql .= ' AND a.canal = ?';
            $params[] = $canal;
        }

        $estado = crm_str(isset($filtros['estado']) ? $filtros['estado'] : '', 40);
        if ($estado !== '') {
            $estadoNorm = self::normalizarEstado($estado, false);
            if ($estadoNorm === 'realizada') {
                $sql .= " AND a.estado IN ('realizada','completada')";
            } else {
                $sql .= ' AND a.estado = ?';
                $params[] = $estadoNorm;
            }
        }

        $vendedorId = crm_int(isset($filtros['vendedor_id']) ? $filtros['vendedor_id'] : 0, 0);
        if ($vendedorId > 0) {
            $sql .= ' AND a.vendedor_id = ?';
            $params[] = $vendedorId;
        }

        $desde = crm_str(isset($filtros['desde']) ? $filtros['desde'] : '', 19);
        $hasta = crm_str(isset($filtros['hasta']) ? $filtros['hasta'] : '', 19);
        if ($desde !== '') {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) {
                $desde .= ' 00:00:00';
            }
            $sql .= ' AND COALESCE(a.fecha_programada, a.creado_en, a.created_at) >= ?';
            $params[] = $desde;
        }
        if ($hasta !== '') {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) {
                $hasta .= ' 23:59:59';
            }
            $sql .= ' AND COALESCE(a.fecha_programada, a.creado_en, a.created_at) <= ?';
            $params[] = $hasta;
        }

        $sql .= ' ORDER BY CASE WHEN a.estado IN (\'pendiente\') THEN 0 ELSE 1 END, COALESCE(a.fecha_programada, a.creado_en, a.created_at) ASC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!is_array($rows)) {
            $rows = array();
        }
        $actividades = array();
        foreach ($rows as $row) {
            $actividades[] = self::hidratar($row);
        }

        return array(
            'actividades' => $actividades,
            'resumen' => self::resumenDeLista($actividades),
        );
    }

    /**
     * @param array $body
     * @param array $user
     * @return array
     */
    public static function crear(array $body, array $user)
    {
        return self::store($body, $user);
    }

    /**
     * @param array $body
     * @param array $user
     * @return array
     */
    public static function store(array $body, array $user)
    {
        $data = self::validate($body, $user);
        $pdo = crm_pdo();
        $now = crm_now();
        $stmt = $pdo->prepare(
            'INSERT INTO crm_actividades (empresa_id, contacto_id, oportunidad_id, cotizacion_id, vendedor_id, usuario_id, tipo, canal, titulo, descripcion, fecha_programada, fecha_completada, estado, resultado, creado_en, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute(array(
            $data['empresa_id'],
            $data['contacto_id'],
            $data['oportunidad_id'],
            $data['cotizacion_id'],
            $data['vendedor_id'],
            $data['usuario_id'],
            $data['tipo'],
            $data['canal'],
            $data['titulo'],
            $data['descripcion'],
            $data['fecha_programada'],
            $data['fecha_completada'],
            $data['estado'],
            $data['resultado'],
            $now,
            $now,
        ));
        return self::one((int) $pdo->lastInsertId());
    }

    /**
     * Marca la actividad como realizada (postventa / seguimiento).
     *
     * @param int $id
     * @param array $body
     * @param array $user
     * @return array
     */
    public static function completar($id, array $body = array(), array $user = array())
    {
        $id = (int) $id;
        if ($id <= 0 && isset($body['id'])) {
            $id = crm_int($body['id'], 0);
        }
        $actual = self::one($id);
        $row = $actual['actividad'];
        $resultado = crm_str(isset($body['resultado']) ? $body['resultado'] : (isset($row['resultado']) ? $row['resultado'] : ''), 250);
        $stmt = crm_pdo()->prepare(
            'UPDATE crm_actividades
             SET estado = ?, fecha_completada = ?, resultado = ?, updated_at = ?
             WHERE id = ?'
        );
        $stmt->execute(array(
            'realizada',
            crm_now(),
            $resultado !== '' ? $resultado : null,
            crm_now(),
            $id,
        ));
        return self::one($id);
    }

    /**
     * @param int $id
     * @param array $body
     * @param array $user
     * @return array
     */
    public static function update($id, array $body, array $user)
    {
        self::one($id);
        $data = self::validate($body, $user);
        $stmt = crm_pdo()->prepare(
            'UPDATE crm_actividades SET empresa_id=?, contacto_id=?, oportunidad_id=?, cotizacion_id=?, vendedor_id=?, usuario_id=?, tipo=?, canal=?, titulo=?, descripcion=?, fecha_programada=?, fecha_completada=?, estado=?, resultado=?, updated_at=?
             WHERE id=?'
        );
        $stmt->execute(array(
            $data['empresa_id'],
            $data['contacto_id'],
            $data['oportunidad_id'],
            $data['cotizacion_id'],
            $data['vendedor_id'],
            $data['usuario_id'],
            $data['tipo'],
            $data['canal'],
            $data['titulo'],
            $data['descripcion'],
            $data['fecha_programada'],
            $data['fecha_completada'],
            $data['estado'],
            $data['resultado'],
            crm_now(),
            (int) $id,
        ));
        return self::one((int) $id);
    }

    /**
     * @param int $id
     * @return array
     */
    public static function one($id)
    {
        $stmt = crm_pdo()->prepare(
            'SELECT a.*, e.razon_social, u.nombre AS usuario_nombre,
                    v.nombre_completo AS vendedor_nombre, q.folio AS cotizacion_folio,
                    o.codigo AS oportunidad_codigo
             FROM crm_actividades a
             LEFT JOIN crm_empresas e ON e.id = a.empresa_id
             LEFT JOIN crm_usuarios u ON u.id = a.usuario_id
             LEFT JOIN crm_vendedores v ON v.id = a.vendedor_id
             LEFT JOIN crm_cotizaciones q ON q.id = a.cotizacion_id
             LEFT JOIN crm_oportunidades o ON o.id = a.oportunidad_id
             WHERE a.id = ? LIMIT 1'
        );
        $stmt->execute(array((int) $id));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            Http::fail('Actividad no encontrada', 404);
        }
        return array('actividad' => self::hidratar($row));
    }

    /**
     * @param array $body
     * @param array $user
     * @return array
     */
    private static function validate(array $body, array $user)
    {
        $titulo = crm_str(isset($body['titulo']) ? $body['titulo'] : '', 220);
        if ($titulo === '') {
            Http::fail('El título de la actividad es obligatorio');
        }
        $tipo = self::normalizarTipo(isset($body['tipo']) ? (string) $body['tipo'] : 'nota');
        $canalIn = isset($body['canal']) ? (string) $body['canal'] : '';
        if ($canalIn === '') {
            $canalIn = self::canalPorTipo($tipo);
        }
        $canal = Catalog::inList($canalIn, Catalog::canales(), 'canal');
        $estado = self::normalizarEstado(isset($body['estado']) ? (string) $body['estado'] : 'pendiente', true);
        $empresaId = crm_int(isset($body['empresa_id']) ? $body['empresa_id'] : 0, 0);
        $completada = crm_str(isset($body['fecha_completada']) ? $body['fecha_completada'] : '', 19);
        if ($estado === 'realizada' && $completada === '') {
            $completada = crm_now();
        }

        $usuarioId = crm_int(isset($body['usuario_id']) ? $body['usuario_id'] : $user['id'], (int) $user['id']);
        $vendedorId = crm_int(isset($body['vendedor_id']) ? $body['vendedor_id'] : 0, 0);
        if ($vendedorId <= 0) {
            $mapped = Vendedores::porUsuario(crm_pdo(), $usuarioId);
            $vendedorId = $mapped ? (int) $mapped['id'] : 0;
        }

        $cotizacionId = crm_int(isset($body['cotizacion_id']) ? $body['cotizacion_id'] : 0, 0);
        $oportunidadId = crm_int(isset($body['oportunidad_id']) ? $body['oportunidad_id'] : 0, 0);
        if ($cotizacionId > 0 && $empresaId <= 0) {
            $cot = crm_pdo()->prepare('SELECT empresa_id, oportunidad_id FROM crm_cotizaciones WHERE id = ? LIMIT 1');
            $cot->execute(array($cotizacionId));
            $cotRow = $cot->fetch(PDO::FETCH_ASSOC);
            if (is_array($cotRow)) {
                $empresaId = crm_int(isset($cotRow['empresa_id']) ? $cotRow['empresa_id'] : 0, 0);
                if ($oportunidadId <= 0) {
                    $oportunidadId = crm_int(isset($cotRow['oportunidad_id']) ? $cotRow['oportunidad_id'] : 0, 0);
                }
            }
        }

        $fechaProg = crm_str(isset($body['fecha_programada']) ? $body['fecha_programada'] : '', 19);
        $fechaProg = str_replace('T', ' ', $fechaProg);

        return array(
            'empresa_id' => $empresaId > 0 ? $empresaId : null,
            'contacto_id' => ($c = crm_int(isset($body['contacto_id']) ? $body['contacto_id'] : 0, 0)) > 0 ? $c : null,
            'oportunidad_id' => $oportunidadId > 0 ? $oportunidadId : null,
            'cotizacion_id' => $cotizacionId > 0 ? $cotizacionId : null,
            'vendedor_id' => $vendedorId > 0 ? $vendedorId : null,
            'usuario_id' => $usuarioId,
            'tipo' => $tipo,
            'canal' => $canal,
            'titulo' => $titulo,
            'descripcion' => crm_str(isset($body['descripcion']) ? $body['descripcion'] : '', 4000) ?: null,
            'fecha_programada' => $fechaProg !== '' ? $fechaProg : null,
            'fecha_completada' => $completada !== '' ? $completada : null,
            'estado' => $estado,
            'resultado' => crm_str(isset($body['resultado']) ? $body['resultado'] : '', 250) ?: null,
        );
    }

    /**
     * @param string $tipo
     * @return string
     */
    public static function normalizarTipo($tipo)
    {
        $tipo = crm_lower(trim((string) $tipo));
        if ($tipo === 'email' || $tipo === 'correo') {
            $tipo = 'correo';
        }
        if ($tipo === 'meeting' || $tipo === 'reunión') {
            $tipo = 'reunion';
        }
        return Catalog::inList($tipo, Catalog::actividadTipos(), 'tipo');
    }

    /**
     * @param string $estado
     * @param bool $paraGuardar
     * @return string
     */
    public static function normalizarEstado($estado, $paraGuardar = true)
    {
        $estado = crm_lower(trim((string) $estado));
        if ($estado === 'completada' || $estado === 'completa' || $estado === 'realizada') {
            $estado = 'realizada';
        }
        $allowed = Catalog::actividadEstados();
        if ($paraGuardar && $estado === 'completada') {
            $estado = 'realizada';
        }
        return Catalog::inList($estado, $allowed, 'estado');
    }

    /**
     * @param string $tipo
     * @return string
     */
    private static function canalPorTipo($tipo)
    {
        if ($tipo === 'llamada') {
            return 'telefono';
        }
        if ($tipo === 'correo') {
            return 'email';
        }
        if ($tipo === 'reunion' || $tipo === 'visita') {
            return 'visita';
        }
        if ($tipo === 'whatsapp') {
            return 'whatsapp';
        }
        return 'telefono';
    }

    /**
     * @param array $row
     * @return array
     */
    private static function hidratar(array $row)
    {
        $estado = (string) (isset($row['estado']) ? $row['estado'] : 'pendiente');
        if ($estado === 'completada') {
            $estado = 'realizada';
        }
        $row['estado'] = $estado;
        $creado = isset($row['creado_en']) ? (string) $row['creado_en'] : '';
        if ($creado === '' && isset($row['created_at'])) {
            $creado = (string) $row['created_at'];
        }
        $row['creado_en'] = $creado !== '' ? $creado : null;

        $prog = isset($row['fecha_programada']) ? (string) $row['fecha_programada'] : '';
        $hoy = date('Y-m-d');
        $esHoy = false;
        $vencida = false;
        if ($prog !== '' && $estado === 'pendiente') {
            $dia = substr($prog, 0, 10);
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dia)) {
                $esHoy = ($dia === $hoy);
                $vencida = ($dia < $hoy);
            }
        }
        $row['es_hoy'] = $esHoy;
        $row['vencida'] = $vencida;
        $row['agenda'] = $vencida ? 'vencida' : ($esHoy ? 'hoy' : ($estado === 'pendiente' ? 'programada' : $estado));
        return $row;
    }

    /**
     * @param array $actividades
     * @return array
     */
    private static function resumenDeLista(array $actividades)
    {
        $out = array(
            'pendientes' => 0,
            'hoy' => 0,
            'vencidas' => 0,
            'programadas' => 0,
            'realizadas' => 0,
            'canceladas' => 0,
        );
        foreach ($actividades as $a) {
            $est = (string) $a['estado'];
            if ($est === 'realizada') {
                $out['realizadas']++;
            } elseif ($est === 'cancelada') {
                $out['canceladas']++;
            } elseif ($est === 'pendiente') {
                $out['pendientes']++;
                if (!empty($a['vencida'])) {
                    $out['vencidas']++;
                } elseif (!empty($a['es_hoy'])) {
                    $out['hoy']++;
                } else {
                    $out['programadas']++;
                }
            }
        }
        return $out;
    }
}
