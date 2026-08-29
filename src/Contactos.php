<?php

declare(strict_types=1);

namespace Crm;

use PDO;

final class Contactos
{
    /**
     * @return array
     */
    public static function index()
    {
        $q = crm_str(isset($_GET['q']) ? $_GET['q'] : '', 120);
        $empresaId = isset($_GET['empresa_id']) ? crm_int($_GET['empresa_id'], 0) : 0;
        $sql = 'SELECT c.*, e.razon_social, e.rut
                FROM crm_contactos c
                INNER JOIN crm_empresas e ON e.id = c.empresa_id
                WHERE 1=1';
        $params = array();
        if ($q !== '') {
            $sql .= ' AND (c.nombre LIKE ? OR c.apellido LIKE ? OR c.email LIKE ? OR c.whatsapp LIKE ? OR c.telefono LIKE ?)';
            $like = '%' . $q . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }
        if ($empresaId > 0) {
            $sql .= ' AND c.empresa_id = ?';
            $params[] = $empresaId;
        }
        $sql .= ' ORDER BY e.razon_social, c.es_principal DESC, c.nombre';
        $stmt = crm_pdo()->prepare($sql);
        $stmt->execute($params);
        return array('contactos' => $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * @param array $body
     * @return array
     */
    public static function store(array $body)
    {
        $data = self::validate($body);
        $pdo = crm_pdo();
        Codes::requireEmpresa($pdo, $data['empresa_id']);
        if ($data['es_principal']) {
            self::clearPrincipal($pdo, $data['empresa_id']);
        }
        $stmt = $pdo->prepare(
            'INSERT INTO crm_contactos (empresa_id, nombre, apellido, cargo, email, telefono, whatsapp, canal_preferido, es_principal, activo, notas, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute(array(
            $data['empresa_id'],
            $data['nombre'],
            $data['apellido'],
            $data['cargo'],
            $data['email'],
            $data['telefono'],
            $data['whatsapp'],
            $data['canal_preferido'],
            $data['es_principal'],
            $data['activo'],
            $data['notas'],
            crm_now(),
        ));
        return self::one((int) $pdo->lastInsertId());
    }

    /**
     * @param int $id
     * @param array $body
     * @return array
     */
    public static function update($id, array $body)
    {
        $id = (int) $id;
        $existing = self::one($id);
        $data = self::validate($body, $existing);
        $pdo = crm_pdo();
        if ($data['es_principal']) {
            self::clearPrincipal($pdo, $data['empresa_id']);
        }
        $stmt = $pdo->prepare(
            'UPDATE crm_contactos SET empresa_id=?, nombre=?, apellido=?, cargo=?, email=?, telefono=?, whatsapp=?, canal_preferido=?, es_principal=?, activo=?, notas=?, updated_at=?
             WHERE id=?'
        );
        $stmt->execute(array(
            $data['empresa_id'],
            $data['nombre'],
            $data['apellido'],
            $data['cargo'],
            $data['email'],
            $data['telefono'],
            $data['whatsapp'],
            $data['canal_preferido'],
            $data['es_principal'],
            $data['activo'],
            $data['notas'],
            crm_now(),
            $id,
        ));
        return self::one($id);
    }

    /**
     * @param int $id
     * @return array
     */
    public static function destroy($id)
    {
        self::one($id);
        $pdo = crm_pdo();
        $n = $pdo->prepare('SELECT COUNT(*) FROM crm_cotizaciones WHERE contacto_id = ?');
        $n->execute(array((int) $id));
        if ((int) $n->fetchColumn() > 0) {
            Http::fail('No se puede eliminar el contacto: tiene cotizaciones asociadas.', 409);
        }
        $stmt = $pdo->prepare('DELETE FROM crm_contactos WHERE id = ?');
        $stmt->execute(array((int) $id));
        return array('deleted' => true, 'id' => (int) $id);
    }

    /**
     * @param int $id
     * @return array
     */
    public static function one($id)
    {
        $stmt = crm_pdo()->prepare(
            'SELECT c.*, e.razon_social FROM crm_contactos c
             INNER JOIN crm_empresas e ON e.id = c.empresa_id
             WHERE c.id = ? LIMIT 1'
        );
        $stmt->execute(array((int) $id));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            Http::fail('Contacto no encontrado', 404);
        }
        return array('contacto' => $row);
    }

    /**
     * @param PDO $pdo
     * @param int $empresaId
     * @return void
     */
    private static function clearPrincipal(PDO $pdo, $empresaId)
    {
        $stmt = $pdo->prepare('UPDATE crm_contactos SET es_principal = 0 WHERE empresa_id = ?');
        $stmt->execute(array((int) $empresaId));
    }

    /**
     * @param array $body
     * @param array|null $existing
     * @return array
     */
    private static function validate(array $body, $existing = null)
    {
        $nombre = crm_str(isset($body['nombre']) ? $body['nombre'] : '', 120);
        if ($nombre === '') {
            Http::fail('El nombre del contacto es obligatorio');
        }
        $empresaId = isset($body['empresa_id']) ? crm_int($body['empresa_id'], 0) : 0;
        if ($empresaId <= 0 && is_array($existing) && isset($existing['contacto']['empresa_id'])) {
            $empresaId = (int) $existing['contacto']['empresa_id'];
        }
        if ($empresaId <= 0) {
            Http::fail('Debe indicar la empresa');
        }
        $canal = isset($body['canal_preferido']) ? (string) $body['canal_preferido'] : 'email';
        $canal = Catalog::inList($canal, Catalog::canales(), 'canal_preferido');
        return array(
            'empresa_id' => $empresaId,
            'nombre' => $nombre,
            'apellido' => crm_str(isset($body['apellido']) ? $body['apellido'] : '', 120) ?: null,
            'cargo' => crm_str(isset($body['cargo']) ? $body['cargo'] : '', 160) ?: null,
            'email' => crm_str(isset($body['email']) ? $body['email'] : '', 190) ?: null,
            'telefono' => crm_str(isset($body['telefono']) ? $body['telefono'] : '', 40) ?: null,
            'whatsapp' => crm_str(isset($body['whatsapp']) ? $body['whatsapp'] : '', 40) ?: null,
            'canal_preferido' => $canal,
            'es_principal' => !empty($body['es_principal']) ? 1 : 0,
            'activo' => isset($body['activo']) && (int) $body['activo'] === 0 ? 0 : 1,
            'notas' => crm_str(isset($body['notas']) ? $body['notas'] : '', 4000) ?: null,
        );
    }
}
