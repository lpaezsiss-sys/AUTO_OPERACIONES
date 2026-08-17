<?php

declare(strict_types=1);

/**
 * @var array $cotizacion
 * @var array $items
 * @var array $emisora
 * @var array|null $vendedor
 * @var array $marcas
 * @var string $logoSrc
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

$numero = isset($cotizacion['numero']) ? (string) $cotizacion['numero'] : (isset($cotizacion['folio']) ? (string) $cotizacion['folio'] : '');
$cotizacion['numero'] = $numero;

$ivaPct = isset($cotizacion['iva_pct']) ? (float) $cotizacion['iva_pct'] : 19.0;
$subtotal = (float) (isset($cotizacion['subtotal']) ? $cotizacion['subtotal'] : 0);
$descuento = (float) (isset($cotizacion['descuento']) ? $cotizacion['descuento'] : 0);
$iva = (float) (isset($cotizacion['iva']) ? $cotizacion['iva'] : 0);
$total = (float) (isset($cotizacion['total']) ? $cotizacion['total'] : 0);

$dirEmisora = isset($emisora['direccion']) ? (string) $emisora['direccion'] : '';
if (!empty($emisora['ciudad']) && strpos($dirEmisora, (string) $emisora['ciudad']) === false) {
    $dirEmisora .= ($dirEmisora !== '' ? ', ' : '') . (string) $emisora['ciudad'];
}

$metaEmisora = array();
if (!empty($emisora['rut'])) {
    $metaEmisora[] = 'RUT ' . (string) $emisora['rut'];
}
if ($dirEmisora !== '') {
    $metaEmisora[] = $dirEmisora;
}
if (!empty($emisora['telefono'])) {
    $metaEmisora[] = (string) $emisora['telefono'];
}
if (!empty($emisora['sitio_web'])) {
    $metaEmisora[] = (string) $emisora['sitio_web'];
}

$contactoNombre = trim(
    (isset($cotizacion['contacto_nombre']) ? (string) $cotizacion['contacto_nombre'] : '')
    . ' '
    . (isset($cotizacion['contacto_apellido']) ? (string) $cotizacion['contacto_apellido'] : '')
);
$contactoEmail = isset($cotizacion['contacto_email']) ? trim((string) $cotizacion['contacto_email']) : '';
$contactoTelefono = isset($cotizacion['contacto_telefono']) ? trim((string) $cotizacion['contacto_telefono']) : '';
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

$dirCliente = isset($cotizacion['direccion']) ? (string) $cotizacion['direccion'] : '';
$comunaCliente = isset($cotizacion['comuna']) ? (string) $cotizacion['comuna'] : '';
if ($comunaCliente !== '' && strpos($dirCliente, $comunaCliente) === false) {
    $dirCliente .= ($dirCliente !== '' ? ', ' : '') . $comunaCliente;
}

$dash = '—';
$contactoNombrePdf = $contactoNombre !== '' ? $contactoNombre : $dash;
$contactoEmailPdf = $contactoEmail !== '' ? $contactoEmail : $dash;
$contactoTelefonoPdf = $contactoTelefono !== '' ? $contactoTelefono : $dash;

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
@page { margin: 8mm 9mm 8mm 9mm; }
body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 8pt; color: #333333; line-height: 1.15; }
table { border-collapse: collapse; }
.wrap { width: 100%; }
.brand { color: #0b3c5d; }
.muted { color: #555555; font-size: 7pt; }
.box-doc { background: #0b3c5d; color: #ffffff; text-align: center; padding: 4px 8px; }
.box-doc .tipo { font-size: 7pt; letter-spacing: 1px; }
.box-doc .num { font-size: 11pt; font-weight: bold; }
.logo { max-width: 92px; max-height: 28px; }
h1, h2, h3 { margin: 0; padding: 0; }
.rule { height: 2px; background: #0b3c5d; font-size: 1px; line-height: 1px; padding: 0; }
.rule-gold { height: 1px; background: #c9a227; font-size: 1px; line-height: 1px; padding: 0; }
.gap { height: 3px; font-size: 1px; line-height: 1px; padding: 0; }
.th { background: #0b3c5d; color: #ffffff; font-size: 7pt; font-weight: bold; padding: 3px 3px; }
.td { border-bottom: 0.4pt solid #cfd8dc; padding: 2px 3px; font-size: 7.5pt; vertical-align: top; }
.td-alt { background: #f4f7f9; }
.num { text-align: right; white-space: nowrap; }
.kv { width: 100%; }
.kv td { padding: 0 4px; vertical-align: middle; font-size: 7.5pt; line-height: 1.2; }
.kv .lab { color: #0b3c5d; font-weight: bold; white-space: nowrap; width: 12%; }
.panel { border: 0.5pt solid #0b3c5d; }
.panel-h { background: #0b3c5d; color: #ffffff; font-size: 7pt; font-weight: bold; padding: 2px 6px; }
.tot-lab { padding: 1px 6px; font-size: 8pt; }
.tot-val { padding: 1px 6px; font-size: 8pt; text-align: right; white-space: nowrap; }
.tot-final { background: #0b3c5d; color: #ffffff; font-weight: bold; }
.sign { margin-top: 6px; text-align: center; font-size: 7.5pt; }
.sign-line { border-top: 0.5pt solid #333333; width: 180px; margin: 8px auto 2px auto; }
.marcas-h { text-align: center; color: #0b3c5d; font-size: 6.5pt; letter-spacing: 0.8px; font-weight: bold; margin: 4px 0 2px 0; }
.marca { text-align: center; vertical-align: middle; padding: 2px; }
.marca img { max-height: 22px; max-width: 80px; }
.marcas-banner { width: 100%; height: 52px; display: block; }
.foot { margin-top: 2px; font-size: 6pt; color: #777777; text-align: center; }
.notas { margin: 3px 0 0 0; font-size: 7pt; color: #555555; }
</style>
</head>
<body>
<table class="wrap" width="100%">
    <tr>
        <td width="14%" valign="middle"><?php if ($logoSrc !== '') { ?>
            <img class="logo" src="<?php echo $h($logoSrc); ?>" alt="Logo">
        <?php } ?></td>
        <td width="56%" valign="middle">
            <strong class="brand" style="font-size:10pt;"><?php echo $h(isset($emisora['razon_social']) ? $emisora['razon_social'] : ''); ?></strong><br>
            <span class="muted"><?php echo $h(implode(' · ', $metaEmisora)); ?></span>
        </td>
        <td width="30%" valign="middle">
            <div class="box-doc">
                <div class="tipo">COTIZACIÓN · <?php echo $h($moneda); ?></div>
                <div class="num"><?php echo $h($cotizacion['numero']); ?></div>
                <div class="tipo" style="margin-top:2px; white-space:nowrap;">Emisión <?php echo $h($fechaEmision !== '' ? $fechaEmision : '—'); ?>
                    · Validez <?php echo $h($validez !== '' ? $validez : '—'); ?></div>
            </div>
        </td>
    </tr>
</table>
<div class="rule"></div>
<div class="rule-gold"></div>
<div class="gap"></div>

<table class="wrap panel" width="100%">
    <tr>
        <td valign="top">
            <div class="panel-h">Cliente</div>
            <table class="kv">
                <tr>
                    <td colspan="2"><strong><?php echo $h(isset($cotizacion['razon_social']) ? $cotizacion['razon_social'] : ''); ?></strong>
                        · RUT <?php echo $h(isset($cotizacion['rut']) ? $cotizacion['rut'] : ''); ?>
                        · <?php echo $h($dirCliente !== '' ? $dirCliente : '—'); ?></td>
                </tr>
                <tr>
                    <td class="lab">Nombre</td>
                    <td><?php echo $h($contactoNombrePdf); ?></td>
                </tr>
                <tr>
                    <td class="lab">Email</td>
                    <td><?php echo $h($contactoEmailPdf); ?></td>
                </tr>
                <tr>
                    <td class="lab">Teléfono</td>
                    <td><?php echo $h($contactoTelefonoPdf); ?></td>
                </tr>
            </table>
        </td>
    </tr>
</table>
<div class="gap"></div>

<table class="wrap" width="100%">
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
        } elseif ($tipo === 'a_pedido' || !empty($it['es_a_pedido'])) {
            $marcaTxt = isset($it['marca_nombre']) ? trim((string) $it['marca_nombre']) : '';
            $desc = '[A pedido]' . ($marcaTxt !== '' ? ' ' . $marcaTxt . ' ·' : '') . ' ' . $desc;
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
<div class="gap"></div>

<table class="wrap" width="100%">
    <tr>
        <td width="58%" valign="top" style="padding-right:6px;">
            <div class="panel-h">Condiciones comerciales</div>
            <table class="kv panel" width="100%">
                <tr>
                    <td class="lab">Lugar de entrega</td>
                    <td><?php echo $h($lugar); ?></td>
                </tr>
                <tr>
                    <td class="lab">Plazo de entrega</td>
                    <td><?php echo $h($plazo); ?></td>
                </tr>
                <tr>
                    <td class="lab">Forma de pago</td>
                    <td><?php echo $h($pago); ?></td>
                </tr>
                <tr>
                    <td class="lab">Datos bancarios</td>
                    <td>Banco Estado · Cuenta Vista #35171442603</td>
                </tr>
            </table>
        </td>
        <td width="42%" valign="top">
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
<p class="notas"><strong>Notas:</strong> <?php echo $h($cotizacion['notas']); ?></p>
<?php } ?>

<div class="sign">
    <div class="sign-line"></div>
    <strong><?php echo $h($vendNombre !== '' ? $vendNombre : 'Ejecutivo comercial'); ?></strong>
    <span class="muted"> · Firma y aceptación de la oferta</span>
</div>

<div class="marcas-h">MARCAS REPRESENTADAS Y DISTRIBUIDAS</div>
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
            } elseif ($nom !== '') {
                echo '<span class="muted">' . $h($nom) . '</span>';
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
<div class="foot">Documento generado por CRM LPAEZsis · crm.lpaezsis.cl</div>
</body>
</html>
