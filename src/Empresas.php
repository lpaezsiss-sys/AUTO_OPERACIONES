<?php

declare(strict_types=1);

namespace Crm;

use PDO;

final class Empresas
{
    /**
     * @return array
     */
    public static function index()
    {
        $q = crm_str(isset($_GET['q']) ? $_GET['q'] : '', 120);
        $estado = crm_str(isset($_GET['estado']) ? $_GET['estado'] : '', 20);
        $sql = 'SELECT e.*, u.nombre AS ejecutivo_nombre
                FROM crm_empresas e
                LEFT JOIN crm_usuarios u ON u.id = e.ejecutivo_id
                WHERE 1=1';
        $params = array();
        if ($q !== '') {
            $sql .= ' AND (e.razon_social LIKE ? OR e.nombre_fantasia LIKE ? OR e.rut LIKE ? OR e.email LIKE ?)';
            $like = '%' . $q . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }
        if ($estado !== '') {
            $sql .= ' AND e.estado = ?';
            $params[] = $estado;
        }
        $sql .= ' ORDER BY e.razon_social ASC';
        $stmt = crm_pdo()->prepare($sql);
        $stmt->execute($params);
        return array('empresas' => $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * @param int $id
     * @return array
     */
    public static function show($id)
    {
        $pdo = crm_pdo();
        $empresa = Codes::requireEmpresa($pdo, $id);
        $c = $pdo->prepare('SELECT * FROM crm_contactos WHERE empresa_id = ? ORDER BY es_principal DESC, nombre ASC');
        $c->execute(array($id));
        $o = $pdo->prepare('SELECT * FROM crm_oportunidades WHERE empresa_id = ? ORDER BY updated_at DESC');
        $o->execute(array($id));
        $q = $pdo->prepare('SELECT * FROM crm_cotizaciones WHERE empresa_id = ? ORDER BY created_at DESC');
        $q->execute(array($id));
        $a = $pdo->prepare('SELECT * FROM crm_actividades WHERE empresa_id = ? ORDER BY COALESCE(fecha_programada, created_at) DESC');
        $a->execute(array($id));
        $u = $pdo->prepare('SELECT id, nombre, email FROM crm_usuarios WHERE id = ? LIMIT 1');
        $u->execute(array($empresa['ejecutivo_id']));
        return array(
            'empresa' => $empresa,
            'ejecutivo' => $u->fetch(PDO::FETCH_ASSOC) ?: null,
            'contactos' => $c->fetchAll(PDO::FETCH_ASSOC),
            'oportunidades' => $o->fetchAll(PDO::FETCH_ASSOC),
            'cotizaciones' => $q->fetchAll(PDO::FETCH_ASSOC),
            'actividades' => $a->fetchAll(PDO::FETCH_ASSOC),
        );
    }

    /**
     * @param array $body
     * @param array $user
     * @return array
     */
    public static function store(array $body, array $user)
    {
        $data = self::validate($body, $user, true);
        $pdo = crm_pdo();
        $stmt = $pdo->prepare(
            'INSERT INTO crm_empresas (rut, razon_social, nombre_fantasia, giro, industria, region, comuna, direccion, telefono, email, sitio_web, origen, ejecutivo_id, estado, notas, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $now = crm_now();
        $stmt->execute(array(
            $data['rut'],
            $data['razon_social'],
            $data['nombre_fantasia'],
            $data['giro'],
            $data['industria'],
            $data['region'],
            $data['comuna'],
            $data['direccion'],
            $data['telefono'],
            $data['email'],
            $data['sitio_web'],
            $data['origen'],
            $data['ejecutivo_id'],
            $data['estado'],
            $data['notas'],
            $now,
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
        $pdo = crm_pdo();
        Codes::requireEmpresa($pdo, $id);
        $data = self::validate($body, $user, false);
        $stmt = $pdo->prepare(
            'UPDATE crm_empresas SET rut=?, razon_social=?, nombre_fantasia=?, giro=?, industria=?, region=?, comuna=?, direccion=?, telefono=?, email=?, sitio_web=?, origen=?, ejecutivo_id=?, estado=?, notas=?, updated_at=?
             WHERE id=?'
        );
        $stmt->execute(array(
            $data['rut'],
            $data['razon_social'],
            $data['nombre_fantasia'],
            $data['giro'],
            $data['industria'],
            $data['region'],
            $data['comuna'],
            $data['direccion'],
            $data['telefono'],
            $data['email'],
            $data['sitio_web'],
            $data['origen'],
            $data['ejecutivo_id'],
            $data['estado'],
            $data['notas'],
            crm_now(),
            (int) $id,
        ));
        return self::show((int) $id);
    }

    /**
     * @param int $id
     * @return array
     */
    public static function destroy($id)
    {
        $pdo = crm_pdo();
        Codes::requireEmpresa($pdo, $id);
        $stmt = $pdo->prepare('DELETE FROM crm_empresas WHERE id = ?');
        $stmt->execute(array((int) $id));
        return array('deleted' => true, 'id' => (int) $id);
    }

    /**
     * @param array $body
     * @param array $user
     * @param bool $creating
     * @return array
     */
    private static function validate(array $body, array $user, $creating)
    {
        $razon = crm_str(isset($body['razon_social']) ? $body['razon_social'] : '', 220);
        if ($razon === '') {
            Http::fail('La razón social es obligatoria');
        }
        $rut = Rut::requireValid(isset($body['rut']) ? (string) $body['rut'] : '');
        $estado = isset($body['estado']) ? (string) $body['estado'] : 'prospecto';
        $estado = Catalog::inList($estado, array('prospecto', 'activa', 'inactiva'), 'estado');
        $origen = isset($body['origen']) ? (string) $body['origen'] : 'web';
        $origen = Catalog::inList($origen, Catalog::origenes(), 'origen');
        $ejecutivo = isset($body['ejecutivo_id']) ? crm_int($body['ejecutivo_id'], 0) : crm_int($user['id'], 0);
        if ($ejecutivo <= 0) {
            $ejecutivo = crm_int($user['id'], 0);
        }
        return array(
            'rut' => $rut,
            'razon_social' => $razon,
            'nombre_fantasia' => crm_str(isset($body['nombre_fantasia']) ? $body['nombre_fantasia'] : '', 220) ?: null,
            'giro' => crm_str(isset($body['giro']) ? $body['giro'] : '', 220) ?: null,
            'industria' => crm_str(isset($body['industria']) ? $body['industria'] : '', 80) ?: null,
            'region' => crm_str(isset($body['region']) ? $body['region'] : '', 80) ?: null,
            'comuna' => crm_str(isset($body['comuna']) ? $body['comuna'] : '', 80) ?: null,
            'direccion' => crm_str(isset($body['direccion']) ? $body['direccion'] : '', 400) ?: null,
            'telefono' => crm_str(isset($body['telefono']) ? $body['telefono'] : '', 40) ?: null,
            'email' => crm_str(isset($body['email']) ? $body['email'] : '', 190) ?: null,
            'sitio_web' => crm_str(isset($body['sitio_web']) ? $body['sitio_web'] : '', 250) ?: null,
            'origen' => $origen,
            'ejecutivo_id' => $ejecutivo,
            'estado' => $estado,
            'notas' => crm_str(isset($body['notas']) ? $body['notas'] : '', 4000) ?: null,
        );
    }
}
