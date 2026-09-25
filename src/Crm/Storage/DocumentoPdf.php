<?php

declare(strict_types=1);

namespace Crm\Storage;

use Crm\ApiException;

/**
 * PDF mínimo (sin Composer) para operaciones COMEX.
 * Se guarda en uploads/comex/pdf/ con modo 0644.
 */
final class DocumentoPdf
{
    /**
     * @param array<string, mixed> $operacion
     */
    public static function operacion(array $operacion): string
    {
        Uploads::ensure();
        $folio = preg_replace('/[^A-Za-z0-9._-]/', '', (string) ($operacion['folio'] ?? 'operacion')) ?: 'operacion';
        $rel = 'comex/pdf/' . $folio . '.pdf';
        $abs = Uploads::path($rel);

        $tipo = (string) ($operacion['tipo'] ?? '');
        $fecha = (string) ($operacion['fecha'] ?? '');
        $estado = (string) ($operacion['estado'] ?? '');
        $lines = [
            'LPAEZSIS COMEX',
            'Operacion ' . $tipo . '  ' . $folio,
            'Fecha ' . $fecha . '  Estado ' . $estado,
            '',
            sprintf('%-14s %-10s %-12s %s', 'SKU', 'Cantidad', 'Precio', 'Descripcion'),
            str_repeat('-', 78),
        ];
        $items = $operacion['items'] ?? [];
        if (is_array($items)) {
            foreach ($items as $it) {
                if (!is_array($it)) {
                    continue;
                }
                $lines[] = sprintf(
                    '%-14s %-10s %-12s %s',
                    substr((string) ($it['sku'] ?? ''), 0, 14),
                    (string) ($it['cantidad'] ?? ''),
                    (string) ($it['precio_unitario'] ?? ''),
                    substr((string) ($it['descripcion'] ?? ''), 0, 36)
                );
            }
        }
        $jpeg = self::primeraJpeg($operacion);
        self::escribir($abs, $lines, $jpeg);
        @chmod($abs, Uploads::FILE_MODE);
        if (!is_file($abs) || filesize($abs) < 8) {
            throw new ApiException('No se pudo escribir el PDF en uploads/', 500);
        }
        return 'uploads/' . $rel;
    }

    /**
     * @param list<string> $lines
     */
    public static function escribir(string $absolutePath, array $lines, ?string $jpegPath = null): void
    {
        $dir = dirname($absolutePath);
        if (!is_dir($dir) && !mkdir($dir, Uploads::DIR_MODE, true) && !is_dir($dir)) {
            throw new ApiException('No se pudo crear uploads/comex/pdf', 500);
        }
        Uploads::chmodDir($dir);

        $content = "BT /F1 11 Tf 50 800 Td 14 TL\n";
        foreach ($lines as $line) {
            $content .= '(' . self::escape($line) . ") '\n";
        }
        $content .= "ET\n";

        $objects = [];
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[5] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';

        $imgObj = null;
        $imgBytes = '';
        if ($jpegPath !== null && is_file($jpegPath)) {
            $raw = file_get_contents($jpegPath);
            if (is_string($raw) && str_starts_with($raw, "\xFF\xD8")) {
                $size = @getimagesize($jpegPath);
                $w = is_array($size) ? (int) $size[0] : 200;
                $h = is_array($size) ? (int) $size[1] : 120;
                $imgBytes = $raw;
                $imgObj = 6;
                $objects[6] = '<< /Type /XObject /Subtype /Image /Width ' . $w
                    . ' /Height ' . $h . ' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length '
                    . strlen($imgBytes) . " >>\nstream\n" . $imgBytes . "\nendstream";
                $maxW = 180;
                $scale = $w > 0 ? min($maxW / $w, 110 / max(1, $h)) : 1;
                $dw = (int) round($w * $scale);
                $dh = (int) round($h * $scale);
                $content .= 'q ' . $dw . ' 0 0 ' . $dh . ' 380 680 cm /Im1 Do Q' . "\n";
            }
        }

        $objects[4] = '<< /Length ' . strlen($content) . " >>\nstream\n" . $content . 'endstream';
        $resources = '<< /Font << /F1 5 0 R >>';
        if ($imgObj !== null) {
            $resources .= ' /XObject << /Im1 6 0 R >>';
        }
        $resources .= ' >>';
        $objects[3] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources '
            . $resources . ' >>';
        $kids = '[3 0 R]';
        $objects[2] = '<< /Type /Pages /Kids ' . $kids . ' /Count 1 >>';

        self::escribirObjetos($absolutePath, $objects);
    }

    /**
     * Una página A4 con una imagen JPEG a ancho completo (planillas GD).
     */
    public static function paginaImagen(string $absolutePath, string $jpegPath, array $titulo = []): void
    {
        $dir = dirname($absolutePath);
        if (!is_dir($dir) && !mkdir($dir, Uploads::DIR_MODE, true) && !is_dir($dir)) {
            throw new ApiException('No se pudo crear uploads/comex/pdf', 500);
        }
        Uploads::chmodDir($dir);
        if (!is_file($jpegPath)) {
            throw new ApiException('JPEG de planilla no encontrado', 500);
        }
        if (class_exists(\finfo::class)) {
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mime = (string) $finfo->file($jpegPath);
            if ($mime !== 'image/jpeg') {
                throw new ApiException('La planilla debe ser JPEG (fileinfo: ' . $mime . ')', 500);
            }
        }
        $raw = file_get_contents($jpegPath);
        if (!is_string($raw) || !str_starts_with($raw, "\xFF\xD8")) {
            throw new ApiException('JPEG de planilla inválido', 500);
        }
        $size = @getimagesize($jpegPath);
        $w = is_array($size) ? (int) $size[0] : 1200;
        $h = is_array($size) ? (int) $size[1] : 1600;

        $content = "BT /F1 10 Tf 36 820 Td 12 TL\n";
        foreach ($titulo as $line) {
            $content .= '(' . self::escape((string) $line) . ") '\n";
        }
        $content .= "ET\n";

        $pageW = 595.0;
        $pageH = 842.0;
        $marginX = 28.0;
        $top = $titulo === [] ? 814.0 : 790.0;
        $maxW = $pageW - ($marginX * 2);
        $maxH = $top - 28.0;
        $scale = min($maxW / max(1, $w), $maxH / max(1, $h));
        $dw = $w * $scale;
        $dh = $h * $scale;
        $x = $marginX;
        $y = $top - $dh;
        $content .= sprintf('q %.2F 0 0 %.2F %.2F %.2F cm /Im1 Do Q' . "\n", $dw, $dh, $x, $y);

        $objects = [];
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[2] = '<< /Type /Pages /Kids [3 0 R] /Count 1 >>';
        $objects[5] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
        $objects[6] = '<< /Type /XObject /Subtype /Image /Width ' . $w
            . ' /Height ' . $h . ' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length '
            . strlen($raw) . " >>\nstream\n" . $raw . "\nendstream";
        $objects[4] = '<< /Length ' . strlen($content) . " >>\nstream\n" . $content . 'endstream';
        $objects[3] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' . $pageW . ' ' . $pageH
            . '] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> /XObject << /Im1 6 0 R >> >> >>';

        self::escribirObjetos($absolutePath, $objects);
    }

    /**
     * @param array<int, string> $objects
     */
    private static function escribirObjetos(string $absolutePath, array $objects): void
    {
        ksort($objects);
        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $num => $body) {
            $offsets[$num] = strlen($pdf);
            $pdf .= $num . " 0 obj\n" . $body . "\nendobj\n";
        }
        $xref = strlen($pdf);
        $max = max(array_keys($objects));
        $pdf .= 'xref' . "\n0 " . ($max + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i <= $max; $i++) {
            $off = $offsets[$i] ?? 0;
            $pdf .= sprintf('%010d 00000 n ' . "\n", $off);
        }
        $pdf .= 'trailer << /Size ' . ($max + 1) . ' /Root 1 0 R >>' . "\nstartxref\n" . $xref . "\n%%EOF\n";

        if (file_put_contents($absolutePath, $pdf) === false) {
            throw new ApiException('No se pudo guardar PDF (permisos uploads/ 755/775)', 500);
        }
    }

    /**
     * @param array<string, mixed> $operacion
     */
    private static function primeraJpeg(array $operacion): ?string
    {
        $root = \Crm\Env::getInstance()->root();
        $items = $operacion['items'] ?? [];
        if (!is_array($items)) {
            return null;
        }
        foreach ($items as $it) {
            if (!is_array($it)) {
                continue;
            }
            $rel = (string) ($it['imagen_path'] ?? '');
            if ($rel === '' || str_contains($rel, '..')) {
                continue;
            }
            $abs = str_starts_with($rel, '/') ? $rel : $root . '/' . ltrim($rel, '/');
            if (!is_file($abs)) {
                continue;
            }
            $ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
            if ($ext === 'jpg' || $ext === 'jpeg') {
                return $abs;
            }
            if ($ext === 'png' && extension_loaded('gd')) {
                $tmp = rtrim(sys_get_temp_dir(), '/') . '/comex-pdf-' . bin2hex(random_bytes(4)) . '.jpg';
                $im = @imagecreatefrompng($abs);
                if ($im === false) {
                    continue;
                }
                $ok = imagejpeg($im, $tmp, 82);
                imagedestroy($im);
                if ($ok === true) {
                    return $tmp;
                }
            }
        }
        return null;
    }

    private static function escape(string $text): string
    {
        $converted = @iconv('UTF-8', 'Windows-1252//TRANSLIT', $text);
        $text = is_string($converted) ? $converted : $text;
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }
}
