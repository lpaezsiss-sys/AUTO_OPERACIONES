<?php

declare(strict_types=1);

namespace Crm;

/**
 * PDF 1.4 de cotización (Helvetica, logo JPEG embebido). PHP 7.4, sin dependencias.
 */
final class CotizacionPdf
{
    /**
     * @param array $cotizacion Resultado de Cotizaciones::show()['cotizacion']
     * @return string
     */
    public static function generar(array $cotizacion)
    {
        $emisora = isset($cotizacion['emisora']) && is_array($cotizacion['emisora'])
            ? $cotizacion['emisora']
            : ConfiguracionEmpresa::obtener();
        $vendedor = isset($cotizacion['vendedor']) && is_array($cotizacion['vendedor'])
            ? $cotizacion['vendedor']
            : null;

        $ops = array();
        $y = 800.0;
        $logo = self::logoJpeg($emisora);
        if ($logo !== null) {
            $maxW = 90.0;
            $maxH = 48.0;
            $scale = min($maxW / $logo['w'], $maxH / $logo['h'], 1.0);
            $dw = $logo['w'] * $scale;
            $dh = $logo['h'] * $scale;
            $ops[] = sprintf('q %.2F 0 0 %.2F 40.00 %.2F cm /Im1 Do Q', $dw, $dh, $y - $dh + 8);
            $textX = 40.0 + $dw + 12.0;
        } else {
            $textX = 40.0;
        }

        $ops[] = self::textOp($textX, $y, 13, isset($emisora['razon_social']) ? (string) $emisora['razon_social'] : '');
        $y -= 16;
        $ops[] = self::textOp($textX, $y, 9, 'RUT: ' . (isset($emisora['rut']) ? (string) $emisora['rut'] : ''));
        $y -= 12;
        $dir = isset($emisora['direccion']) ? (string) $emisora['direccion'] : '';
        if (!empty($emisora['ciudad'])) {
            $dir .= ($dir !== '' ? ', ' : '') . (string) $emisora['ciudad'];
        }
        $ops[] = self::textOp($textX, $y, 9, $dir);
        $y -= 12;
        $contactoEmp = array();
        if (!empty($emisora['telefono'])) {
            $contactoEmp[] = 'Tel. ' . (string) $emisora['telefono'];
        }
        if (!empty($emisora['email'])) {
            $contactoEmp[] = (string) $emisora['email'];
        }
        if (count($contactoEmp) > 0) {
            $ops[] = self::textOp($textX, $y, 9, implode(' · ', $contactoEmp));
            $y -= 12;
        }

        $y -= 10;
        $ops[] = '0.85 0.75 0.05 rg 40 ' . sprintf('%.2F', $y) . ' 515 1.2 re f 0 0 0 rg';
        $y -= 22;
        $folio = isset($cotizacion['folio']) ? (string) $cotizacion['folio'] : '';
        $ops[] = self::textOp(40, $y, 16, 'COTIZACIÓN ' . $folio);
        $ops[] = self::textOp(400, $y, 10, 'Fecha: ' . (isset($cotizacion['fecha_emision']) ? (string) $cotizacion['fecha_emision'] : ''));
        $y -= 18;
        $ops[] = self::textOp(40, $y, 10, 'Cliente: ' . (isset($cotizacion['razon_social']) ? (string) $cotizacion['razon_social'] : ''));
        $ops[] = self::textOp(400, $y, 10, 'RUT: ' . (isset($cotizacion['rut']) ? (string) $cotizacion['rut'] : ''));
        $y -= 14;
        if (!empty($cotizacion['direccion'])) {
            $ops[] = self::textOp(40, $y, 9, 'Dirección: ' . (string) $cotizacion['direccion']);
            $y -= 14;
        }

        $y -= 6;
        $ops[] = self::textOp(40, $y, 11, 'Ejecutivo comercial');
        $y -= 14;
        if (is_array($vendedor)) {
            $ops[] = self::textOp(40, $y, 10, 'Nombre: ' . (isset($vendedor['nombre_completo']) ? (string) $vendedor['nombre_completo'] : ''));
            $y -= 12;
            $ops[] = self::textOp(40, $y, 10, 'Mail: ' . (isset($vendedor['email']) ? (string) $vendedor['email'] : ''));
            $y -= 12;
            $ops[] = self::textOp(40, $y, 10, 'Teléfono: ' . (isset($vendedor['telefono']) ? (string) $vendedor['telefono'] : '—'));
            $y -= 16;
        } else {
            $ops[] = self::textOp(40, $y, 10, 'Sin vendedor asignado');
            $y -= 16;
        }

        $ops[] = '0.05 0.16 0.29 rg 40 ' . sprintf('%.2F', $y) . ' 515 16 re f 1 1 1 rg';
        $ops[] = self::textOp(46, $y + 4, 8, 'SKU');
        $ops[] = self::textOp(110, $y + 4, 8, 'Descripción');
        $ops[] = self::textOp(330, $y + 4, 8, 'Cant.');
        $ops[] = self::textOp(380, $y + 4, 8, 'Precio');
        $ops[] = self::textOp(450, $y + 4, 8, '% Desc.');
        $ops[] = self::textOp(505, $y + 4, 8, 'Subtotal');
        $ops[] = '0 0 0 rg';
        $y -= 18;

        $items = isset($cotizacion['items']) && is_array($cotizacion['items']) ? $cotizacion['items'] : array();
        foreach ($items as $it) {
            if (!is_array($it)) {
                continue;
            }
            if ($y < 90) {
                break;
            }
            $tipo = isset($it['tipo_item']) ? (string) $it['tipo_item'] : 'producto';
            $descPdf = (string) (isset($it['descripcion']) ? $it['descripcion'] : '');
            if ($tipo === 'servicio') {
                $descPdf = '[Servicio] ' . $descPdf;
            }
            $ops[] = self::textOp(46, $y, 8, (string) (isset($it['codigo']) ? $it['codigo'] : ''));
            $ops[] = self::textOp(110, $y, 8, self::clip($descPdf, 42));
            $ops[] = self::textOp(330, $y, 8, self::num((float) (isset($it['cantidad']) ? $it['cantidad'] : 0), 2));
            $ops[] = self::textOp(380, $y, 8, self::clp((float) (isset($it['precio_unitario']) ? $it['precio_unitario'] : 0)));
            $ops[] = self::textOp(450, $y, 8, self::num((float) (isset($it['descuento_pct']) ? $it['descuento_pct'] : 0), 2));
            $ops[] = self::textOp(505, $y, 8, self::clp((float) (isset($it['subtotal']) ? $it['subtotal'] : 0)));
            $y -= 13;
        }

        $y -= 10;
        $ops[] = '0.85 0.75 0.05 rg 350 ' . sprintf('%.2F', $y + 10) . ' 205 0.8 re f 0 0 0 rg';
        $y -= 4;
        $ops[] = self::textOp(360, $y, 10, 'Subtotal');
        $ops[] = self::textOp(480, $y, 10, self::clp((float) (isset($cotizacion['subtotal']) ? $cotizacion['subtotal'] : 0)));
        $y -= 13;
        $ops[] = self::textOp(360, $y, 10, 'Descuento');
        $ops[] = self::textOp(480, $y, 10, self::clp((float) (isset($cotizacion['descuento']) ? $cotizacion['descuento'] : 0)));
        $y -= 13;
        $ivaPct = isset($cotizacion['iva_pct']) ? (float) $cotizacion['iva_pct'] : 19.0;
        $ops[] = self::textOp(360, $y, 10, 'IVA ' . self::num($ivaPct, 0) . '%');
        $ops[] = self::textOp(480, $y, 10, self::clp((float) (isset($cotizacion['iva']) ? $cotizacion['iva'] : 0)));
        $y -= 14;
        $ops[] = self::textOp(360, $y, 12, 'TOTAL');
        $ops[] = self::textOp(480, $y, 12, self::clp((float) (isset($cotizacion['total']) ? $cotizacion['total'] : 0)));

        if (!empty($cotizacion['notas'])) {
            $y -= 28;
            $ops[] = self::textOp(40, $y, 9, 'Notas: ' . self::clip((string) $cotizacion['notas'], 90));
        }

        $ops[] = self::textOp(40, 40, 8, 'Documento generado por CRM LPAEZsis · crm.lpaezsis.cl');

        $stream = implode("\n", $ops);
        return self::assemble($stream, $logo);
    }

    /**
     * @param array $emisora
     * @return array|null
     */
    private static function logoJpeg(array $emisora)
    {
        $rel = isset($emisora['logo_path']) ? (string) $emisora['logo_path'] : '';
        if ($rel === '') {
            return null;
        }
        $path = $rel;
        if (!str_starts_with($path, '/')) {
            $path = dirname(__DIR__) . '/' . ltrim($rel, '/');
        }
        if (!is_file($path)) {
            return null;
        }
        $info = @getimagesize($path);
        if (!is_array($info) || empty($info['mime'])) {
            return null;
        }
        $mime = (string) $info['mime'];
        $bytes = '';
        $w = (int) $info[0];
        $h = (int) $info[1];
        if ($w <= 0 || $h <= 0) {
            return null;
        }
        if ($mime === 'image/jpeg') {
            $raw = file_get_contents($path);
            $bytes = is_string($raw) ? $raw : '';
        } elseif ($mime === 'image/png' && function_exists('imagecreatefrompng') && function_exists('imagejpeg')) {
            $src = @imagecreatefrompng($path);
            if ($src === false) {
                return null;
            }
            $bg = imagecreatetruecolor($w, $h);
            $white = imagecolorallocate($bg, 255, 255, 255);
            imagefilledrectangle($bg, 0, 0, $w, $h, $white);
            imagecopy($bg, $src, 0, 0, 0, 0, $w, $h);
            ob_start();
            imagejpeg($bg, null, 85);
            $bytes = (string) ob_get_clean();
            imagedestroy($src);
            imagedestroy($bg);
        }
        if ($bytes === '') {
            return null;
        }
        return array('bytes' => $bytes, 'w' => $w, 'h' => $h);
    }

    /**
     * @param float $x
     * @param float $y
     * @param float $size
     * @param string $text
     * @return string
     */
    private static function textOp($x, $y, $size, $text)
    {
        $enc = self::winAnsi((string) $text);
        $esc = str_replace(array('\\', '(', ')'), array('\\\\', '\\(', '\\)'), $enc);
        return sprintf('BT /F1 %.2F Tf 1 0 0 1 %.2F %.2F Tm (%s) Tj ET', (float) $size, (float) $x, (float) $y, $esc);
    }

    /**
     * @param string $s
     * @return string
     */
    private static function winAnsi($s)
    {
        if (function_exists('iconv')) {
            $out = @iconv('UTF-8', 'Windows-1252//TRANSLIT', $s);
            if (is_string($out)) {
                return $out;
            }
        }
        return $s;
    }

    /**
     * @param string $s
     * @param int $max
     * @return string
     */
    private static function clip($s, $max)
    {
        if (function_exists('mb_substr')) {
            return mb_strlen($s) > $max ? mb_substr($s, 0, $max - 1) . '...' : $s;
        }
        return strlen($s) > $max ? substr($s, 0, $max - 1) . '...' : $s;
    }

    /**
     * @param float $n
     * @param int $dec
     * @return string
     */
    private static function num($n, $dec)
    {
        return number_format((float) $n, (int) $dec, ',', '.');
    }

    /**
     * @param float $n
     * @return string
     */
    private static function clp($n)
    {
        return '$' . number_format((float) $n, 0, ',', '.');
    }

    /**
     * @param string $stream
     * @param array|null $logo
     * @return string
     */
    private static function assemble($stream, $logo)
    {
        $objs = array();
        $hasImg = is_array($logo);
        $fontId = 4;
        $imgId = 5;
        $contentId = $hasImg ? 6 : 5;

        $objs[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objs[2] = '<< /Type /Pages /Kids [3 0 R] /Count 1 >>';
        $res = '/Font << /F1 ' . $fontId . ' 0 R >>';
        if ($hasImg) {
            $res .= ' /XObject << /Im1 ' . $imgId . ' 0 R >>';
        }
        $objs[3] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << ' . $res . ' >> /Contents ' . $contentId . ' 0 R >>';
        $objs[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        if ($hasImg) {
            $bytes = $logo['bytes'];
            $objs[5] = '<< /Type /XObject /Subtype /Image /Width ' . (int) $logo['w']
                . ' /Height ' . (int) $logo['h']
                . ' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length '
                . strlen($bytes) . " >>\nstream\n" . $bytes . "\nendstream";
        }
        $objs[$contentId] = '<< /Length ' . strlen($stream) . " >>\nstream\n" . $stream . "\nendstream";

        $out = "%PDF-1.4\n";
        $offsets = array(0);
        $max = $contentId;
        $n = 1;
        while ($n <= $max) {
            $offsets[$n] = strlen($out);
            $out .= $n . " 0 obj\n" . $objs[$n] . "\nendobj\n";
            $n++;
        }
        $xref = strlen($out);
        $count = $max + 1;
        $out .= "xref\n0 " . $count . "\n";
        $out .= "0000000000 65535 f \n";
        $n = 1;
        while ($n <= $max) {
            $out .= sprintf("%010d 00000 n \n", $offsets[$n]);
            $n++;
        }
        $out .= "trailer\n<< /Size " . $count . " /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF\n";
        return $out;
    }
}
