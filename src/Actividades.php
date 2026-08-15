<?php

declare(strict_types=1);

namespace Crm;

use PDO;

final class Actividades
{
    /**
     * @return array
     */
    public static function index()
    {
        $canal = crm_str(isset($_GET['canal']) ? $_GET['canal'] : '', 40);
        $estado = crm_str(isset($_GET['estado']) ? $_GET['estado'] : '', 40);
        $sql = 'SELECT a.*, e.razon_social, u.nombre AS usuario_nombre
                FROM crm_actividades a
                LEFT JOIN crm_empresas e ON e.id = a.empresa_id
                LEFT JOIN crm_usuarios u ON u.id = a.usuario_id
                WHERE 1=1';
        $params = array();
        if ($canal !== '') {
            $sql .= ' AND a.canal = ?';
            $params[] = $canal;
        }
        if ($estado !== '') {
            $sql .= ' AND a.estado = ?';
            $params[] = $estado;
        }
        $sql .= ' ORDER BY COALESCE(a.fecha_programada, a.created_at) DESC';
        $stmt = crm_pdo()->prepare($sql);
        $stmt->execute($params);
        return array('actividades' => $stmt->fetchAll(PDO::FETCH_ASSOC));
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
        $stmt = $pdo->prepare(
            'INSERT INTO crm_actividades (empresa_id, contacto_id, oportunidad_id, cotizacion_id, usuario_id, tipo, canal, titulo, descripcion, fecha_programada, fecha_completada, estado, resultado, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute(array(
            $data['empresa_id'],
            $data['contacto_id'],
            $data['oportunidad_id'],
            $data['cotizacion_id'],
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
        ));
        return self::one((int) $pdo->lastInsertId());
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
            'UPDATE crm_actividades SET empresa_id=?, contacto_id=?, oportunidad_id=?, cotizacion_id=?, usuario_id=?, tipo=?, canal=?, titulo=?, descripcion=?, fecha_programada=?, fecha_completada=?, estado=?, resultado=?, updated_at=?
             WHERE id=?'
        );
        $stmt->execute(array(
            $data['empresa_id'],
            $data['contacto_id'],
            $data['oportunidad_id'],
            $data['cotizacion_id'],
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
        $stmt = crm_pdo()->prepare('SELECT * FROM crm_actividades WHERE id = ? LIMIT 1');
        $stmt->execute(array((int) $id));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            Http::fail('Actividad no encontrada', 404);
        }
        return array('actividad' => $row);
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
        $tipo = Catalog::inList(isset($body['tipo']) ? (string) $body['tipo'] : 'nota', Catalog::actividadTipos(), 'tipo');
        $canal = Catalog::inList(isset($body['canal']) ? (string) $body['canal'] : 'telefono', Catalog::canales(), 'canal');
        $estado = isset($body['estado']) ? (string) $body['estado'] : 'pendiente';
        $estado = Catalog::inList($estado, array('pendiente', 'completada', 'cancelada'), 'estado');
        $empresaId = crm_int(isset($body['empresa_id']) ? $body['empresa_id'] : 0, 0);
        $completada = crm_str(isset($body['fecha_completada']) ? $body['fecha_completada'] : '', 19);
        if ($estado === 'completada' && $completada === '') {
            $completada = crm_now();
        }
        return array(
            'empresa_id' => $empresaId > 0 ? $empresaId : null,
            'contacto_id' => ($c = crm_int(isset($body['contacto_id']) ? $body['contacto_id'] : 0, 0)) > 0 ? $c : null,
            'oportunidad_id' => ($o = crm_int(isset($body['oportunidad_id']) ? $body['oportunidad_id'] : 0, 0)) > 0 ? $o : null,
            'cotizacion_id' => ($q = crm_int(isset($body['cotizacion_id']) ? $body['cotizacion_id'] : 0, 0)) > 0 ? $q : null,
            'usuario_id' => crm_int(isset($body['usuario_id']) ? $body['usuario_id'] : $user['id'], (int) $user['id']),
            'tipo' => $tipo,
            'canal' => $canal,
            'titulo' => $titulo,
            'descripcion' => crm_str(isset($body['descripcion']) ? $body['descripcion'] : '', 4000) ?: null,
            'fecha_programada' => crm_str(isset($body['fecha_programada']) ? $body['fecha_programada'] : '', 19) ?: null,
            'fecha_completada' => $completada !== '' ? $completada : null,
            'estado' => $estado,
            'resultado' => crm_str(isset($body['resultado']) ? $body['resultado'] : '', 250) ?: null,
        );
    }
}
