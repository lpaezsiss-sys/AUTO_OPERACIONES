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

    /**
     * Guarda el logo siempre en uploads/logo.png. Solo PNG o JPG.
     *
     * @param array $file Entrada estilo $_FILES['logo']
     * @param bool $requireUpload
     * @return string
     */
    public static function guardarArchivoLogo(array $file, $requireUpload = true)
    {
        $err = isset($file['error']) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;
        if ($err !== UPLOAD_ERR_OK && $err !== 0) {
            Http::fail('No se pudo cargar el logo.');
        }
        $tmp = isset($file['tmp_name']) ? (string) $file['tmp_name'] : '';
        $size = isset($file['size']) ? (int) $file['size'] : 0;
        if ($tmp === '' || !is_file($tmp)) {
            Http::fail('Archivo de logo inválido.');
        }
        if ($requireUpload && function_exists('is_uploaded_file') && !is_uploaded_file($tmp)) {
            Http::fail('Archivo de logo inválido.');
        }
        if ($size <= 0) {
            $size = (int) filesize($tmp);
        }
        if ($size <= 0 || $size > 2097152) {
            Http::fail('El logo no debe superar 2 MB.');
        }
        $info = @getimagesize($tmp);
        if (!is_array($info) || empty($info['mime'])) {
            Http::fail('El archivo no es una imagen válida.');
        }
        $mime = (string) $info['mime'];
        if ($mime !== 'image/png' && $mime !== 'image/jpeg') {
            Http::fail('El logo debe ser PNG o JPG.');
        }

        $dir = dirname(__DIR__) . '/uploads';
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            Http::fail('No se pudo crear el directorio de uploads.', 500);
        }
        $rel = 'uploads/logo.png';
        $dest = dirname(__DIR__) . '/' . $rel;

        if ($mime === 'image/png') {
            $ok = @copy($tmp, $dest);
            if (!$ok) {
                Http::fail('No se pudo guardar el logo.', 500);
            }
            return $rel;
        }

        if (!function_exists('imagecreatefromjpeg') || !function_exists('imagepng')) {
            Http::fail('No se pudo convertir JPG a PNG (GD no disponible).', 500);
        }
        $im = @imagecreatefromjpeg($tmp);
        if ($im === false) {
            Http::fail('JPG de logo inválido.');
        }
        $ok = @imagepng($im, $dest, 6);
        imagedestroy($im);
        if (!$ok) {
            Http::fail('No se pudo guardar el logo.', 500);
        }
        return $rel;
    }
}
