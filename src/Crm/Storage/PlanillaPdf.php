<?php

declare(strict_types=1);

namespace Crm\Storage;

use Crm\ApiException;

/**
 * Planilla landed cost: GD (tabla) + fileinfo (JPEG) + PDF embebido.
 */
final class PlanillaPdf
{
    /**
     * @param array<string, mixed> $eval
     * @param array<string, mixed> $operacion
     * @param array<string, mixed> $comparacion
     */
    public static function evaluacion(array $eval, array $operacion, array $comparacion = []): string
    {
        if (!extension_loaded('gd')) {
            throw new ApiException('Extensión gd requerida para la planilla PDF', 500);
        }
        Uploads::ensure();
        $calculo = is_array($eval['calculo'] ?? null) ? $eval['calculo'] : $eval;
        $folio = preg_replace('/[^A-Za-z0-9._-]/', '', (string) ($operacion['folio'] ?? 'op')) ?: 'op';
        $ver = preg_replace('/[^A-Za-z]/', '', (string) ($calculo['version'] ?? 'ESTIMADA')) ?: 'ESTIMADA';
        $rel = 'comex/pdf/landed-' . $folio . '-' . $ver . '.pdf';
        $abs = Uploads::path($rel);

        $jpeg = rtrim(sys_get_temp_dir(), '/') . '/landed-' . bin2hex(random_bytes(4)) . '.jpg';
        self::renderJpeg($jpeg, $calculo, $operacion, $comparacion);
        if (class_exists(\finfo::class)) {
            $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($jpeg);
            if ($mime !== 'image/jpeg') {
                throw new ApiException('fileinfo: se esperaba image/jpeg', 500);
            }
        }
        DocumentoPdf::paginaImagen($abs, $jpeg, [
            'LPAEZSIS COMEX  Landed Cost Chile  IVA aduanero 19%',
            'Folio ' . (string) ($operacion['folio'] ?? '') . '  Version ' . $ver,
        ]);
        @unlink($jpeg);
        @chmod($abs, Uploads::FILE_MODE);
        if (!is_file($abs) || !str_starts_with((string) file_get_contents($abs, false, null, 0, 5), '%PDF')) {
            throw new ApiException('No se pudo generar el PDF de landed cost', 500);
        }
        return 'uploads/' . $rel;
    }

    /**
     * @param array<string, mixed> $calculo
     * @param array<string, mixed> $operacion
     * @param array<string, mixed> $comparacion
     */
    public static function renderJpeg(string $path, array $calculo, array $operacion, array $comparacion): void
    {
        $items = is_array($calculo['items'] ?? null) ? $calculo['items'] : [];
        $gastos = is_array($calculo['gastos'] ?? null) ? $calculo['gastos'] : [];
        $tot = is_array($calculo['totales'] ?? null) ? $calculo['totales'] : [];
        $rows = 8 + count($gastos) + 2 + count($items) + 3;
        if (!empty($comparacion['disponible'])) {
            $rows += 6;
        }
        $w = 1400;
        $rowH = 22;
        $h = max(900, 120 + ($rows * $rowH));
        $im = imagecreatetruecolor($w, $h);
        if ($im === false) {
            throw new ApiException('GD no pudo crear la planilla', 500);
        }
        $navy = imagecolorallocate($im, 5, 41, 75);
        $yellow = imagecolorallocate($im, 254, 192, 1);
        $white = imagecolorallocate($im, 255, 255, 255);
        $ink = imagecolorallocate($im, 14, 26, 36);
        $muted = imagecolorallocate($im, 91, 107, 122);
        $bg = imagecolorallocate($im, 244, 246, 248);
        $line = imagecolorallocate($im, 197, 212, 227);
        if ($navy === false || $yellow === false || $white === false || $ink === false || $muted === false || $bg === false || $line === false) {
            imagedestroy($im);
            throw new ApiException('GD: colores no disponibles', 500);
        }
        imagefilledrectangle($im, 0, 0, $w, $h, $bg);
        imagefilledrectangle($im, 0, 0, $w, 72, $navy);
        imagefilledrectangle($im, 0, 72, $w, 78, $yellow);
        self::txt($im, 20, 18, 'LPAEZSIS COMEX  |  Evaluacion de operaciones (Landed Cost)', $white, 5);
        self::txt($im, 20, 42, 'Folio ' . (string) ($operacion['folio'] ?? '-') . '   '
            . (string) ($calculo['version'] ?? '') . '   IVA ' . (string) ($calculo['iva_pct'] ?? 19) . '% CIF   '
            . 'TC USD ' . (string) ($calculo['tipo_cambio_usd'] ?? '') . '   TC EUR ' . (string) ($calculo['tipo_cambio_eur'] ?? ''), $yellow, 3);

        $y = 96;
        self::txt($im, 20, $y, 'Gastos (origen USD/EUR + locales CLP)', $navy, 4);
        $y += 26;
        foreach ($gastos as $g) {
            if (!is_array($g)) {
                continue;
            }
            $cif = !empty($g['cif']) ? 'CIF' : 'local';
            self::txt($im, 24, $y, sprintf(
                '%-18s %-8s %-6s %12s %14s  [%s]',
                (string) ($g['nombre'] ?? ''),
                (string) ($g['ambito'] ?? ''),
                (string) ($g['moneda'] ?? ''),
                (string) ($g['monto'] ?? '0'),
                number_format((float) ($g['monto_clp'] ?? 0), 0, ',', '.'),
                $cif
            ), $ink, 3);
            $y += $rowH;
        }

        $y += 8;
        self::txt($im, 20, $y, 'Prorrateo por FOB', $navy, 4);
        $y += 24;
        imagefilledrectangle($im, 16, $y - 4, $w - 16, $y + 18, $navy);
        self::txt($im, 20, $y, 'SKU        Cant   FOB orig     FOB CLP        CIF        IVA 19%     Locales      Landed     Unit', $white, 3);
        $y += 26;
        foreach ($items as $it) {
            if (!is_array($it)) {
                continue;
            }
            imageline($im, 16, $y + 16, $w - 16, $y + 16, $line);
            self::txt($im, 20, $y, sprintf(
                '%-10s %5s %10s %12s %12s %12s %12s %12s %10s',
                substr((string) ($it['sku'] ?? ''), 0, 10),
                (string) ($it['cantidad'] ?? ''),
                number_format((float) ($it['fob_origen'] ?? 0), 2, ',', '.'),
                number_format((float) ($it['fob_clp'] ?? 0), 0, ',', '.'),
                number_format((float) ($it['cif_clp'] ?? 0), 0, ',', '.'),
                number_format((float) ($it['iva_clp'] ?? 0), 0, ',', '.'),
                number_format((float) ($it['gastos_locales_clp'] ?? 0), 0, ',', '.'),
                number_format((float) ($it['landed_total_clp'] ?? 0), 0, ',', '.'),
                number_format((float) ($it['landed_unitario_clp'] ?? 0), 0, ',', '.')
            ), $ink, 3);
            $y += $rowH;
        }
        $y += 10;
        self::txt($im, 20, $y, sprintf(
            'TOTAL FOB %s  CIF %s  IVA %s  LOCAL %s  LANDED %s',
            number_format((float) ($tot['fob_clp'] ?? 0), 0, ',', '.'),
            number_format((float) ($tot['cif_clp'] ?? 0), 0, ',', '.'),
            number_format((float) ($tot['iva_clp'] ?? 0), 0, ',', '.'),
            number_format((float) ($tot['gastos_locales_clp'] ?? 0), 0, ',', '.'),
            number_format((float) ($tot['landed_clp'] ?? 0), 0, ',', '.')
        ), $navy, 4);

        if (!empty($comparacion['disponible']) && is_array($comparacion['totales'] ?? null)) {
            $y += 36;
            self::txt($im, 20, $y, 'Comparacion Estimada vs Costo Real (CLP)', $navy, 4);
            $y += 24;
            foreach (['cif_clp' => 'CIF', 'iva_clp' => 'IVA aduanero', 'landed_clp' => 'Landed cost'] as $k => $label) {
                $d = $comparacion['totales'][$k] ?? [];
                if (!is_array($d)) {
                    continue;
                }
                self::txt($im, 24, $y, sprintf(
                    '%-16s  Est %s   Real %s   Delta %s (%s%%)',
                    $label,
                    number_format((float) ($d['estimada'] ?? 0), 0, ',', '.'),
                    number_format((float) ($d['real'] ?? 0), 0, ',', '.'),
                    number_format((float) ($d['delta'] ?? 0), 0, ',', '.'),
                    (string) ($d['delta_pct'] ?? '0')
                ), $ink, 3);
                $y += $rowH;
            }
        }

        $ok = imagejpeg($im, $path, 86);
        imagedestroy($im);
        if ($ok !== true) {
            throw new ApiException('No se pudo escribir JPEG de planilla', 500);
        }
    }

    private static function txt(\GdImage $im, int $x, int $y, string $text, int $color, int $font): void
    {
        $converted = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $text);
        $text = is_string($converted) ? $converted : $text;
        imagestring($im, $font, $x, $y, $text, $color);
    }
}
