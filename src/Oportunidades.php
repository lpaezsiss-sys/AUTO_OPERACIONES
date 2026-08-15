<?php

declare(strict_types=1);

namespace Crm;

use PDO;

final class Oportunidades
{
    /**
     * @return array
     */
    public static function index()
    {
        $etapa = crm_str(isset($_GET['etapa']) ? $_GET['etapa'] : '', 40);
        $sql = 'SELECT o.*, e.razon_social, e.rut, u.nombre AS ejecutivo_nombre
                FROM crm_oportunidades o
                INNER JOIN crm_empresas e ON e.id = o.empresa_id
                LEFT JOIN crm_usuarios u ON u.id = o.ejecutivo_id
                WHERE 1=1';
        $params = array();
        if ($etapa !== '') {
            $sql .= ' AND o.etapa = ?';
            $params[] = $etapa;
        }
        $sql .= ' ORDER BY o.updated_at DESC';
        $stmt = crm_pdo()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $pipeline = array();
        foreach (Catalog::etapas() as $et) {
            $pipeline[$et] = array('etapa' => $et, 'cantidad' => 0, 'valor' => 0.0);
        }
        foreach ($rows as $row) {
            $et = (string) $row['etapa'];
            if (!isset($pipeline[$et])) {
                continue;
            }
            $pipeline[$et]['cantidad']++;
            $pipeline[$et]['valor'] += (float) $row['valor_estimado'];
        }
        return array(
            'oportunidades' => $rows,
            'pipeline' => array_values($pipeline),
        );
    }

    /**
     * @param int $id
     * @return array
     */
    public static function show($id)
    {
        $stmt = crm_pdo()->prepare(
            'SELECT o.*, e.razon_social, e.rut, u.nombre AS ejecutivo_nombre
             FROM crm_oportunidades o
             INNER JOIN crm_empresas e ON e.id = o.empresa_id
             LEFT JOIN crm_usuarios u ON u.id = o.ejecutivo_id
             WHERE o.id = ? LIMIT 1'
        );
        $stmt->execute(array((int) $id));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            Http::fail('Oportunidad no encontrada', 404);
        }
        return array('oportunidad' => $row);
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
        Codes::requireEmpresa($pdo, $data['empresa_id']);
        $codigo = Codes::next('crm_oportunidades', 'codigo', 'OPP');
        $stmt = $pdo->prepare(
            'INSERT INTO crm_oportunidades (codigo, empresa_id, contacto_id, titulo, etapa, valor_estimado, probabilidad, fecha_cierre_esperada, ejecutivo_id, origen_canal, motivo_perdida, notas, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute(array(
            $codigo,
            $data['empresa_id'],
            $data['contacto_id'],
            $data['titulo'],
            $data['etapa'],
            $data['valor_estimado'],
            $data['probabilidad'],
            $data['fecha_cierre_esperada'],
            $data['ejecutivo_id'],
            $data['origen_canal'],
            $data['motivo_perdida'],
            $data['notas'],
            crm_now(),
        ));
        return self::show((int) $pdo->lastInsertId());
    }

    /**
     * @param int $id
     * @param array $body
     * @param array $user
     * @return array
     */
    public static function update($id, array $body, array $user)
    {
        self::show($id);
        $data = self::validate($body, $user);
        $stmt = crm_pdo()->prepare(
            'UPDATE crm_oportunidades SET empresa_id=?, contacto_id=?, titulo=?, etapa=?, valor_estimado=?, probabilidad=?, fecha_cierre_esperada=?, ejecutivo_id=?, origen_canal=?, motivo_perdida=?, notas=?, updated_at=?
             WHERE id=?'
        );
        $stmt->execute(array(
            $data['empresa_id'],
            $data['contacto_id'],
            $data['titulo'],
            $data['etapa'],
            $data['valor_estimado'],
            $data['probabilidad'],
            $data['fecha_cierre_esperada'],
            $data['ejecutivo_id'],
            $data['origen_canal'],
            $data['motivo_perdida'],
            $data['notas'],
            crm_now(),
            (int) $id,
        ));
        return self::show((int) $id);
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
            Http::fail('El título de la oportunidad es obligatorio');
        }
        $empresaId = crm_int(isset($body['empresa_id']) ? $body['empresa_id'] : 0, 0);
        if ($empresaId <= 0) {
            Http::fail('Debe indicar la empresa');
        }
        $etapa = Catalog::inList(isset($body['etapa']) ? (string) $body['etapa'] : 'prospecto', Catalog::etapas(), 'etapa');
        $canal = Catalog::inList(isset($body['origen_canal']) ? (string) $body['origen_canal'] : 'web', Catalog::canales(), 'origen_canal');
        $prob = crm_int(isset($body['probabilidad']) ? $body['probabilidad'] : 10, 10);
        if ($prob < 0) {
            $prob = 0;
        }
        if ($prob > 100) {
            $prob = 100;
        }
        $contacto = crm_int(isset($body['contacto_id']) ? $body['contacto_id'] : 0, 0);
        $ejecutivo = crm_int(isset($body['ejecutivo_id']) ? $body['ejecutivo_id'] : $user['id'], (int) $user['id']);
        $fecha = crm_str(isset($body['fecha_cierre_esperada']) ? $body['fecha_cierre_esperada'] : '', 10);
        return array(
            'empresa_id' => $empresaId,
            'contacto_id' => $contacto > 0 ? $contacto : null,
            'titulo' => $titulo,
            'etapa' => $etapa,
            'valor_estimado' => crm_float(isset($body['valor_estimado']) ? $body['valor_estimado'] : 0, 0),
            'probabilidad' => $prob,
            'fecha_cierre_esperada' => $fecha !== '' ? $fecha : null,
            'ejecutivo_id' => $ejecutivo,
            'origen_canal' => $canal,
            'motivo_perdida' => crm_str(isset($body['motivo_perdida']) ? $body['motivo_perdida'] : '', 250) ?: null,
            'notas' => crm_str(isset($body['notas']) ? $body['notas'] : '', 4000) ?: null,
        );
    }
}
