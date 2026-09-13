<?php

declare(strict_types=1);

namespace Crm\Storage;

use Crm\ApiException;
use ZipArchive;

/**
 * Planilla XLSX (OOXML) sin Composer: ZipArchive + XML.
 */
final class PlanillaXlsx
{
    /**
     * @param array<string, mixed> $pack
     * @param array<string, mixed> $operacion
     */
    public static function matriz(array $pack, array $operacion): string
    {
        if (!class_exists(ZipArchive::class)) {
            throw new ApiException('Extensión zip requerida para exportar XLSX', 500);
        }
        Uploads::ensure();
        $folio = preg_replace('/[^A-Za-z0-9._-]/', '', (string) ($operacion['folio'] ?? 'op')) ?: 'op';
        $rel = 'comex/xlsx/landed-' . $folio . '.xlsx';
        $abs = Uploads::path($rel);

        $est = is_array($pack['estimada']['calculo'] ?? null) ? $pack['estimada']['calculo'] : [];
        $real = is_array($pack['real']['calculo'] ?? null) ? $pack['real']['calculo'] : [];
        $comp = is_array($pack['comparacion'] ?? null) ? $pack['comparacion'] : [];

        $sheets = [
            'Comparacion' => self::sheetComparacion($operacion, $est, $real, $comp),
            'Estimacion' => self::sheetVersion('ESTIMACION', $est),
            'CostoReal' => self::sheetVersion('COSTO REAL', $real),
        ];

        $tmp = rtrim(sys_get_temp_dir(), '/') . '/landed-' . bin2hex(random_bytes(4)) . '.xlsx';
        $zip = new ZipArchive();
        if ($zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new ApiException('No se pudo crear el XLSX temporal', 500);
        }
        $zip->addFromString('[Content_Types].xml', self::contentTypes(count($sheets)));
        $zip->addFromString('_rels/.rels', self::relsRoot());
        $zip->addFromString('xl/workbook.xml', self::workbook(array_keys($sheets)));
        $zip->addFromString('xl/_rels/workbook.xml.rels', self::relsWorkbook(count($sheets)));
        $zip->addFromString('xl/styles.xml', self::styles());
        $i = 1;
        foreach ($sheets as $xml) {
            $zip->addFromString('xl/worksheets/sheet' . $i . '.xml', $xml);
            $i++;
        }
        $zip->close();
        if (!is_file($tmp) || filesize($tmp) < 32) {
            throw new ApiException('XLSX vacío', 500);
        }
        if (!@rename($tmp, $abs) && !@copy($tmp, $abs)) {
            throw new ApiException('No se pudo guardar uploads/comex/xlsx', 500);
        }
        @unlink($tmp);
        @chmod($abs, Uploads::FILE_MODE);
        return 'uploads/' . $rel;
    }

    /**
     * @param array<string, mixed> $operacion
     * @param array<string, mixed> $est
     * @param array<string, mixed> $real
     * @param array<string, mixed> $comp
     */
    private static function sheetComparacion(array $operacion, array $est, array $real, array $comp): string
    {
        $rows = [];
        $rows[] = ['LPAEZSIS COMEX — Matriz landed cost (Estimación vs Costo real)'];
        $rows[] = ['Folio', (string) ($operacion['folio'] ?? ''), 'Tipo', (string) ($operacion['tipo'] ?? '')];
        $rows[] = ['IVA aduanero %', (string) ($est['iva_pct'] ?? $real['iva_pct'] ?? 19), 'TC USD', (string) ($est['tipo_cambio_usd'] ?? $real['tipo_cambio_usd'] ?? '')];
        $rows[] = [];
        $rows[] = ['Concepto', 'Estimación CLP', 'Real CLP', 'Delta CLP', 'Delta %'];
        $labels = [
            'fob_clp' => 'FOB',
            'cif_clp' => 'CIF',
            'iva_clp' => 'IVA aduanero 19%',
            'gastos_locales_clp' => 'Gastos locales',
            'gastos_origen_clp' => 'Gastos origen',
            'landed_clp' => 'Landed CLP',
            'landed_usd' => 'Landed USD',
        ];
        $tot = is_array($comp['totales'] ?? null) ? $comp['totales'] : [];
        foreach ($labels as $k => $label) {
            $d = is_array($tot[$k] ?? null) ? $tot[$k] : [];
            $rows[] = [
                $label,
                (float) ($d['estimada'] ?? 0),
                (float) ($d['real'] ?? 0),
                (float) ($d['delta'] ?? 0),
                (string) ($d['delta_pct'] ?? ''),
            ];
        }
        $rows[] = [];
        $rows[] = ['SKU', 'Landed est CLP', 'Landed real CLP', 'Delta', 'Unit est CLP', 'Unit real CLP', 'Unit est USD', 'Unit real USD'];
        foreach ($comp['items'] ?? [] as $it) {
            if (!is_array($it)) {
                continue;
            }
            $rows[] = [
                (string) ($it['sku'] ?? ''),
                (float) ($it['estimada'] ?? 0),
                (float) ($it['real'] ?? 0),
                (float) ($it['delta'] ?? 0),
                (float) ($it['unitario_clp_est'] ?? 0),
                (float) ($it['unitario_clp_real'] ?? 0),
                (float) ($it['unitario_usd_est'] ?? 0),
                (float) ($it['unitario_usd_real'] ?? 0),
            ];
        }
        return self::sheetXml($rows);
    }

    /**
     * @param array<string, mixed> $calculo
     */
    private static function sheetVersion(string $titulo, array $calculo): string
    {
        $rows = [];
        $rows[] = ['LPAEZSIS COMEX — ' . $titulo];
        $rows[] = ['Moneda FOB', (string) ($calculo['moneda_origen'] ?? ''), 'TC USD', (float) ($calculo['tipo_cambio_usd'] ?? 0), 'TC EUR', (float) ($calculo['tipo_cambio_eur'] ?? 0), 'IVA %', (float) ($calculo['iva_pct'] ?? 19)];
        $rows[] = [];
        $rows[] = ['Gastos', 'Ámbito', 'Moneda', 'Monto', 'CLP', 'CIF'];
        foreach ($calculo['gastos'] ?? [] as $g) {
            if (!is_array($g)) {
                continue;
            }
            $rows[] = [
                (string) ($g['nombre'] ?? ''),
                (string) ($g['ambito'] ?? ''),
                (string) ($g['moneda'] ?? ''),
                (float) ($g['monto'] ?? 0),
                (float) ($g['monto_clp'] ?? 0),
                !empty($g['cif']) ? 'SI' : 'NO',
            ];
        }
        $rows[] = [];
        $rows[] = ['SKU', 'Cant', 'FOB unit', 'FOB orig', 'Factor', 'FOB CLP', 'CIF CLP', 'IVA CLP', 'Locales CLP', 'Landed CLP', 'Unit CLP', 'Landed USD', 'Unit USD'];
        foreach ($calculo['items'] ?? [] as $it) {
            if (!is_array($it)) {
                continue;
            }
            $rows[] = [
                (string) ($it['sku'] ?? ''),
                (float) ($it['cantidad'] ?? 0),
                (float) ($it['fob_unitario'] ?? 0),
                (float) ($it['fob_origen'] ?? 0),
                (float) ($it['factor'] ?? $it['share'] ?? 0),
                (float) ($it['fob_clp'] ?? 0),
                (float) ($it['cif_clp'] ?? 0),
                (float) ($it['iva_clp'] ?? 0),
                (float) ($it['gastos_locales_clp'] ?? 0),
                (float) ($it['landed_total_clp'] ?? 0),
                (float) ($it['landed_unitario_clp'] ?? 0),
                (float) ($it['landed_total_usd'] ?? 0),
                (float) ($it['landed_unitario_usd'] ?? 0),
            ];
        }
        $t = is_array($calculo['totales'] ?? null) ? $calculo['totales'] : [];
        $rows[] = [];
        $rows[] = ['TOTAL FOB CLP', (float) ($t['fob_clp'] ?? 0), 'CIF', (float) ($t['cif_clp'] ?? 0), 'IVA', (float) ($t['iva_clp'] ?? 0), 'LANDED CLP', (float) ($t['landed_clp'] ?? 0), 'LANDED USD', (float) ($t['landed_usd'] ?? 0)];
        return self::sheetXml($rows);
    }

    /** @param list<list<mixed>> $rows */
    private static function sheetXml(array $rows): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
        foreach ($rows as $rIdx => $row) {
            $xml .= '<row r="' . ($rIdx + 1) . '">';
            foreach ($row as $cIdx => $val) {
                $ref = self::col($cIdx) . ($rIdx + 1);
                if (is_int($val) || is_float($val)) {
                    $xml .= '<c r="' . $ref . '" t="n"><v>' . $val . '</v></c>';
                } else {
                    $xml .= '<c r="' . $ref . '" t="inlineStr"><is><t>' . self::xml((string) $val) . '</t></is></c>';
                }
            }
            $xml .= '</row>';
        }
        return $xml . '</sheetData></worksheet>';
    }

    private static function col(int $i): string
    {
        $s = '';
        $n = $i;
        do {
            $s = chr(65 + ($n % 26)) . $s;
            $n = intdiv($n, 26) - 1;
        } while ($n >= 0);
        return $s;
    }

    private static function xml(string $s): string
    {
        return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    /** @param list<string> $names */
    private static function workbook(array $names): string
    {
        $sheets = '';
        foreach ($names as $i => $name) {
            $sheets .= '<sheet name="' . self::xml($name) . '" sheetId="' . ($i + 1) . '" r:id="rId' . ($i + 1) . '"/>';
        }
        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
            . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets>' . $sheets . '</sheets></workbook>';
    }

    private static function relsWorkbook(int $n): string
    {
        $rels = '';
        for ($i = 1; $i <= $n; $i++) {
            $rels .= '<Relationship Id="rId' . $i . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $i . '.xml"/>';
        }
        $rels .= '<Relationship Id="rId' . ($n + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . $rels . '</Relationships>';
    }

    private static function relsRoot(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';
    }

    private static function contentTypes(int $n): string
    {
        $ov = '';
        for ($i = 1; $i <= $n; $i++) {
            $ov .= '<Override PartName="/xl/worksheets/sheet' . $i . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }
        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . $ov . '</Types>';
    }

    private static function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="1"><font><sz val="11"/><name val="Calibri"/></font></fonts>'
            . '<fills count="1"><fill><patternFill patternType="none"/></fill></fills>'
            . '<borders count="1"><border/></borders>'
            . '<cellStyleXfs count="1"><xf/></cellStyleXfs>'
            . '<cellXfs count="1"><xf/></cellXfs>'
            . '</styleSheet>';
    }
}
