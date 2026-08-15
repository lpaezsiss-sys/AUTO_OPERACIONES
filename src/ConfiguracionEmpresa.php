<?php

declare(strict_types=1);

namespace Crm;

use PDO;

final class ConfiguracionEmpresa
{
    /**
     * @param PDO|null $pdo
     * @return array
     */
    public static function obtener($pdo = null)
    {
        $pdo = $pdo instanceof PDO ? $pdo : crm_pdo();
        $stmt = $pdo->query('SELECT * FROM crm_configuracion_empresa WHERE id = 1 LIMIT 1');
        $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
        if (!$row) {
            return array(
                'id' => 1,
                'rut' => '',
                'razon_social' => '',
                'nombre_fantasia' => '',
                'giro' => '',
                'direccion' => '',
                'ciudad' => '',
                'region' => '',
                'telefono' => '',
                'email' => '',
                'sitio_web' => '',
                'logo_path' => '',
                'actualizado_en' => null,
            );
        }
        return $row;
    }

    /**
     * @param array $data
     * @param PDO|null $pdo
     * @return array
     */
    public static function guardar(array $data, $pdo = null)
    {
        $pdo = $pdo instanceof PDO ? $pdo : crm_pdo();
        $rut = crm_str(isset($data['rut']) ? $data['rut'] : '', 20);
        $razon = crm_str(isset($data['razon_social']) ? $data['razon_social'] : '', 150);
        $direccion = crm_str(isset($data['direccion']) ? $data['direccion'] : '', 2000);
        if ($rut === '' || $razon === '' || $direccion === '') {
            Http::fail('RUT, razón social y dirección son obligatorios.');
        }

        $actual = self::obtener($pdo);
        $logo = crm_str(isset($data['logo_path']) ? $data['logo_path'] : (isset($actual['logo_path']) ? $actual['logo_path'] : ''), 255);

        $params = array(
            $rut,
            $razon,
            crm_str(isset($data['nombre_fantasia']) ? $data['nombre_fantasia'] : '', 150),
            crm_str(isset($data['giro']) ? $data['giro'] : '', 255),
            $direccion,
            crm_str(isset($data['ciudad']) ? $data['ciudad'] : '', 100),
            crm_str(isset($data['region']) ? $data['region'] : '', 100),
            crm_str(isset($data['telefono']) ? $data['telefono'] : '', 50),
            crm_str(isset($data['email']) ? $data['email'] : '', 150),
            crm_str(isset($data['sitio_web']) ? $data['sitio_web'] : '', 150),
            $logo,
        );

        $exists = (int) $pdo->query('SELECT COUNT(*) FROM crm_configuracion_empresa WHERE id = 1')->fetchColumn();
        if ($exists > 0) {
            $sql = 'UPDATE crm_configuracion_empresa SET
                        rut = ?, razon_social = ?, nombre_fantasia = ?, giro = ?, direccion = ?,
                        ciudad = ?, region = ?, telefono = ?, email = ?, sitio_web = ?, logo_path = ?
                    WHERE id = 1';
            $pdo->prepare($sql)->execute($params);
        } else {
            $sql = 'INSERT INTO crm_configuracion_empresa
                        (id, rut, razon_social, nombre_fantasia, giro, direccion, ciudad, region, telefono, email, sitio_web, logo_path)
                    VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
            $pdo->prepare($sql)->execute($params);
        }

        return array('configuracion' => self::obtener($pdo));
    }

    /**
     * @param string $logoPath
     * @param PDO|null $pdo
     * @return array
     */
    public static function actualizarLogo($logoPath, $pdo = null)
    {
        $pdo = $pdo instanceof PDO ? $pdo : crm_pdo();
        $logoPath = crm_str($logoPath, 255);
        $count = (int) $pdo->query('SELECT COUNT(*) FROM crm_configuracion_empresa WHERE id = 1')->fetchColumn();
        if ($count === 0) {
            Http::fail('Configure primero los datos de la empresa emisora.');
        }
        $pdo->prepare('UPDATE crm_configuracion_empresa SET logo_path = ? WHERE id = 1')->execute(array($logoPath));
        return array('configuracion' => self::obtener($pdo));
    }
}
