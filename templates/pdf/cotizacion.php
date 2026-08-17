<?php

declare(strict_types=1);

/**
 * @var array $cotizacion
 * @var array $items
 * @var array $emisora
 * @var array|null $vendedor
 * @var array $marcas
 * @var string $logoSrc
 * @var string $bannerMarcasSrc
 * @var callable $h
 */
$h = isset($h) && is_callable($h) ? $h : static function ($v) {
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
};
$cotizacion = is_array($cotizacion) ? $cotizacion : array();
$items = is_array($items) ? $items : array();
$emisora = is_array($emisora) ? $emisora : array();
$marcas = is_array($marcas) ? $marcas : array();
$logoSrc = isset($logoSrc) ? (string) $logoSrc : '';
$bannerMarcasSrc = isset($bannerMarcasSrc) ? (string) $bannerMarcasSrc : '';

$numero = isset($cotizacion['numero']) ? (string) $cotizacion['numero'] : (isset($cotizacion['folio']) ? (string) $cotizacion['folio'] : '');
$cotizacion['numero'] = $numero;

$ivaPct = isset($cotizacion['iva_pct']) ? (float) $cotizacion['iva_pct'] : 19.0;
$subtotal = (float) (isset($cotizacion['subtotal']) ? $cotizacion['subtotal'] : 0);
$descuento = (float) (isset($cotizacion['descuento']) ? $cotizacion['descuento'] : 0);
$iva = (float) (isset($cotizacion['iva']) ? $cotizacion['iva'] : 0);
$total = (float) (isset($cotizacion['total']) ? $cotizacion['total'] : 0);

$dirEmisora = isset($emisora['direccion']) ? (string) $emisora['direccion'] : '';
if (!empty($emisora['ciudad'])) {
    $dirEmisora .= ($dirEmisora !== '' ? ', ' : '') . (string) $emisora['ciudad'];
}

$tels = array();
if (!empty($emisora['telefono'])) {
    $tels[] = (string) $emisora['telefono'];
}
$telTxt = implode(' · ', $tels);

$contactoNombre = trim(
    (isset($cotizacion['contacto_nombre']) ? (string) $cotizacion['contacto_nombre'] : '')
    . ' '
    . (isset($cotizacion['contacto_apellido']) ? (string) $cotizacion['contacto_apellido'] : '')
);
$contactoEmail = isset($cotizacion['contacto_email']) ? (string) $cotizacion['contacto_email'] : '';
$validez = isset($cotizacion['validez_oferta']) && (string) $cotizacion['validez_oferta'] !== ''
    ? (string) $cotizacion['validez_oferta']
    : (isset($cotizacion['fecha_validez']) ? (string) $cotizacion['fecha_validez'] : '');
$moneda = isset($cotizacion['moneda']) && (string) $cotizacion['moneda'] !== '' ? (string) $cotizacion['moneda'] : 'CLP';
$pago = isset($cotizacion['condiciones_pago']) && (string) $cotizacion['condiciones_pago'] !== ''
    ? (string) $cotizacion['condiciones_pago']
    : 'Por definir';
$plazo = isset($cotizacion['plazo_entrega']) && (string) $cotizacion['plazo_entrega'] !== ''
    ? (string) $cotizacion['plazo_entrega']
    : 'Por definir';
$lugar = isset($cotizacion['lugar_entrega']) && (string) $cotizacion['lugar_entrega'] !== ''
    ? (string) $cotizacion['lugar_entrega']
    : 'Por definir';

$vendNombre = '';
if (is_array($vendedor) && !empty($vendedor['nombre_completo'])) {
    $vendNombre = (string) $vendedor['nombre_completo'];
} elseif (!empty($cotizacion['vendedor_nombre'])) {
    $vendNombre = (string) $cotizacion['vendedor_nombre'];
}

$fmtFecha = static function ($v) {
    $s = trim((string) $v);
    if ($s === '') {
        return '';
    }
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $s, $m)) {
        return $m[3] . '-' . $m[2] . '-' . $m[1];
    }
    return $s;
};
$fechaEmision = $fmtFecha(isset($cotizacion['fecha_emision']) ? $cotizacion['fecha_emision'] : '');
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Cotización <?php echo $h($numero); ?></title>
<style>
@page { margin: 10mm 11mm 10mm 11mm; }
body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 9pt; color: #333333; }
table { border-collapse: collapse; }
.wrap { width: 100%; }
.brand { color: #0b3c5d; }
.muted { color: #555555; font-size: 8pt; }
.box-doc { background: #0b3c5d; color: #ffffff; text-align: center; padding: 8px 10px; }
.box-doc .tipo { font-size: 8pt; letter-spacing: 1px; }
.box-doc .num { font-size: 14pt; font-weight: bold; margin-top: 4px; }
.logo { max-width: 110px; max-height: 52px; }
h1, h2, h3 { margin: 0; padding: 0; }
.rule { height: 3px; background: #0b3c5d; font-size: 1px; line-height: 1px; }
.rule-gold { height: 2px; background: #c9a227; font-size: 1px; line-height: 1px; }
.section { margin-top: 10px; }
.th { background: #0b3c5d; color: #ffffff; font-size: 7.5pt; font-weight: bold; padding: 5px 4px; }
.td { border-bottom: 0.4pt solid #cfd8dc; padding: 4px; font-size: 8pt; vertical-align: top; }
.td-alt { background: #f4f7f9; }
.num { text-align: right; white-space: nowrap; }
.panel { border: 0.6pt solid #0b3c5d; }
.panel td { padding: 7px 8px; vertical-align: top; }
.panel-h { background: #0b3c5d; color: #ffffff; font-size: 8pt; font-weight: bold; padding: 5px 8px; }
.tot-lab { padding: 3px 6px; font-size: 8.5pt; }
.tot-val { padding: 3px 6px; font-size: 8.5pt; text-align: right; }
.tot-final { background: #0b3c5d; color: #ffffff; font-weight: bold; }
.sign { margin-top: 12px; text-align: center; }
.sign-line { border-top: 0.6pt solid #333333; width: 220px; margin: 14px auto 4px auto; }
.marcas-h { text-align: center; color: #0b3c5d; font-size: 8pt; letter-spacing: 1px; font-weight: bold; margin: 8px 0 4px 0; }
.marca { text-align: center; vertical-align: middle; padding: 6px 4px; }
.marca img { max-height: 32px; max-width: 95px; }
.marca-n { font-size: 6.5pt; color: #0b3c5d; margin-top: 3px; }
.marcas-banner { width: 100%; height: auto; max-height: 32mm; display: block; margin-top: 2px; }
.foot { margin-top: 4px; font-size: 7pt; color: #777777; text-align: center; }
</style>
</head>
<body>
<table class="wrap" width="100%">
    <tr>
        <td width="62%" valign="top">
            <?php if ($logoSrc !== '') { ?>
                <img class="logo" src="<?php echo $h($logoSrc); ?>" alt="Logo">
            <?php } ?>
            <div style="margin-top:4px;">
                <strong class="brand" style="font-size:12pt;"><?php echo $h(isset($emisora['razon_social']) ? $emisora['razon_social'] : ''); ?></strong><br>
                <span class="muted">RUT: <?php echo $h(isset($emisora['rut']) ? $emisora['rut'] : ''); ?></span><br>
                <span class="muted"><?php echo $h($dirEmisora); ?></span><br>
                <?php if ($telTxt !== '') { ?><span class="muted">Tel: <?php echo $h($telTxt); ?></span><br><?php } ?>
                <?php if (!empty($emisora['sitio_web'])) { ?><span class="muted"><?php echo $h($emisora['sitio_web']); ?></span><?php } ?>
            </div>
        </td>
        <td width="38%" valign="top">
            <div class="box-doc">
                <div class="tipo">COTIZACIÓN</div>
                <div class="num"><?php echo $h($cotizacion['numero']); ?></div>
            </div>
            <div class="muted" style="margin-top:6px; text-align:right;">
                Moneda: <?php echo $h($moneda); ?>
            </div>
        </td>
    </tr>
</table>
<div class="rule" style="margin-top:8px;">&nbsp;</div>
<div class="rule-gold">&nbsp;</div>

<table class="wrap section" width="100%">
    <tr>
        <td width="54%" valign="top" style="padding-right:8px;">
            <div class="panel-h">Cliente</div>
            <table width="100%" class="panel">
                <tr><td><strong><?php echo $h(isset($cotizacion['razon_social']) ? $cotizacion['razon_social'] : ''); ?></strong></td></tr>
                <tr><td>RUT: <?php echo $h(isset($cotizacion['rut']) ? $cotizacion['rut'] : ''); ?></td></tr>
                <tr><td>Dirección: <?php echo $h(isset($cotizacion['direccion']) ? $cotizacion['direccion'] : '—'); ?></td></tr>
                <tr><td>Comuna: <?php echo $h(isset($cotizacion['comuna']) && $cotizacion['comuna'] !== '' ? $cotizacion['comuna'] : '—'); ?></td></tr>
                <tr><td>Contacto: <?php echo $h($contactoNombre !== '' ? $contactoNombre : '—'); ?></td></tr>
                <tr><td>Email: <?php echo $h($contactoEmail !== '' ? $contactoEmail : '—'); ?></td></tr>
            </table>
        </td>
        <td width="46%" valign="top">
            <div class="panel-h">Emisión y validez</div>
            <table width="100%" class="panel">
                <tr><td>Fecha de emisión</td><td style="text-align:right;"><?php echo $h($fechaEmision !== '' ? $fechaEmision : '—'); ?></td></tr>
                <tr><td>Días de validez</td><td style="text-align:right;"><?php echo $h($validez !== '' ? $validez : '—'); ?></td></tr>
            </table>
        </td>
    </tr>
</table>

<table class="wrap section" width="100%">
    <tr>
        <td class="th" width="12%">Código</td>
        <td class="th" width="40%">Descripción</td>
        <td class="th num" width="10%">Cantidad</td>
        <td class="th" width="8%">Unidad</td>
        <td class="th num" width="15%">P. unitario</td>
        <td class="th num" width="15%">Total línea</td>
    </tr>
    <?php
    $i = 0;
    foreach ($items as $it) {
        if (!is_array($it)) {
            continue;
        }
        $i++;
        $tipo = isset($it['tipo_item']) ? (string) $it['tipo_item'] : 'producto';
        $desc = (string) (isset($it['descripcion']) ? $it['descripcion'] : '');
        if ($tipo === 'servicio') {
            $desc = '[Servicio] ' . $desc;
        }
        $un = isset($it['unidad']) && (string) $it['unidad'] !== '' ? (string) $it['unidad'] : ($tipo === 'servicio' ? 'GL' : 'UN');
        $cant = (float) (isset($it['cantidad']) ? $it['cantidad'] : 0);
        $pu = (float) (isset($it['precio_unitario']) ? $it['precio_unitario'] : 0);
        $lin = (float) (isset($it['subtotal']) ? $it['subtotal'] : ($cant * $pu));
        $alt = ($i % 2 === 0) ? ' td-alt' : '';
        ?>
        <tr>
            <td class="td<?php echo $alt; ?>"><?php echo $h(isset($it['codigo']) ? $it['codigo'] : ''); ?></td>
            <td class="td<?php echo $alt; ?>"><?php echo $h($desc); ?></td>
            <td class="td num<?php echo $alt; ?>"><?php echo $h(number_format($cant, 0, ',', '.')); ?></td>
            <td class="td<?php echo $alt; ?>"><?php echo $h($un); ?></td>
            <td class="td num<?php echo $alt; ?>">$<?php echo $h(number_format($pu, 0, ',', '.')); ?></td>
            <td class="td num<?php echo $alt; ?>">$<?php echo $h(number_format($lin, 0, ',', '.')); ?></td>
        </tr>
    <?php } ?>
</table>

<table class="wrap section" width="100%">
    <tr>
        <td width="54%" valign="top" style="padding-right:8px;">
            <div class="panel-h">Condiciones comerciales</div>
            <table width="100%" class="panel">
                <tr><td><strong>Datos bancarios</strong><br>Banco Estado<br>Cuenta Vista #35171442603</td></tr>
                <tr><td><strong>Lugar de entrega</strong><br><?php echo $h($lugar); ?></td></tr>
                <tr><td><strong>Plazo de entrega</strong><br><?php echo $h($plazo); ?></td></tr>
                <tr><td><strong>Forma de pago</strong><br><?php echo $h($pago); ?></td></tr>
            </table>
        </td>
        <td width="46%" valign="top">
            <div class="panel-h">Totales</div>
            <table width="100%" class="panel">
                <tr>
                    <td class="tot-lab">Subtotal</td>
                    <td class="tot-val">$<?php echo $h(number_format($subtotal, 0, ',', '.')); ?></td>
                </tr>
                <?php if ($descuento > 0) { ?>
                <tr>
                    <td class="tot-lab">Descuento</td>
                    <td class="tot-val">$<?php echo $h(number_format($descuento, 0, ',', '.')); ?></td>
                </tr>
                <?php } ?>
                <tr>
                    <td class="tot-lab">IVA <?php echo $h(number_format($ivaPct, 0, ',', '.')); ?>%</td>
                    <td class="tot-val">$<?php echo $h(number_format($iva, 0, ',', '.')); ?></td>
                </tr>
                <tr>
                    <td class="tot-lab tot-final">Total general</td>
                    <td class="tot-val tot-final">$<?php echo $h(number_format($total, 0, ',', '.')); ?></td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<?php if (!empty($cotizacion['notas'])) { ?>
<p class="muted section"><strong>Notas:</strong> <?php echo $h($cotizacion['notas']); ?></p>
<?php } ?>

<div class="sign">
    <div class="sign-line"></div>
    <div><strong><?php echo $h($vendNombre !== '' ? $vendNombre : 'Ejecutivo comercial'); ?></strong></div>
    <div class="muted">Firma y aceptación de la oferta</div>
</div>

<div class="marcas-h">MARCAS REPRESENTADAS Y DISTRIBUIDAS</div>
<?php if ($bannerMarcasSrc !== '') { ?>
<img class="marcas-banner" src="<?php echo $h($bannerMarcasSrc); ?>" alt="Marcas representadas y distribuidas">
<?php } else { ?>
<table class="wrap" width="100%">
    <?php
    $chunks = array();
    $row = array();
    foreach ($marcas as $marca) {
        if (!is_array($marca)) {
            continue;
        }
        $row[] = $marca;
        if (count($row) === 6) {
            $chunks[] = $row;
            $row = array();
        }
    }
    if (count($row) > 0) {
        $chunks[] = $row;
    }
    foreach ($chunks as $fila) {
        echo '<tr>';
        $n = 0;
        foreach ($fila as $marca) {
            $n++;
            $src = isset($marca['src']) ? (string) $marca['src'] : '';
            $nom = isset($marca['nombre']) ? (string) $marca['nombre'] : '';
            echo '<td class="marca" width="16%">';
            if ($src !== '') {
                echo '<img src="' . $h($src) . '" alt="' . $h($nom) . '">';
            }
            echo '</td>';
        }
        while ($n < 6) {
            $n++;
            echo '<td class="marca" width="16%">&nbsp;</td>';
        }
        echo '</tr>';
    }
    ?>
</table>
<?php } ?>
<div class="foot">Documento generado por CRM LPAEZsis · crm.lpaezsis.cl</div>
</body>
</html>
