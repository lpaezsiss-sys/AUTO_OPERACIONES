<?php

declare(strict_types=1);

namespace Crm\Storage;

use Crm\ApiException;
use Crm\Env;

/**
 * Imágenes de ficha e ítem COMEX en uploads/ (755/775 dirs, 0644 archivos).
 * GD para generar la ficha cuando no hay foto de inventario.
 */
final class ItemImagen
{
    public const DIR_PRODUCTOS = 'comex/productos';
    public const DIR_ITEMS = 'comex/items';
    public const MAX_BYTES = 2097152;
    public const WIDTH = 640;
    public const HEIGHT = 400;

    public static function generarFicha(string $sku, string $nombre = ''): string
    {
        if (!extension_loaded('gd')) {
            throw new ApiException('Extensión gd requerida para generar imágenes', 500);
        }
        $sku = self::safeSku($sku);
        if ($sku === '') {
            throw new ApiException('SKU inválido para imagen', 400);
        }
        $nombre = trim($nombre) !== '' ? trim($nombre) : $sku;
        Uploads::ensure();
        $relDir = self::DIR_PRODUCTOS;
        $absDir = Uploads::path($relDir);
        $rel = $relDir . '/' . $sku . '.png';
        $abs = Uploads::path($rel);

        $im = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        if ($im === false) {
            throw new ApiException('No se pudo crear la imagen GD', 500);
        }
        $navy = imagecolorallocate($im, 5, 41, 75);
        $white = imagecolorallocate($im, 255, 255, 255);
        $muted = imagecolorallocate($im, 180, 198, 214);
        if ($navy === false || $white === false || $muted === false) {
            imagedestroy($im);
            throw new ApiException('GD: colores no disponibles', 500);
        }
        imagefilledrectangle($im, 0, 0, self::WIDTH, self::HEIGHT, $navy);
        imagestring($im, 5, 32, 80, self::latin1('LPAEZSIS COMEX'), $muted);
        imagestring($im, 5, 32, 140, self::latin1($sku), $white);
        imagestring($im, 4, 32, 200, self::latin1(self::trunc($nombre, 42)), $white);
        imagestring($im, 3, 32, 340, self::latin1('Ficha de producto'), $muted);

        $ok = imagepng($im, $abs, 6);
        imagedestroy($im);
        if ($ok !== true) {
            throw new ApiException('No se pudo escribir ' . $rel . ' (permisos uploads/)', 500);
        }
        @chmod($abs, Uploads::FILE_MODE);
        if (!is_file($abs) || !is_writable($absDir)) {
            throw new ApiException('uploads/ no escribible para imágenes de ítem', 500);
        }
        return 'uploads/' . $rel;
    }

    /**
     * @param array<string, mixed> $file
     */
    public static function guardarUpload(array $file, string $subdir = self::DIR_ITEMS): string
    {
        $err = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($err !== UPLOAD_ERR_OK) {
            throw new ApiException('Debe adjuntar una imagen PNG o JPG', 400);
        }
        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_file($tmp)) {
            throw new ApiException('Archivo temporal de imagen no disponible', 400);
        }
        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0) {
            $size = (int) filesize($tmp);
        }
        if ($size <= 0 || $size > self::MAX_BYTES) {
            throw new ApiException('La imagen no debe superar 2 MB', 400);
        }
        $info = @getimagesize($tmp);
        if (!is_array($info) || !isset($info[2])) {
            throw new ApiException('La imagen no es PNG/JPG válida', 400);
        }
        $ext = match ((int) $info[2]) {
            IMAGETYPE_JPEG => 'jpg',
            IMAGETYPE_PNG => 'png',
            default => throw new ApiException('Solo PNG o JPG', 400),
        };
        Uploads::ensure();
        $name = bin2hex(random_bytes(8)) . '.' . $ext;
        $rel = $subdir . '/' . $name;
        $abs = Uploads::path($rel);
        $raw = file_get_contents($tmp);
        if (!is_string($raw) || file_put_contents($abs, $raw) === false) {
            throw new ApiException('No se pudo guardar en uploads/ (permisos 755/775)', 500);
        }
        @chmod($abs, Uploads::FILE_MODE);
        return 'uploads/' . $rel;
    }

    public static function safeSku(string $sku): string
    {
        $sku = preg_replace('/[^A-Za-z0-9._-]/', '', $sku) ?? '';
        return substr($sku, 0, 80);
    }

    private static function trunc(string $s, int $max): string
    {
        if (function_exists('mb_substr')) {
            return mb_substr($s, 0, $max);
        }
        return substr($s, 0, $max);
    }

    private static function latin1(string $s): string
    {
        $converted = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $s);
        return is_string($converted) ? $converted : $s;
    }
}
