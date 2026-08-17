<?php

declare(strict_types=1);

namespace Crm;

use PDO;

final class Marcas
{
    /**
     * @return list<array{archivo:string,nombre:string}>
     */
    public static function catalogoInicial()
    {
        return array(
            array('archivo' => 'sonic.png', 'nombre' => 'Sonic Air Systems'),
            array('archivo' => 'lc.png', 'nombre' => 'L&C Ltda.'),
            array('archivo' => 'movex.png', 'nombre' => 'Movex'),
            array('archivo' => 'eltra.png', 'nombre' => 'ELTRA trade'),
            array('archivo' => 'flexlink.png', 'nombre' => 'FlexLink'),
            array('archivo' => 'elektror.png', 'nombre' => 'Elektror airsystems'),
            array('archivo' => 'haida.png', 'nombre' => 'HAIDA International'),
            array('archivo' => 'intralox.png', 'nombre' => 'Intralox'),
            array('archivo' => 'columbia.png', 'nombre' => 'Columbia Okura LLC'),
            array('archivo' => 'combi.png', 'nombre' => 'Combi Packaging Systems'),
            array('archivo' => 'cmc.png', 'nombre' => 'CMC Klebetechnik'),
            array('archivo' => 'oriental.png', 'nombre' => 'Oriental Motor'),
        );
    }

    /**
     * @param PDO|null $pdo
     * @return void
     */
    public static function sembrarSiVacio($pdo = null)
    {
        $pdo = $pdo instanceof PDO ? $pdo : crm_pdo();
        try {
            $count = (int) $pdo->query('SELECT COUNT(*) FROM crm_marcas')->fetchColumn();
        } catch (\PDOException $e) {
            return;
        }
        if ($count > 0) {
            return;
        }
        $dir = self::directorio();
        $ins = $pdo->prepare(
            'INSERT INTO crm_marcas (nombre, archivo, activa, incluir_global, orden, updated_at)
             VALUES (?, ?, 1, 1, ?, ?)'
        );
        $orden = 10;
        foreach (self::catalogoInicial() as $def) {
            $path = $dir . '/' . $def['archivo'];
            if (!is_file($path)) {
                continue;
            }
            $ins->execute(array($def['nombre'], $def['archivo'], $orden, crm_now()));
            $orden += 10;
        }
    }

    /**
     * @return array
     */
    public static function index()
    {
        self::sembrarSiVacio();
        $rows = crm_pdo()->query(
            'SELECT * FROM crm_marcas ORDER BY orden ASC, nombre ASC'
        )->fetchAll(PDO::FETCH_ASSOC);
        if (!is_array($rows)) {
            $rows = array();
        }
        return array('marcas' => self::enriquecerFilas($rows));
    }

    /**
     * @param int $id
     * @return array
     */
    public static function show($id)
    {
        $row = self::requireOne($id);
        return array('marca' => self::enriquecer($row));
    }

    /**
     * @param array $body
     * @param array|null $file
     * @return array
     */
    public static function store(array $body, $file = null)
    {
        $data = self::validate($body, true);
        if (!is_array($file) || self::archivoVacio($file)) {
            Http::fail('Debe adjuntar el logo de la marca (PNG, JPG o SVG).');
        }
        $pdo = crm_pdo();
        $ins = $pdo->prepare(
            'INSERT INTO crm_marcas (nombre, archivo, activa, incluir_global, orden, updated_at)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $placeholder = 'marca_upl_pending.png';
        $ins->execute(array(
            $data['nombre'],
            $placeholder,
            $data['activa'],
            $data['incluir_global'],
            $data['orden'],
            crm_now(),
        ));
        $id = (int) $pdo->lastInsertId();
        try {
            $archivo = self::guardarArchivo($file, $id, PHP_SAPI !== 'cli');
            $pdo->prepare('UPDATE crm_marcas SET archivo = ?, updated_at = ? WHERE id = ?')
                ->execute(array($archivo, crm_now(), $id));
        } catch (\Throwable $e) {
            $pdo->prepare('DELETE FROM crm_marcas WHERE id = ?')->execute(array($id));
            throw $e;
        }
        return self::show($id);
    }

    /**
     * @param int $id
     * @param array $body
     * @param array|null $file
     * @return array
     */
    public static function update($id, array $body, $file = null)
    {
        $id = (int) $id;
        $existing = self::requireOne($id);
        $data = self::validate($body, false, $existing);
        $archivo = (string) $existing['archivo'];
        if (is_array($file) && !self::archivoVacio($file)) {
            $nuevo = self::guardarArchivo($file, $id, PHP_SAPI !== 'cli');
            if ($nuevo !== $archivo) {
                self::borrarArchivoSiSubido($archivo);
            }
            $archivo = $nuevo;
        }
        $stmt = crm_pdo()->prepare(
            'UPDATE crm_marcas SET nombre=?, archivo=?, activa=?, incluir_global=?, orden=?, updated_at=?
             WHERE id=?'
        );
        $stmt->execute(array(
            $data['nombre'],
            $archivo,
            $data['activa'],
            $data['incluir_global'],
            $data['orden'],
            crm_now(),
            $id,
        ));
        return self::show($id);
    }

    /**
     * @param int $id
     * @return array
     */
    public static function destroy($id)
    {
        $id = (int) $id;
        $row = self::requireOne($id);
        $pdo = crm_pdo();
        $pdo->beginTransaction();
        try {
            $pdo->prepare('DELETE FROM crm_cotizacion_marcas WHERE marca_id = ?')->execute(array($id));
            $pdo->prepare('DELETE FROM crm_marcas WHERE id = ?')->execute(array($id));
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
        self::borrarArchivoSiSubido(isset($row['archivo']) ? (string) $row['archivo'] : '');
        return array('deleted' => true, 'id' => $id);
    }

    /**
     * Logos a renderizar en el PDF: selección de la cotización o marcas globales.
     *
     * @param int $cotizacionId
     * @param PDO|null $pdo
     * @return list<array>
     */
    public static function paraPdf($cotizacionId, $pdo = null)
    {
        $pdo = $pdo instanceof PDO ? $pdo : crm_pdo();
        self::sembrarSiVacio($pdo);
        $cotizacionId = (int) $cotizacionId;
        $rows = array();
        if ($cotizacionId > 0) {
            $stmt = $pdo->prepare(
                'SELECT m.*
                 FROM crm_cotizacion_marcas cm
                 INNER JOIN crm_marcas m ON m.id = cm.marca_id
                 WHERE cm.cotizacion_id = ?
                 ORDER BY m.orden ASC, m.nombre ASC'
            );
            $stmt->execute(array($cotizacionId));
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        if (!is_array($rows) || count($rows) === 0) {
            $rows = $pdo->query(
                'SELECT * FROM crm_marcas
                 WHERE activa = 1 AND incluir_global = 1
                 ORDER BY orden ASC, nombre ASC'
            )->fetchAll(PDO::FETCH_ASSOC);
        }
        if (!is_array($rows)) {
            $rows = array();
        }
        return self::enriquecerFilas($rows);
    }

    /**
     * @param int $cotizacionId
     * @param PDO|null $pdo
     * @return list<int>
     */
    public static function idsDeCotizacion($cotizacionId, $pdo = null)
    {
        $pdo = $pdo instanceof PDO ? $pdo : crm_pdo();
        $stmt = $pdo->prepare('SELECT marca_id FROM crm_cotizacion_marcas WHERE cotizacion_id = ?');
        $stmt->execute(array((int) $cotizacionId));
        $ids = array();
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $mid) {
            $ids[] = (int) $mid;
        }
        return $ids;
    }

    /**
     * @param PDO $pdo
     * @param int $cotizacionId
     * @param array $body
     * @return void
     */
    public static function sincronizarCotizacion(PDO $pdo, $cotizacionId, array $body)
    {
        if (!array_key_exists('marca_ids', $body)) {
            return;
        }
        $cotizacionId = (int) $cotizacionId;
        $ids = array();
        if (is_array($body['marca_ids'])) {
            foreach ($body['marca_ids'] as $raw) {
                $mid = crm_int($raw, 0);
                if ($mid > 0) {
                    $ids[$mid] = $mid;
                }
            }
        }
        $pdo->prepare('DELETE FROM crm_cotizacion_marcas WHERE cotizacion_id = ?')->execute(array($cotizacionId));
        if (count($ids) === 0) {
            return;
        }
        $chk = $pdo->prepare('SELECT id FROM crm_marcas WHERE id = ? LIMIT 1');
        $ins = $pdo->prepare('INSERT INTO crm_cotizacion_marcas (cotizacion_id, marca_id) VALUES (?, ?)');
        foreach ($ids as $mid) {
            $chk->execute(array($mid));
            if (!$chk->fetchColumn()) {
                Http::fail('Marca no encontrada: id ' . $mid);
            }
            $ins->execute(array($cotizacionId, $mid));
        }
    }

    /**
     * @return string
     */
    public static function directorio()
    {
        return dirname(__DIR__) . '/assets/img/marcas';
    }

    /**
     * @param int $id
     * @return array
     */
    private static function requireOne($id)
    {
        $stmt = crm_pdo()->prepare('SELECT * FROM crm_marcas WHERE id = ? LIMIT 1');
        $stmt->execute(array((int) $id));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            Http::fail('Marca no encontrada', 404);
        }
        return $row;
    }

    /**
     * @param array $body
     * @param bool $creating
     * @param array|null $existing
     * @return array
     */
    private static function validate(array $body, $creating, $existing = null)
    {
        $nombre = crm_str(isset($body['nombre']) ? $body['nombre'] : '', 150);
        if ($nombre === '' && is_array($existing)) {
            $nombre = crm_str(isset($existing['nombre']) ? $existing['nombre'] : '', 150);
        }
        if ($nombre === '') {
            Http::fail('El nombre de la marca es obligatorio');
        }
        $orden = isset($body['orden']) ? crm_int($body['orden'], 0) : (is_array($existing) ? crm_int(isset($existing['orden']) ? $existing['orden'] : 0, 0) : 0);
        return array(
            'nombre' => $nombre,
            'activa' => self::flag($body, 'activa', $creating, $existing, 1),
            'incluir_global' => self::flag($body, 'incluir_global', $creating, $existing, 1),
            'orden' => $orden,
        );
    }

    /**
     * @param array $body
     * @param string $key
     * @param bool $creating
     * @param array|null $existing
     * @param int $default
     * @return int
     */
    private static function flag(array $body, $key, $creating, $existing, $default)
    {
        if (array_key_exists($key, $body)) {
            $v = $body[$key];
            if ($v === true || $v === 'true' || $v === 'on' || $v === '1' || $v === 1) {
                return 1;
            }
            return 0;
        }
        if (!$creating && is_array($existing) && array_key_exists($key, $existing)) {
            return (int) $existing[$key] ? 1 : 0;
        }
        return (int) $default ? 1 : 0;
    }

    /**
     * @param array $file
     * @return bool
     */
    private static function archivoVacio(array $file)
    {
        $err = isset($file['error']) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;
        if ($err === UPLOAD_ERR_NO_FILE) {
            return true;
        }
        $tmp = isset($file['tmp_name']) ? (string) $file['tmp_name'] : '';
        return $tmp === '' || !is_file($tmp);
    }

    /**
     * @param array $file
     * @param int $id
     * @param bool $requireUpload
     * @return string basename
     */
    private static function guardarArchivo(array $file, $id, $requireUpload)
    {
        $err = isset($file['error']) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;
        if ($err !== UPLOAD_ERR_OK && $err !== 0) {
            Http::fail('No se pudo cargar el logo de la marca.');
        }
        $tmp = isset($file['tmp_name']) ? (string) $file['tmp_name'] : '';
        $size = isset($file['size']) ? (int) $file['size'] : 0;
        $orig = isset($file['name']) ? (string) $file['name'] : 'marca.png';
        if ($tmp === '' || !is_file($tmp)) {
            Http::fail('Archivo de marca inválido.');
        }
        if ($requireUpload && function_exists('is_uploaded_file') && !is_uploaded_file($tmp)) {
            Http::fail('Archivo de marca inválido.');
        }
        if ($size <= 0) {
            $size = (int) filesize($tmp);
        }
        if ($size <= 0 || $size > 2097152) {
            Http::fail('El logo de la marca no debe superar 2 MB.');
        }

        $dir = self::directorio();
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            Http::fail('No se pudo crear el directorio de marcas.', 500);
        }

        $extOrig = strtolower((string) pathinfo($orig, PATHINFO_EXTENSION));
        $id = (int) $id;
        $base = 'marca_upl_' . $id . '_' . substr(sha1($orig . microtime(true)), 0, 8);

        if ($extOrig === 'svg' || self::pareceSvg($tmp)) {
            $raw = (string) file_get_contents($tmp);
            if (stripos($raw, '<svg') === false) {
                Http::fail('SVG de marca inválido.');
            }
            $name = $base . '.svg';
            $dest = $dir . '/' . $name;
            if (@file_put_contents($dest, $raw) === false) {
                Http::fail('No se pudo guardar el SVG de la marca.', 500);
            }
            return $name;
        }

        $info = @getimagesize($tmp);
        if (!is_array($info) || empty($info['mime'])) {
            Http::fail('El archivo no es una imagen válida.');
        }
        $mime = (string) $info['mime'];
        if ($mime !== 'image/png' && $mime !== 'image/jpeg') {
            Http::fail('El logo debe ser PNG, JPG o SVG.');
        }

        $name = $base . '.png';
        $dest = $dir . '/' . $name;
        if ($mime === 'image/png') {
            if (!@copy($tmp, $dest)) {
                Http::fail('No se pudo guardar el logo de la marca.', 500);
            }
            return $name;
        }
        if (!function_exists('imagecreatefromjpeg') || !function_exists('imagepng')) {
            Http::fail('No se pudo convertir JPG a PNG (GD no disponible).', 500);
        }
        $im = @imagecreatefromjpeg($tmp);
        if ($im === false) {
            Http::fail('JPG de marca inválido.');
        }
        $ok = @imagepng($im, $dest, 6);
        imagedestroy($im);
        if (!$ok) {
            Http::fail('No se pudo guardar el logo de la marca.', 500);
        }
        return $name;
    }

    /**
     * @param string $path
     * @return bool
     */
    private static function pareceSvg($path)
    {
        $fh = @fopen($path, 'rb');
        if ($fh === false) {
            return false;
        }
        $head = (string) fread($fh, 256);
        fclose($fh);
        return stripos($head, '<svg') !== false;
    }

    /**
     * @param string $archivo
     * @return void
     */
    private static function borrarArchivoSiSubido($archivo)
    {
        $archivo = basename((string) $archivo);
        if ($archivo === '' || strpos($archivo, 'marca_upl_') !== 0) {
            return;
        }
        $path = self::directorio() . '/' . $archivo;
        if (is_file($path)) {
            @unlink($path);
        }
    }

    /**
     * @param list<array> $rows
     * @return list<array>
     */
    private static function enriquecerFilas(array $rows)
    {
        $out = array();
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $out[] = self::enriquecer($row);
        }
        return $out;
    }

    /**
     * @param array $row
     * @return array
     */
    private static function enriquecer(array $row)
    {
        $archivo = isset($row['archivo']) ? basename((string) $row['archivo']) : '';
        $row['archivo'] = $archivo;
        $row['url'] = $archivo !== '' ? ('assets/img/marcas/' . $archivo) : '';
        $path = self::directorio() . '/' . $archivo;
        $row['existe_archivo'] = $archivo !== '' && is_file($path) ? 1 : 0;
        $row['activa'] = !empty($row['activa']) ? 1 : 0;
        $row['incluir_global'] = !empty($row['incluir_global']) ? 1 : 0;
        return $row;
    }
}
