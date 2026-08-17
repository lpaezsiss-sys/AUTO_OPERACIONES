<?php

declare(strict_types=1);

namespace Crm;

use PDO;

/**
 * Imagen de línea de cotización. Inventario solo lectura; subidas en uploads/cotizacion_items/.
 */
final class ItemImagen
{
    const DIR_REL = 'uploads/cotizacion_items';
    const INV_DIR_REL = 'uploads/productos';
    const MAX_BYTES = 2097152;

    /**
     * @param PDO $pdo
     * @return string
     */
    public static function columnaInventario(PDO $pdo)
    {
        $candidatas = array('imagen_url', 'imagen', 'foto_url', 'foto', 'image', 'photo');
        foreach ($candidatas as $col) {
            if (self::tablaTieneColumna($pdo, 'productos', $col)) {
                return $col;
            }
        }
        return '';
    }

    /**
     * @param array $row
     * @return array
     */
    public static function anexarAProducto(array $row)
    {
        $row['imagen_url'] = self::resolverInventario($row);
        return $row;
    }

    /**
     * @param array $prod
     * @return string
     */
    public static function resolverInventario(array $prod)
    {
        $claves = array('imagen_url', 'imagen', 'foto_url', 'foto', 'image', 'photo');
        foreach ($claves as $k) {
            if (!isset($prod[$k])) {
                continue;
            }
            $n = self::normalizarRuta((string) $prod[$k], false);
            if ($n !== '') {
                return $n;
            }
        }
        $codigo = isset($prod['codigo']) ? (string) $prod['codigo'] : '';
        return self::archivoPorCodigo($codigo);
    }

    /**
     * @param mixed $raw
     * @param array|null $prod
     * @return string
     */
    public static function normalizarEntrada($raw, $prod = null)
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            if (is_array($prod)) {
                return self::resolverInventario($prod);
            }
            return '';
        }
        if (preg_match('#^https?://#i', $raw)) {
            return self::descargarRemota($raw);
        }
        return self::normalizarRuta($raw, true);
    }

    /**
     * @param array $file
     * @return string Ruta relativa
     */
    public static function guardarUpload(array $file)
    {
        $err = isset($file['error']) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;
        if ($err === UPLOAD_ERR_NO_FILE || $err === UPLOAD_ERR_OK && empty($file['tmp_name'])) {
            Http::fail('Debe adjuntar una imagen PNG o JPG.');
        }
        if ($err !== UPLOAD_ERR_OK) {
            Http::fail('No se pudo recibir la imagen del ítem.');
        }
        $tmp = (string) $file['tmp_name'];
        $orig = isset($file['name']) ? (string) $file['name'] : 'item.jpg';
        $size = isset($file['size']) ? (int) $file['size'] : 0;
        if ($size <= 0 && is_file($tmp)) {
            $size = (int) filesize($tmp);
        }
        if ($size <= 0 || $size > self::MAX_BYTES) {
            Http::fail('La imagen del ítem no debe superar 2 MB.');
        }
        return self::guardarDesdeTmp($tmp, $orig);
    }

    /**
     * @param PDO $pdo
     * @param string $table
     * @param string $column
     * @return bool
     */
    private static function tablaTieneColumna(PDO $pdo, $table, $column)
    {
        $table = (string) $table;
        $column = (string) $column;
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $stmt = $pdo->query('PRAGMA table_info(' . $table . ')');
            $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : array();
            foreach ($rows as $col) {
                if (isset($col['name']) && (string) $col['name'] === $column) {
                    return true;
                }
            }
            return false;
        }
        $sql = 'SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?';
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array($table, $column));
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * @param string $codigo
     * @return string
     */
    private static function archivoPorCodigo($codigo)
    {
        $codigo = preg_replace('/[^A-Za-z0-9._-]/', '', (string) $codigo);
        if ($codigo === '') {
            return '';
        }
        $root = dirname(__DIR__);
        $exts = array('png', 'jpg', 'jpeg');
        foreach ($exts as $ext) {
            $rel = self::INV_DIR_REL . '/' . $codigo . '.' . $ext;
            if (is_file($root . '/' . $rel)) {
                return $rel;
            }
        }
        return '';
    }

    /**
     * @param string $path
     * @param bool $exigirExistencia
     * @return string
     */
    private static function normalizarRuta($path, $exigirExistencia)
    {
        $path = str_replace('\\', '/', trim((string) $path));
        if ($path === '' || strpos($path, '..') !== false) {
            return '';
        }
        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }
        $path = ltrim($path, '/');
        $permitidos = array(
            self::DIR_REL . '/',
            self::INV_DIR_REL . '/',
            'uploads/',
            'assets/img/',
        );
        $ok = false;
        foreach ($permitidos as $pref) {
            if (strpos($path, $pref) === 0) {
                $ok = true;
                break;
            }
        }
        if (!$ok && preg_match('/^[A-Za-z0-9._-]+\.(png|jpe?g)$/i', $path)) {
            $try = self::INV_DIR_REL . '/' . $path;
            if (is_file(dirname(__DIR__) . '/' . $try)) {
                $path = $try;
                $ok = true;
            }
        }
        if (!$ok) {
            return '';
        }
        if ($exigirExistencia && !is_file(dirname(__DIR__) . '/' . $path)) {
            return '';
        }
        if (!$exigirExistencia && !preg_match('#^https?://#i', $path) && !is_file(dirname(__DIR__) . '/' . $path)) {
            return '';
        }
        return $path;
    }

    /**
     * @param string $url
     * @return string
     */
    private static function descargarRemota($url)
    {
        $url = self::urlSegura($url);
        $bin = self::bajar($url);
        if ($bin === '') {
            Http::fail('No se pudo descargar la imagen del ítem.');
        }
        if (strlen($bin) > self::MAX_BYTES) {
            Http::fail('La imagen del ítem no debe superar 2 MB.');
        }
        $tmp = tempnam(sys_get_temp_dir(), 'crmimg');
        if ($tmp === false) {
            Http::fail('No se pudo crear un temporal para la imagen.', 500);
        }
        file_put_contents($tmp, $bin);
        try {
            $rel = self::guardarDesdeTmp($tmp, 'remoto.jpg');
        } finally {
            if (is_file($tmp)) {
                @unlink($tmp);
            }
        }
        return $rel;
    }

    /**
     * @param string $url
     * @return string
     */
    private static function urlSegura($url)
    {
        $url = trim((string) $url);
        $p = parse_url($url);
        if (!is_array($p) || empty($p['scheme']) || empty($p['host'])) {
            Http::fail('URL de imagen inválida.');
        }
        $scheme = strtolower((string) $p['scheme']);
        if ($scheme !== 'http' && $scheme !== 'https') {
            Http::fail('La URL de imagen debe ser http o https.');
        }
        $host = strtolower((string) $p['host']);
        if ($host === 'localhost' || $host === '127.0.0.1' || $host === '::1' || strpos($host, '0.0.0.0') === 0) {
            Http::fail('URL de imagen no permitida.');
        }
        return $url;
    }

    /**
     * @param string $url
     * @return string
     */
    private static function bajar($url)
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            if ($ch === false) {
                return '';
            }
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 6);
            curl_setopt($ch, CURLOPT_USERAGENT, 'CRM-LPAEZsis/1.0');
            curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
            $data = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if (!is_string($data) || $code >= 400) {
                return '';
            }
            return $data;
        }
        $ctx = stream_context_create(array(
            'http' => array(
                'timeout' => 10,
                'follow_location' => 1,
                'max_redirects' => 3,
                'header' => "User-Agent: CRM-LPAEZsis/1.0\r\n",
            ),
            'ssl' => array(
                'verify_peer' => true,
                'verify_peer_name' => true,
            ),
        ));
        $data = @file_get_contents($url, false, $ctx, 0, self::MAX_BYTES + 1);
        return is_string($data) ? $data : '';
    }

    /**
     * @param string $tmp
     * @param string $orig
     * @return string
     */
    private static function guardarDesdeTmp($tmp, $orig)
    {
        $dir = dirname(__DIR__) . '/' . self::DIR_REL;
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            Http::fail('No se pudo crear el directorio de imágenes de ítems.', 500);
        }
        $info = @getimagesize($tmp);
        if (!is_array($info) || empty($info['mime'])) {
            Http::fail('El archivo no es una imagen válida.');
        }
        $mime = (string) $info['mime'];
        if ($mime !== 'image/png' && $mime !== 'image/jpeg') {
            Http::fail('La imagen del ítem debe ser PNG o JPG.');
        }
        $base = 'item_' . date('YmdHis') . '_' . substr(sha1($orig . microtime(true)), 0, 8);
        $name = $base . '.png';
        $dest = $dir . '/' . $name;
        if ($mime === 'image/png') {
            if (!@copy($tmp, $dest)) {
                Http::fail('No se pudo guardar la imagen del ítem.', 500);
            }
            return self::DIR_REL . '/' . $name;
        }
        if (!function_exists('imagecreatefromjpeg') || !function_exists('imagepng')) {
            Http::fail('No se pudo convertir JPG a PNG (GD no disponible).', 500);
        }
        $im = @imagecreatefromjpeg($tmp);
        if ($im === false) {
            Http::fail('JPG de ítem inválido.');
        }
        $ok = @imagepng($im, $dest, 6);
        imagedestroy($im);
        if (!$ok) {
            Http::fail('No se pudo guardar la imagen del ítem.', 500);
        }
        return self::DIR_REL . '/' . $name;
    }
}
