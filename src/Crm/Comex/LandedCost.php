<?php

declare(strict_types=1);

namespace Crm\Comex;

use Crm\ApiException;

/**
 * Landed cost Chile: prorrateo por FOB, FX USD/EUR→CLP, IVA aduanero sobre CIF.
 */
final class LandedCost
{
    public const VERSION_ESTIMADA = 'ESTIMADA';
    public const VERSION_REAL = 'REAL';
    public const IVA_DEFAULT = 19.0;

    /** @var array<string, array{nombre: string, ambito: string, moneda: string, cif: bool}> */
    public const GASTOS_CATALOGO = [
        'FLETE_INTL' => ['nombre' => 'Flete internacional', 'ambito' => 'ORIGEN', 'moneda' => 'USD', 'cif' => true],
        'SEGURO' => ['nombre' => 'Seguro', 'ambito' => 'ORIGEN', 'moneda' => 'USD', 'cif' => true],
        'ADUANA' => ['nombre' => 'Aduana', 'ambito' => 'LOCAL', 'moneda' => 'CLP', 'cif' => false],
        'AGENCIA' => ['nombre' => 'Agencia', 'ambito' => 'LOCAL', 'moneda' => 'CLP', 'cif' => false],
        'FLETE_INTERNO' => ['nombre' => 'Flete interno', 'ambito' => 'LOCAL', 'moneda' => 'CLP', 'cif' => false],
        'BANCARIOS' => ['nombre' => 'Gastos bancarios', 'ambito' => 'LOCAL', 'moneda' => 'CLP', 'cif' => false],
    ];

    public static function ivaPct(?float $override = null): float
    {
        if ($override !== null && $override >= 0) {
            return $override;
        }
        if (function_exists('crm_iva_pct')) {
            return crm_iva_pct();
        }
        return self::IVA_DEFAULT;
    }

    public static function aClp(float $monto, string $moneda, float $tcUsd, float $tcEur): float
    {
        $moneda = strtoupper(trim($moneda));
        return match ($moneda) {
            'CLP' => $monto,
            'USD' => $monto * $tcUsd,
            'EUR' => $monto * $tcEur,
            default => throw new ApiException('Moneda no soportada: ' . $moneda, 400),
        };
    }

    /**
     * @param list<float> $pesos
     * @return list<float>
     */
    public static function prorratear(float $total, array $pesos, int $decimales = 2): array
    {
        $n = count($pesos);
        if ($n === 0) {
            return [];
        }
        $suma = 0.0;
        foreach ($pesos as $p) {
            $suma += (float) $p;
        }
        if ($suma <= 0) {
            throw new ApiException('El FOB total debe ser mayor que 0 para prorratear', 400);
        }
        $out = [];
        $acc = 0.0;
        for ($i = 0; $i < $n; $i++) {
            if ($i === $n - 1) {
                $out[] = round($total - $acc, $decimales);
                break;
            }
            $parte = round($total * ((float) $pesos[$i] / $suma), $decimales);
            $out[] = $parte;
            $acc += $parte;
        }
        return $out;
    }

    /**
     * @param array{
     *   moneda_origen?: string,
     *   tipo_cambio_usd?: float|int|string,
     *   tipo_cambio_eur?: float|int|string,
     *   iva_pct?: float|int|string,
     *   version?: string,
     *   gastos?: list<array<string, mixed>>,
     *   items: list<array<string, mixed>>
     * } $input
     * @return array<string, mixed>
     */
    public static function calcular(array $input): array
    {
        $version = strtoupper(trim((string) ($input['version'] ?? self::VERSION_ESTIMADA)));
        if ($version !== self::VERSION_ESTIMADA && $version !== self::VERSION_REAL) {
            throw new ApiException('version debe ser ESTIMADA o REAL', 400);
        }
        $monedaOrigen = strtoupper(trim((string) ($input['moneda_origen'] ?? 'USD')));
        if ($monedaOrigen !== 'USD' && $monedaOrigen !== 'EUR') {
            throw new ApiException('moneda_origen debe ser USD o EUR', 400);
        }
        $tcUsd = self::num($input['tipo_cambio_usd'] ?? 0);
        $tcEur = self::num($input['tipo_cambio_eur'] ?? 0);
        if ($monedaOrigen === 'USD' && $tcUsd <= 0) {
            throw new ApiException('tipo_cambio_usd debe ser mayor que 0', 400);
        }
        if ($monedaOrigen === 'EUR' && $tcEur <= 0) {
            throw new ApiException('tipo_cambio_eur debe ser mayor que 0', 400);
        }
        $ivaPct = self::ivaPct(isset($input['iva_pct']) ? self::num($input['iva_pct']) : null);

        $gastosIn = $input['gastos'] ?? [];
        if (!is_array($gastosIn) || $gastosIn === []) {
            $gastosIn = self::gastosCero();
        }
        $gastos = [];
        $cifClp = 0.0;
        $localesClp = 0.0;
        $origenClp = 0.0;
        foreach ($gastosIn as $g) {
            if (!is_array($g)) {
                continue;
            }
            $norm = self::normalizarGasto($g, $tcUsd, $tcEur);
            $gastos[] = $norm;
            if ($norm['cif']) {
                $cifClp += $norm['monto_clp'];
            } else {
                $localesClp += $norm['monto_clp'];
            }
            if ($norm['ambito'] === 'ORIGEN') {
                $origenClp += $norm['monto_clp'];
            }
        }

        $itemsIn = $input['items'] ?? [];
        if (!is_array($itemsIn) || $itemsIn === []) {
            throw new ApiException('La evaluación requiere ítems con FOB', 400);
        }
        $prep = [];
        $pesosFob = [];
        foreach ($itemsIn as $it) {
            if (!is_array($it)) {
                continue;
            }
            $qty = self::num($it['cantidad'] ?? 0);
            $fobU = self::num($it['fob_unitario'] ?? $it['precio_unitario'] ?? 0);
            if ($qty <= 0) {
                throw new ApiException('Cantidad de ítem debe ser mayor que 0', 400);
            }
            $fobOrig = round($qty * $fobU, 4);
            $fobItemClp = round(self::aClp($fobOrig, $monedaOrigen, $tcUsd, $tcEur), 2);
            $prep[] = [
                'operacion_item_id' => (int) ($it['id'] ?? $it['operacion_item_id'] ?? 0),
                'sku' => (string) ($it['sku'] ?? ''),
                'descripcion' => (string) ($it['descripcion'] ?? ''),
                'cantidad' => $qty,
                'fob_unitario' => $fobU,
                'fob_origen' => $fobOrig,
                'fob_clp' => $fobItemClp,
            ];
            $pesosFob[] = $fobOrig;
        }
        if ($prep === []) {
            throw new ApiException('No hay ítems válidos', 400);
        }

        $fobTotalOrig = array_sum($pesosFob);
        $partesCif = self::prorratear($cifClp, $pesosFob);
        $partesLoc = self::prorratear($localesClp, $pesosFob);

        $lineas = [];
        $totFobClp = 0.0;
        $totCif = 0.0;
        $totIva = 0.0;
        $totLoc = 0.0;
        $totLanded = 0.0;
        foreach ($prep as $i => $row) {
            $cifItem = round($row['fob_clp'] + $partesCif[$i], 2);
            $ivaItem = round($cifItem * ($ivaPct / 100), 2);
            $locItem = $partesLoc[$i];
            $landed = round($cifItem + $ivaItem + $locItem, 2);
            $unit = $row['cantidad'] > 0 ? round($landed / $row['cantidad'], 4) : 0.0;
            $share = $fobTotalOrig > 0 ? round($row['fob_origen'] / $fobTotalOrig, 6) : 0.0;
            $linea = $row + [
                'share' => $share,
                'gastos_cif_clp' => $partesCif[$i],
                'cif_clp' => $cifItem,
                'iva_clp' => $ivaItem,
                'gastos_locales_clp' => $locItem,
                'landed_total_clp' => $landed,
                'landed_unitario_clp' => $unit,
            ];
            $lineas[] = $linea;
            $totFobClp += $row['fob_clp'];
            $totCif += $cifItem;
            $totIva += $ivaItem;
            $totLoc += $locItem;
            $totLanded += $landed;
        }

        return [
            'version' => $version,
            'moneda_origen' => $monedaOrigen,
            'tipo_cambio_usd' => $tcUsd,
            'tipo_cambio_eur' => $tcEur,
            'iva_pct' => $ivaPct,
            'gastos' => $gastos,
            'items' => $lineas,
            'totales' => [
                'fob_origen' => round($fobTotalOrig, 4),
                'fob_clp' => round($totFobClp, 2),
                'gastos_origen_clp' => round($origenClp, 2),
                'gastos_cif_clp' => round($cifClp, 2),
                'cif_clp' => round($totCif, 2),
                'iva_clp' => round($totIva, 2),
                'gastos_locales_clp' => round($totLoc, 2),
                'landed_clp' => round($totLanded, 2),
            ],
        ];
    }

    /**
     * @param array<string, mixed>|null $estimada
     * @param array<string, mixed>|null $real
     * @return array<string, mixed>
     */
    public static function comparar(?array $estimada, ?array $real): array
    {
        $keys = ['fob_clp', 'cif_clp', 'iva_clp', 'gastos_locales_clp', 'gastos_origen_clp', 'landed_clp'];
        $delta = [];
        foreach ($keys as $k) {
            $e = (float) ($estimada['totales'][$k] ?? 0);
            $r = (float) ($real['totales'][$k] ?? 0);
            $d = round($r - $e, 2);
            $pct = $e != 0.0 ? round(($d / $e) * 100, 2) : null;
            $delta[$k] = ['estimada' => $e, 'real' => $r, 'delta' => $d, 'delta_pct' => $pct];
        }
        $items = [];
        $bySkuEst = [];
        foreach ($estimada['items'] ?? [] as $it) {
            if (is_array($it)) {
                $bySkuEst[(string) ($it['sku'] ?? '')] = $it;
            }
        }
        foreach ($real['items'] ?? [] as $it) {
            if (!is_array($it)) {
                continue;
            }
            $sku = (string) ($it['sku'] ?? '');
            $e = $bySkuEst[$sku] ?? null;
            $eLand = (float) ($e['landed_total_clp'] ?? 0);
            $rLand = (float) ($it['landed_total_clp'] ?? 0);
            $items[] = [
                'sku' => $sku,
                'descripcion' => $it['descripcion'] ?? ($e['descripcion'] ?? ''),
                'estimada' => $eLand,
                'real' => $rLand,
                'delta' => round($rLand - $eLand, 2),
            ];
        }
        return [
            'disponible' => $estimada !== null && $real !== null,
            'totales' => $delta,
            'items' => $items,
        ];
    }

    /** @return list<array<string, mixed>> */
    public static function gastosCero(): array
    {
        $out = [];
        foreach (self::GASTOS_CATALOGO as $codigo => $meta) {
            $out[] = [
                'codigo' => $codigo,
                'nombre' => $meta['nombre'],
                'ambito' => $meta['ambito'],
                'moneda' => $meta['moneda'],
                'monto' => 0.0,
                'cif' => $meta['cif'],
            ];
        }
        return $out;
    }

    /**
     * @param array<string, mixed> $g
     * @return array<string, mixed>
     */
    private static function normalizarGasto(array $g, float $tcUsd, float $tcEur): array
    {
        $codigo = strtoupper(trim((string) ($g['codigo'] ?? 'OTRO')));
        $meta = self::GASTOS_CATALOGO[$codigo] ?? null;
        $ambito = strtoupper(trim((string) ($g['ambito'] ?? ($meta['ambito'] ?? 'LOCAL'))));
        $moneda = strtoupper(trim((string) ($g['moneda'] ?? ($meta['moneda'] ?? 'CLP'))));
        $cif = array_key_exists('cif', $g)
            ? (bool) $g['cif']
            : (bool) ($meta['cif'] ?? false);
        if ($moneda !== 'CLP' && $moneda !== 'USD' && $moneda !== 'EUR') {
            throw new ApiException('Moneda de gasto inválida: ' . $moneda, 400);
        }
        if (($moneda === 'USD' && $tcUsd <= 0) || ($moneda === 'EUR' && $tcEur <= 0)) {
            throw new ApiException('Falta tipo de cambio para ' . $moneda, 400);
        }
        $monto = self::num($g['monto'] ?? 0);
        if ($monto < 0) {
            throw new ApiException('El monto del gasto no puede ser negativo', 400);
        }
        $nombre = trim((string) ($g['nombre'] ?? ($meta['nombre'] ?? $codigo)));
        $clp = round(self::aClp($monto, $moneda, $tcUsd, $tcEur), 2);
        return [
            'codigo' => $codigo,
            'nombre' => $nombre,
            'ambito' => $ambito,
            'moneda' => $moneda,
            'monto' => $monto,
            'monto_clp' => $clp,
            'cif' => $cif,
        ];
    }

    private static function num(mixed $v): float
    {
        if (function_exists('crm_float')) {
            return crm_float($v, 0.0);
        }
        if (is_int($v) || is_float($v)) {
            return (float) $v;
        }
        return is_numeric($v) ? (float) $v : 0.0;
    }
}
