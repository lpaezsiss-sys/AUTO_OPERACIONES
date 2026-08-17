<?php

declare(strict_types=1);

/**
 * Suite de integración PHP 7.4 (SQLite en archivo temporal).
 */
$root = dirname(__DIR__);
$tmp = $root . '/data/test-crm.sqlite';
foreach (array($tmp, $tmp . '-wal', $tmp . '-shm', $tmp . '-journal') as $f) {
    if (is_file($f)) {
        unlink($f);
    }
}

putenv('CRM_DB_DRIVER=sqlite');
putenv('CRM_SQLITE_PATH=' . $tmp);
putenv('APP_DEBUG=1');
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTPS'] = 'off';
$_SERVER['SERVER_PORT'] = '80';

require $root . '/includes/bootstrap.php';

$failed = 0;
$passed = 0;

function assert_true($cond, $msg)
{
    global $failed, $passed;
    if ($cond) {
        $passed++;
        echo "OK  $msg\n";
        return;
    }
    $failed++;
    echo "FAIL  $msg\n";
}

echo "== PHP " . PHP_VERSION . " ==\n";

assert_true(\Crm\Rut::isValid('76.543.210-3'), 'RUT válido 76.543.210-3');
assert_true(!\Crm\Rut::isValid('76.543.210-K'), 'RUT inválido rechazado');
assert_true(\Crm\Rut::format('765432103') === '76.543.210-3', 'Formato RUT');

$pdo = crm_pdo();
assert_true(crm_pdo_driver() === 'sqlite', 'Driver sqlite de prueba');
$prodCount = (int) $pdo->query('SELECT COUNT(*) FROM productos')->fetchColumn();
assert_true($prodCount >= 18, 'Seed de productos de inventario');

$userRow = $pdo->query("SELECT * FROM crm_usuarios WHERE email = 'ivan.p@example.net'")->fetch(PDO::FETCH_ASSOC);
assert_true(is_array($userRow), 'Usuario admin seed');

$login = \Crm\Auth::login('ivan.p@example.net', 'Lpaezsis.2026');
assert_true($login['email'] === 'ivan.p@example.net', 'Login correcto');

$loginPad = \Crm\Auth::login('ivan.p@example.net', '  Lpaezsis.2026  ');
assert_true($loginPad['email'] === 'ivan.p@example.net', 'Login recorta espacios en la clave');

try {
    \Crm\Auth::login('ivan.p@example.net', 'bad');
    assert_true(false, 'Login inválido debe fallar');
} catch (\Crm\ApiException $e) {
    assert_true($e->status === 401, 'Login inválido = 401');
}

$emp = \Crm\Empresas::store(array(
    'rut' => '76.123.456-0',
    'razon_social' => 'Packing Demo SpA',
    'industria' => 'Agroindustria',
    'region' => 'Maule',
    'origen' => 'whatsapp',
    'estado' => 'prospecto',
), $login);
$empId = (int) $emp['empresa']['id'];
assert_true($empId > 0, 'Alta de empresa');

try {
    \Crm\Empresas::store(array(
        'rut' => '11.111.111-K',
        'razon_social' => 'RUT malo',
        'origen' => 'web',
    ), $login);
    assert_true(false, 'RUT inválido en empresa debe fallar');
} catch (\Crm\ApiException $e) {
    assert_true(strpos($e->getMessage(), 'RUT') !== false, 'Error de RUT en empresa');
}

$cto = \Crm\Contactos::store(array(
    'empresa_id' => $empId,
    'nombre' => 'Ana',
    'apellido' => 'Rivas',
    'email' => 'ana.rivas@packingdemo.cl',
    'telefono' => '+56 9 5555 1212',
    'canal_preferido' => 'email',
    'es_principal' => 1,
));
assert_true((int) $cto['contacto']['id'] > 0, 'Alta de contacto');
assert_true((string) $cto['contacto']['telefono'] === '+56 9 5555 1212', 'Teléfono de contacto persistido');

$opp = \Crm\Oportunidades::store(array(
    'empresa_id' => $empId,
    'titulo' => 'Línea de soplado',
    'etapa' => 'propuesta',
    'valor_estimado' => 1500000,
    'origen_canal' => 'whatsapp',
), $login);
assert_true(strpos($opp['oportunidad']['codigo'], 'OPP-') === 0, 'Código OPP generado');

$prod = $pdo->query("SELECT * FROM productos WHERE codigo = '13451' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
assert_true(is_array($prod), 'Producto inventario 13451');

$cot = \Crm\Cotizaciones::store(array(
    'empresa_id' => $empId,
    'estado' => 'borrador',
    'descuento' => 0,
    'items' => array(
        array(
            'producto_id' => (int) $prod['id'],
            'cantidad' => 2,
        ),
    ),
), $login);
$cotId = (int) $cot['cotizacion']['id'];
assert_true(strpos($cot['cotizacion']['folio'], 'COT-') === 0, 'Folio COT generado');
assert_true((float) $cot['cotizacion']['items'][0]['stock_al_cotizar'] === (float) $prod['stock'], 'Snapshot de stock desde productos');
$neto = (float) $cot['cotizacion']['subtotal'];
$iva = (float) $cot['cotizacion']['iva'];
assert_true(abs($iva - round($neto * 0.19, 2)) < 0.001, 'IVA 19%');

$beforeCots = (int) $pdo->query('SELECT COUNT(*) FROM crm_cotizaciones')->fetchColumn();
$beforeItems = (int) $pdo->query('SELECT COUNT(*) FROM crm_cotizacion_items')->fetchColumn();
try {
    \Crm\Cotizaciones::store(array(
        'empresa_id' => $empId,
        'items' => array(
            array('producto_id' => 999999, 'cantidad' => 1, 'codigo' => 'X', 'descripcion' => 'X'),
        ),
    ), $login);
    assert_true(false, 'Producto inexistente debe fallar');
} catch (\Crm\ApiException $e) {
    assert_true(strpos($e->getMessage(), 'inventario') !== false, 'Error de producto inventario');
}
$afterCots = (int) $pdo->query('SELECT COUNT(*) FROM crm_cotizaciones')->fetchColumn();
$afterItems = (int) $pdo->query('SELECT COUNT(*) FROM crm_cotizacion_items')->fetchColumn();
assert_true($beforeCots === $afterCots && $beforeItems === $afterItems, 'Rollback de cotización + ítems');

$stockBefore = (float) $pdo->query("SELECT stock FROM productos WHERE codigo = '13451'")->fetchColumn();
\Crm\Cotizaciones::update($cotId, array(
    'empresa_id' => $empId,
    'estado' => 'enviada',
    'items' => array(
        array('producto_id' => (int) $prod['id'], 'cantidad' => 2),
    ),
), $login);
$stockAfter = (float) $pdo->query("SELECT stock FROM productos WHERE codigo = '13451'")->fetchColumn();
assert_true($stockBefore === $stockAfter, 'CRM no modifica stock de productos');

$act = \Crm\Actividades::store(array(
    'empresa_id' => $empId,
    'titulo' => 'WhatsApp de seguimiento',
    'tipo' => 'whatsapp',
    'canal' => 'whatsapp',
), $login);
assert_true($act['actividad']['canal'] === 'whatsapp', 'Actividad omnicanal');

$dash = \Crm\Dashboard::stats();
assert_true($dash['kpis']['empresas'] >= 4, 'Dashboard cuenta empresas');

$readonly = \Crm\Productos::index();
assert_true(count($readonly['productos']) >= 18, 'Listado read-only de productos');

$cfg = \Crm\ConfiguracionEmpresa::obtener($pdo);
assert_true((int) $cfg['id'] === 1, 'Configuración empresa singleton id=1');
assert_true(strpos((string) $cfg['razon_social'], 'LPAEZsis') !== false, 'Seed empresa emisora LPAEZsis');

$cfgSaved = \Crm\ConfiguracionEmpresa::guardar(array(
    'rut' => '76.987.654-5',
    'razon_social' => 'LPAEZsis-Soluciones Industriales SpA',
    'nombre_fantasia' => 'LPAEZsis',
    'giro' => 'Maquinaria industrial',
    'direccion' => 'Santiago, Chile',
    'ciudad' => 'Santiago',
    'email' => 'ventas@lpaezsis.cl',
), $pdo);
assert_true((int) $cfgSaved['configuracion']['id'] === 1, 'Guardar configuración mantiene id=1');
$cfgCount = (int) $pdo->query('SELECT COUNT(*) FROM crm_configuracion_empresa')->fetchColumn();
assert_true($cfgCount === 1, 'Una sola fila de configuración');

$singletonRejected = false;
try {
    $pdo->exec("INSERT INTO crm_configuracion_empresa (id, rut, razon_social, direccion) VALUES (2, '1-9', 'Otra', 'X')");
} catch (PDOException $e) {
    $singletonRejected = true;
}
assert_true($singletonRejected, 'CHECK/PK impide segunda fila de configuración');

$vendRows = $pdo->query('SELECT * FROM crm_vendedores ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
assert_true(count($vendRows) >= 2, 'Seed de vendedores desde usuarios');
$adminVend = \Crm\Vendedores::porUsuario($pdo, (int) $login['id']);
assert_true(is_array($adminVend), 'Vendedor vinculado al usuario admin');
assert_true(abs((float) $adminVend['comision_porcentaje'] - 2.50) < 0.001, 'Comisión admin 2.50%');
assert_true(abs(\Crm\Comisiones::calcularMonto(1000, 3.5) - 35.0) < 0.001, 'Cálculo comisión 3.5% de 1000');

$acept = \Crm\Cotizaciones::store(array(
    'empresa_id' => $empId,
    'contacto_id' => (int) $cto['contacto']['id'],
    'estado' => 'aceptada',
    'descuento' => 100,
    'items' => array(
        array(
            'producto_id' => (int) $prod['id'],
            'cantidad' => 2,
        ),
    ),
), $login);
$aceptId = (int) $acept['cotizacion']['id'];
$netoAcept = (float) $acept['cotizacion']['subtotal'] - (float) $acept['cotizacion']['descuento'];
$comRow = $pdo->prepare('SELECT * FROM crm_comisiones WHERE cotizacion_id = ? LIMIT 1');
$comRow->execute(array($aceptId));
$comision = $comRow->fetch(PDO::FETCH_ASSOC);
assert_true(is_array($comision), 'Comisión creada al aceptar cotización');
$esperado = \Crm\Comisiones::calcularMonto($netoAcept, $adminVend['comision_porcentaje']);
assert_true(abs((float) $comision['monto_comision'] - $esperado) < 0.001, 'Monto comisión = neto × %');
assert_true((string) $comision['estado'] === 'pendiente', 'Comisión inicial pendiente');
assert_true((int) $acept['cotizacion']['vendedor_id'] === (int) $adminVend['id'], 'Cotización guarda vendedor_id');
assert_true(is_array($acept['cotizacion']['vendedor']), 'Ficha de vendedor en cotización');
assert_true(is_array($acept['cotizacion']['emisora']), 'Datos empresa emisora en cotización');

$htmlPdf = \Crm\CotizacionPdf::html($acept['cotizacion']);
$pdf = \Crm\CotizacionPdf::generar($acept['cotizacion']);
assert_true(strpos($pdf, '%PDF') === 0, 'PDF de cotización válido');
assert_true(strpos($htmlPdf, '76.987.654-5') !== false, 'PDF incluye RUT de empresa emisora');
assert_true(strpos($htmlPdf, 'LPAEZsis') !== false, 'PDF incluye razón social emisora');
assert_true(strpos($htmlPdf, 'Luis') !== false, 'PDF incluye nombre del vendedor');
assert_true(strpos($htmlPdf, 'MARCAS REPRESENTADAS') !== false, 'PDF incluye sección de marcas');
assert_true(strpos($htmlPdf, 'banner_marcas.png') === false, 'PDF no usa banner fijo de marcas');
assert_true(strpos($htmlPdf, 'sonic.png') !== false, 'PDF incluye logo de marca desde disco');
assert_true(strpos($htmlPdf, 'Ana Rivas') !== false, 'PDF incluye nombre del contacto');
assert_true(strpos($htmlPdf, 'ana.rivas@packingdemo.cl') !== false, 'PDF incluye email del contacto');
assert_true(strpos($htmlPdf, '+56 9 5555 1212') !== false, 'PDF incluye teléfono del contacto');
assert_true(strpos($htmlPdf, '>Nombre<') !== false || strpos($htmlPdf, 'Nombre') !== false, 'PDF etiqueta Nombre del contacto');
assert_true(strpos($htmlPdf, 'Teléfono') !== false, 'PDF etiqueta Teléfono del contacto');
assert_true(strpos($htmlPdf, 'Banco Estado') !== false, 'PDF incluye datos bancarios');
assert_true(strpos($htmlPdf, '35171442603') !== false, 'PDF incluye número de cuenta vista');
$posPago = strpos($htmlPdf, 'Forma de pago');
$posBanco = strpos($htmlPdf, 'Banco Estado');
assert_true($posPago !== false && $posBanco !== false && $posPago < $posBanco, 'Condiciones comerciales van antes de datos bancarios');
assert_true(strpos($htmlPdf, 'logo.png') !== false || strpos($htmlPdf, 'logo.svg') !== false, 'PDF referencia logo corporativo');

$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
$tmpPng = sys_get_temp_dir() . '/crm-logo-test.png';
file_put_contents($tmpPng, $png);
$logoRel = \Crm\ConfiguracionEmpresa::guardarArchivoLogo(array(
    'tmp_name' => $tmpPng,
    'size' => strlen($png),
    'error' => 0,
    'name' => 'logo.png',
    'type' => 'image/png',
), false);
assert_true($logoRel === 'uploads/logo.png', 'Logo se guarda en uploads/logo.png');
assert_true(is_file(dirname(__DIR__) . '/uploads/logo.png'), 'Archivo logo.png existe');

$gifRejected = false;
$tmpGif = sys_get_temp_dir() . '/crm-logo-test.gif';
file_put_contents($tmpGif, 'GIF89a');
try {
    \Crm\ConfiguracionEmpresa::guardarArchivoLogo(array(
        'tmp_name' => $tmpGif,
        'size' => 6,
        'error' => 0,
        'name' => 'x.gif',
        'type' => 'image/gif',
    ), false);
} catch (\Crm\ApiException $e) {
    $gifRejected = strpos($e->getMessage(), 'PNG') !== false || strpos($e->getMessage(), 'imagen') !== false;
}
assert_true($gifRejected, 'Logo GIF/no PNG-JPG rechazado');

\Crm\Cotizaciones::update($aceptId, array(
    'empresa_id' => $empId,
    'estado' => 'rechazada',
    'items' => array(
        array('producto_id' => (int) $prod['id'], 'cantidad' => 2),
    ),
), $login);
$comRow->execute(array($aceptId));
$comision2 = $comRow->fetch(PDO::FETCH_ASSOC);
assert_true(is_array($comision2) && (string) $comision2['estado'] === 'anulada', 'Rechazo anula comisión pendiente');

$serv = \Crm\Cotizaciones::store(array(
    'empresa_id' => $empId,
    'estado' => 'borrador',
    'items' => array(
        array(
            'tipo_item' => 'servicio',
            'descripcion' => 'Instalación y puesta en marcha',
            'cantidad' => 1,
            'precio_unitario' => 150000,
        ),
        array(
            'tipo_item' => 'producto',
            'producto_id' => (int) $prod['id'],
            'cantidad' => 1,
        ),
    ),
), $login);
$servItems = $serv['cotizacion']['items'];
assert_true(count($servItems) === 2, 'Cotización mixta producto + servicio');
assert_true((string) $servItems[0]['tipo_item'] === 'servicio', 'Primer ítem es servicio');
assert_true($servItems[0]['producto_id'] === null || $servItems[0]['producto_id'] === '', 'Servicio deja producto_id NULL');
assert_true((string) $servItems[0]['codigo'] === 'SERV', 'Código por defecto SERV');
assert_true((string) $servItems[1]['tipo_item'] === 'producto', 'Segundo ítem es producto');
assert_true((int) $servItems[1]['producto_id'] === (int) $prod['id'], 'Producto mantiene FK de inventario');

$servFail = false;
try {
    \Crm\Cotizaciones::store(array(
        'empresa_id' => $empId,
        'items' => array(
            array('tipo_item' => 'servicio', 'cantidad' => 1, 'precio_unitario' => 10),
        ),
    ), $login);
} catch (\Crm\ApiException $e) {
    $servFail = strpos($e->getMessage(), 'descripción') !== false;
}
assert_true($servFail, 'Servicio sin descripción se rechaza');

$pdfServ = \Crm\CotizacionPdf::generar($serv['cotizacion']);
$htmlServ = \Crm\CotizacionPdf::html($serv['cotizacion']);
assert_true(strpos($pdfServ, '%PDF') === 0, 'PDF de servicio válido');
assert_true(strpos($htmlServ, '[Servicio]') !== false, 'PDF marca ítems de servicio');

$com = \Crm\Cotizaciones::store(array(
    'empresa_id' => $empId,
    'estado' => 'enviada',
    'moneda' => 'USD',
    'validez_oferta' => '30 días',
    'condiciones_pago' => '50% anticipo, 50% contra entrega',
    'plazo_entrega' => '15 días hábiles',
    'lugar_entrega' => 'Planta cliente, Talca',
    'items' => array(
        array(
            'producto_id' => (int) $prod['id'],
            'cantidad' => 1,
        ),
    ),
), $login);
$ccom = $com['cotizacion'];
assert_true((string) $ccom['moneda'] === 'USD', 'Moneda USD persistida');
assert_true((string) $ccom['validez_oferta'] === '30 días', 'Validez de oferta persistida');
assert_true(strpos((string) $ccom['condiciones_pago'], 'anticipo') !== false, 'Condiciones de pago persistidas');
assert_true((string) $ccom['plazo_entrega'] === '15 días hábiles', 'Plazo de entrega persistido');
assert_true(strpos((string) $ccom['lugar_entrega'], 'Talca') !== false, 'Lugar de entrega persistido');
$pdfCom = \Crm\CotizacionPdf::generar($ccom);
$htmlCom = \Crm\CotizacionPdf::html($ccom);
assert_true(strpos($pdfCom, '%PDF') === 0, 'PDF comercial válido');
assert_true(strpos($htmlCom, 'USD') !== false, 'PDF incluye moneda');
assert_true(strpos($htmlCom, 'Validez') !== false || strpos($htmlCom, 'validez') !== false, 'PDF incluye validez de oferta');

$monedaFail = false;
try {
    \Crm\Cotizaciones::store(array(
        'empresa_id' => $empId,
        'moneda' => 'XXX',
        'items' => array(
            array('producto_id' => (int) $prod['id'], 'cantidad' => 1),
        ),
    ), $login);
} catch (\Crm\ApiException $e) {
    $monedaFail = strpos($e->getMessage(), 'Moneda') !== false;
}
assert_true($monedaFail, 'Moneda inválida se rechaza');

$def = \Crm\Cotizaciones::store(array(
    'empresa_id' => $empId,
    'items' => array(
        array('producto_id' => (int) $prod['id'], 'cantidad' => 1),
    ),
), $login);
assert_true((string) $def['cotizacion']['moneda'] === 'CLP', 'Moneda por defecto CLP');

$beforeCom = (int) $pdo->query('SELECT COUNT(*) FROM crm_comisiones')->fetchColumn();
$pdo->beginTransaction();
\Crm\Comisiones::registrarDesdeCotizacion($pdo, $cotId, (int) $adminVend['id'], 9999.00);
$pdo->rollBack();
$afterCom = (int) $pdo->query('SELECT COUNT(*) FROM crm_comisiones')->fetchColumn();
assert_true($beforeCom === $afterCom, 'Rollback de comisión en la misma transacción');

foreach (array('prospecto', 'negociacion', 'ganada', 'perdida') as $etReporte) {
    $oppRep = \Crm\Oportunidades::store(array(
        'empresa_id' => $empId,
        'titulo' => 'Pipeline ' . $etReporte,
        'etapa' => $etReporte,
        'valor_estimado' => 250000,
        'origen_canal' => 'web',
    ), $login);
    assert_true((string) $oppRep['oportunidad']['etapa'] === $etReporte, 'Oportunidad etapa ' . $etReporte);
}

$filtroMes = array(
    'desde' => date('Y-m-01'),
    'hasta' => date('Y-m-d'),
);

$kpis = \Crm\Reportes::obtener('resumen_kpis', $filtroMes);
$kpisJson = json_encode($kpis);
assert_true(is_string($kpisJson) && json_last_error() === JSON_ERROR_NONE, 'JSON válido resumen_kpis');
$kpisDec = json_decode($kpisJson, true);
assert_true(is_array($kpisDec) && isset($kpisDec['kpis']['monto_cotizado']), 'KPI monto_cotizado');
assert_true(isset($kpisDec['kpis']['ventas_ganadas'], $kpisDec['kpis']['conversion_pct'], $kpisDec['kpis']['comisiones']), 'KPIs ventas, conversión y comisiones');
assert_true((float) $kpisDec['kpis']['monto_cotizado'] > 0, 'Monto cotizado del mes > 0');
assert_true((float) $kpisDec['kpis']['conversion_pct'] >= 0 && (float) $kpisDec['kpis']['conversion_pct'] <= 100, 'Conversión entre 0 y 100');

$pipe = \Crm\Reportes::obtener('pipeline', $filtroMes);
$pipeJson = json_encode($pipe);
assert_true(is_string($pipeJson) && json_last_error() === JSON_ERROR_NONE, 'JSON válido pipeline');
$pipeDec = json_decode($pipeJson, true);
assert_true(is_array($pipeDec) && isset($pipeDec['etapas']) && count($pipeDec['etapas']) === 5, 'Pipeline tiene 5 etapas');
$etapasKeys = array();
foreach ($pipeDec['etapas'] as $et) {
    $etapasKeys[] = (string) $et['etapa'];
    assert_true(isset($et['label'], $et['cantidad'], $et['monto']), 'Etapa pipeline con cantidad y monto');
}
assert_true($etapasKeys === array('lead', 'cotizacion', 'negociacion', 'ganado', 'perdido'), 'Orden de etapas del pipeline');
assert_true((int) $pipeDec['etapas'][0]['cantidad'] >= 1, 'Pipeline lead con oportunidades');
assert_true((int) $pipeDec['etapas'][1]['cantidad'] >= 1, 'Pipeline cotización con oportunidades');

$rank = \Crm\Reportes::obtener('vendedores', $filtroMes);
$rankJson = json_encode($rank);
assert_true(is_string($rankJson) && json_last_error() === JSON_ERROR_NONE, 'JSON válido vendedores');
$rankDec = json_decode($rankJson, true);
assert_true(is_array($rankDec) && isset($rankDec['vendedores']) && count($rankDec['vendedores']) >= 2, 'Ranking de vendedores');
$rank0 = $rankDec['vendedores'][0];
assert_true(isset($rank0['total_cotizado'], $rank0['total_cerrado'], $rank0['tasa_cierre_pct'], $rank0['comisiones']), 'Ranking con cotizado, cerrado, tasa y comisión');

$top = \Crm\Reportes::obtener('productos_top', $filtroMes);
$topJson = json_encode($top);
assert_true(is_string($topJson) && json_last_error() === JSON_ERROR_NONE, 'JSON válido productos_top');
$topDec = json_decode($topJson, true);
assert_true(is_array($topDec) && isset($topDec['items']), 'Top productos/servicios');
assert_true(count($topDec['items']) <= 10, 'Top 10 como máximo');
assert_true(count($topDec['items']) >= 1, 'Al menos un ítem cotizado en el top');
assert_true(isset($topDec['proporcion']['producto'], $topDec['proporcion']['servicio']), 'Proporción producto vs servicio');

$filtroVend = $filtroMes;
$filtroVend['vendedor_id'] = (int) $adminVend['id'];
$kpisVend = \Crm\Reportes::obtener('resumen_kpis', $filtroVend);
assert_true((int) $kpisVend['filtros']['vendedor_id'] === (int) $adminVend['id'], 'Filtro vendedor_id en KPIs');
$rankVend = \Crm\Reportes::obtener('vendedores', $filtroVend);
assert_true(count($rankVend['vendedores']) === 1, 'Ranking filtrado a un vendedor');

$tipoInvalido = false;
try {
    \Crm\Reportes::obtener('no_existe', $filtroMes);
} catch (\Crm\ApiException $e) {
    $tipoInvalido = strpos($e->getMessage(), 'Tipo') !== false;
}
assert_true($tipoInvalido, 'Tipo de reporte inválido se rechaza');

$ayer = date('Y-m-d', time() - 86400) . ' 09:00:00';
$seg = \Crm\Actividades::crear(array(
    'titulo' => 'Llamada postventa secador',
    'tipo' => 'llamada',
    'fecha_programada' => $ayer,
    'empresa_id' => $empId,
    'cotizacion_id' => $cotId,
    'vendedor_id' => (int) $adminVend['id'],
    'descripcion' => 'Confirmar instalación',
), $login);
$segAct = $seg['actividad'];
assert_true((int) $segAct['id'] > 0, 'Crear actividad de seguimiento');
assert_true((string) $segAct['tipo'] === 'llamada', 'Tipo llamada');
assert_true((string) $segAct['estado'] === 'pendiente', 'Estado inicial pendiente');
assert_true((int) $segAct['vendedor_id'] === (int) $adminVend['id'], 'Actividad asignada al vendedor');
assert_true((int) $segAct['cotizacion_id'] === $cotId, 'Actividad vinculada a cotización');
assert_true(!empty($segAct['creado_en']), 'Campo creado_en poblado');
assert_true(!empty($segAct['vencida']), 'Actividad de ayer queda vencida');

$correoAct = \Crm\Actividades::crear(array(
    'titulo' => 'Correo de seguimiento',
    'tipo' => 'correo',
    'vendedor_id' => (int) $adminVend['id'],
    'empresa_id' => $empId,
), $login);
assert_true((string) $correoAct['actividad']['tipo'] === 'correo', 'Tipo correo');
assert_true((string) $correoAct['actividad']['canal'] === 'email', 'Canal email para correo');

$hoyAct = \Crm\Actividades::crear(array(
    'titulo' => 'Reunión de cierre',
    'tipo' => 'reunion',
    'fecha_programada' => date('Y-m-d') . ' 16:00:00',
    'vendedor_id' => (int) $adminVend['id'],
), $login);
assert_true(!empty($hoyAct['actividad']['es_hoy']), 'Reunión de hoy marcada es_hoy');

$done = \Crm\Actividades::completar((int) $segAct['id'], array('resultado' => 'Cliente confirma'), $login);
assert_true((string) $done['actividad']['estado'] === 'realizada', 'Completar pasa estado a realizada');
assert_true(!empty($done['actividad']['fecha_completada']), 'Fecha de completado al realizar');

$listReal = \Crm\Actividades::index(array(
    'vendedor_id' => (int) $adminVend['id'],
    'estado' => 'realizada',
));
$foundReal = false;
foreach ($listReal['actividades'] as $aRow) {
    if ((int) $aRow['id'] === (int) $segAct['id']) {
        $foundReal = true;
        break;
    }
}
assert_true($foundReal, 'GET filtra actividades realizadas por vendedor');
assert_true(isset($listReal['resumen']['realizadas']), 'Resumen de agenda en el listado');

$listRango = \Crm\Actividades::index(array(
    'desde' => date('Y-m-d', time() - 86400),
    'hasta' => date('Y-m-d'),
    'estado' => 'pendiente',
));
assert_true(isset($listRango['actividades']), 'GET con rango de fechas');

$listPendKpi = \Crm\Actividades::index(array(
    'vendedor_id' => (int) $adminVend['id'],
    'estado' => 'pendiente',
));
assert_true((int) $listPendKpi['resumen']['realizadas'] >= 1, 'KPI realizadas no se anula al filtrar pendientes');

$marcasSeed = \Crm\Marcas::index();
assert_true(count($marcasSeed['marcas']) >= 10, 'Seed de marcas representadas');
$marcaIdsGlobales = array();
foreach ($marcasSeed['marcas'] as $mRow) {
    if ((int) $mRow['incluir_global'] === 1 && (int) $mRow['activa'] === 1) {
        $marcaIdsGlobales[] = (int) $mRow['id'];
    }
}
assert_true(count($marcaIdsGlobales) >= 1, 'Hay marcas globales para el PDF');
assert_true((int) $acept['cotizacion']['contacto_id'] === (int) $cto['contacto']['id'], 'Cotización guarda contacto_id');
assert_true(is_array($acept['cotizacion']['marca_ids']), 'Cotización expone marca_ids');
assert_true(count($acept['cotizacion']['marcas']) >= 1, 'Cotización resuelve marcas del PDF');

$pngMarca = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
$tmpMarca = sys_get_temp_dir() . '/crm-marca-test.png';
file_put_contents($tmpMarca, $pngMarca);
$marcaNueva = \Crm\Marcas::store(array(
    'nombre' => 'Marca Test Local',
    'activa' => 1,
    'incluir_global' => 0,
    'orden' => 999,
), array(
    'tmp_name' => $tmpMarca,
    'size' => strlen($pngMarca),
    'error' => 0,
    'name' => 'test.png',
    'type' => 'image/png',
));
$marcaNuevaId = (int) $marcaNueva['marca']['id'];
assert_true($marcaNuevaId > 0, 'Alta de marca con logo');
assert_true(strpos((string) $marcaNueva['marca']['archivo'], 'marca_upl_') === 0, 'Archivo de marca subida con prefijo propio');
assert_true(is_file(dirname(__DIR__) . '/assets/img/marcas/' . $marcaNueva['marca']['archivo']), 'Logo de marca existe en disco');

$cotMarca = \Crm\Cotizaciones::store(array(
    'empresa_id' => $empId,
    'contacto_id' => (int) $cto['contacto']['id'],
    'estado' => 'borrador',
    'marca_ids' => array($marcaNuevaId),
    'items' => array(
        array('producto_id' => (int) $prod['id'], 'cantidad' => 1),
    ),
), $login);
assert_true($cotMarca['cotizacion']['marca_ids'] === array($marcaNuevaId), 'Selección de marcas persistida en cotización');
$htmlMarcaSel = \Crm\CotizacionPdf::html($cotMarca['cotizacion']);
assert_true(strpos($htmlMarcaSel, $marcaNueva['marca']['archivo']) !== false, 'PDF usa logo de marca seleccionada');
assert_true(strpos($htmlMarcaSel, 'banner_marcas.png') === false, 'PDF seleccionado no usa banner fijo');

$empUpd = \Crm\Empresas::update($empId, array(
    'rut' => '76.123.456-0',
    'razon_social' => 'Packing Demo SpA',
    'direccion' => 'Camino Industrial 450',
    'industria' => 'Agroindustria',
    'region' => 'Maule',
    'origen' => 'whatsapp',
    'estado' => 'activa',
), $login);
assert_true((string) $empUpd['empresa']['direccion'] === 'Camino Industrial 450', 'Editar empresa persiste dirección');
assert_true((string) $empUpd['empresa']['estado'] === 'activa', 'Editar empresa persiste estado');

$ctoUpd = \Crm\Contactos::update((int) $cto['contacto']['id'], array(
    'empresa_id' => $empId,
    'nombre' => 'Ana',
    'apellido' => 'Rivas',
    'cargo' => 'Jefa de Compras',
    'email' => 'ana.rivas@packingdemo.cl',
    'telefono' => '+56 9 5555 9999',
    'canal_preferido' => 'email',
));
assert_true((string) $ctoUpd['contacto']['telefono'] === '+56 9 5555 9999', 'Editar contacto persiste teléfono');
assert_true((string) $ctoUpd['contacto']['cargo'] === 'Jefa de Compras', 'Editar contacto persiste cargo');

$delCtoBlocked = false;
try {
    \Crm\Contactos::destroy((int) $cto['contacto']['id']);
} catch (\Crm\ApiException $e) {
    $delCtoBlocked = $e->status === 409 || strpos($e->getMessage(), 'cotizaciones') !== false;
}
assert_true($delCtoBlocked, 'No se elimina contacto con cotizaciones');

$delEmpBlocked = false;
try {
    \Crm\Empresas::destroy($empId);
} catch (\Crm\ApiException $e) {
    $delEmpBlocked = $e->status === 409 || strpos($e->getMessage(), 'cotizaciones') !== false;
}
assert_true($delEmpBlocked, 'No se elimina empresa con cotizaciones');

$empLibre = \Crm\Empresas::store(array(
    'rut' => '77.888.111-K',
    'razon_social' => 'Empresa Sin Cotizaciones Ltda.',
    'origen' => 'web',
    'estado' => 'prospecto',
), $login);
$ctoLibre = \Crm\Contactos::store(array(
    'empresa_id' => (int) $empLibre['empresa']['id'],
    'nombre' => 'Pedro',
    'apellido' => 'Libre',
    'telefono' => '+56 9 1111 2222',
    'canal_preferido' => 'telefono',
));
$delCtoOk = \Crm\Contactos::destroy((int) $ctoLibre['contacto']['id']);
assert_true(!empty($delCtoOk['deleted']), 'Se elimina contacto sin cotizaciones');
$delEmpOk = \Crm\Empresas::destroy((int) $empLibre['empresa']['id']);
assert_true(!empty($delEmpOk['deleted']), 'Se elimina empresa sin cotizaciones');

$delCot = \Crm\Cotizaciones::destroy((int) $cotMarca['cotizacion']['id']);
assert_true(!empty($delCot['deleted']), 'Se elimina cotización');
$gone = $pdo->prepare('SELECT id FROM crm_cotizaciones WHERE id = ?');
$gone->execute(array((int) $cotMarca['cotizacion']['id']));
assert_true($gone->fetch() === false, 'Cotización eliminada de la base');
$pivotGone = (int) $pdo->query(
    'SELECT COUNT(*) FROM crm_cotizacion_marcas WHERE cotizacion_id = ' . (int) $cotMarca['cotizacion']['id']
)->fetchColumn();
assert_true($pivotGone === 0, 'Pivote de marcas de cotización eliminado');

$archivoMarca = (string) $marcaNueva['marca']['archivo'];
$delMarca = \Crm\Marcas::destroy($marcaNuevaId);
assert_true(!empty($delMarca['deleted']), 'Se elimina marca');
assert_true(!is_file(dirname(__DIR__) . '/assets/img/marcas/' . $archivoMarca), 'Archivo de marca subida se borra del disco');

$marcaCat = \Crm\Marcas::index();
$marcaSeedId = (int) $marcaCat['marcas'][0]['id'];
$marcaSeedNom = (string) $marcaCat['marcas'][0]['nombre'];
$pedido1 = \Crm\Cotizaciones::store(array(
    'empresa_id' => $empId,
    'estado' => 'aceptada',
    'items' => array(
        array(
            'tipo_item' => 'a_pedido',
            'descripcion' => 'Soplador X200 a pedido',
            'marca_id' => $marcaSeedId,
            'cantidad' => 2,
            'precio_unitario' => 100000,
            'costo_unitario' => 60000,
        ),
    ),
), $login);
$itPed = $pedido1['cotizacion']['items'][0];
assert_true((string) $itPed['tipo_item'] === 'a_pedido', 'Ítem tipo a_pedido');
assert_true((int) $itPed['es_a_pedido'] === 1, 'Flag es_a_pedido = 1');
assert_true($itPed['producto_id'] === null || $itPed['producto_id'] === '', 'A pedido deja producto_id NULL');
assert_true((int) $itPed['marca_id'] === $marcaSeedId, 'A pedido guarda marca_id de catálogo');
assert_true((string) $itPed['marca_nombre'] === $marcaSeedNom, 'A pedido copia nombre de marca');
assert_true(abs((float) $itPed['costo_unitario'] - 60000) < 0.001, 'Costo unitario persistido');
$htmlPed = \Crm\CotizacionPdf::html($pedido1['cotizacion']);
assert_true(strpos($htmlPed, '[A pedido]') !== false, 'PDF marca ítem a pedido');
assert_true(strpos($htmlPed, $marcaSeedNom) !== false, 'PDF incluye marca del ítem a pedido');

$pedidoLibre = \Crm\Cotizaciones::store(array(
    'empresa_id' => $empId,
    'estado' => 'enviada',
    'items' => array(
        array(
            'tipo_item' => 'a_pedido',
            'descripcion' => 'Filtro especial no catálogo',
            'marca_nombre' => 'Marca Libre XYZ',
            'cantidad' => 1,
            'precio_unitario' => 50000,
            'costo_unitario' => 0,
        ),
    ),
), $login);
assert_true($pedidoLibre['cotizacion']['items'][0]['marca_id'] === null || $pedidoLibre['cotizacion']['items'][0]['marca_id'] === '', 'Marca texto libre sin marca_id');
assert_true((string) $pedidoLibre['cotizacion']['items'][0]['marca_nombre'] === 'Marca Libre XYZ', 'Marca texto libre persistida');

\Crm\Cotizaciones::store(array(
    'empresa_id' => $empId,
    'estado' => 'borrador',
    'items' => array(
        array(
            'tipo_item' => 'a_pedido',
            'descripcion' => 'Soplador X200 a pedido',
            'marca_id' => $marcaSeedId,
            'cantidad' => 1,
            'precio_unitario' => 100000,
            'costo_unitario' => 60000,
        ),
    ),
), $login);

$pedidoFail = false;
try {
    \Crm\Cotizaciones::store(array(
        'empresa_id' => $empId,
        'items' => array(
            array('tipo_item' => 'a_pedido', 'cantidad' => 1, 'precio_unitario' => 10),
        ),
    ), $login);
} catch (\Crm\ApiException $e) {
    $pedidoFail = strpos($e->getMessage(), 'descripción') !== false;
}
assert_true($pedidoFail, 'A pedido sin descripción se rechaza');

$statsPed = \Crm\EstadisticasAPedido::obtener(array('periodo' => 'anio'));
assert_true((int) $statsPed['kpis']['n_cotizados'] >= 3, 'KPI ítems a pedido cotizados');
assert_true((int) $statsPed['kpis']['n_ganados'] >= 1, 'KPI ítems a pedido ganados');
assert_true((float) $statsPed['kpis']['monto_ganado'] > 0, 'Monto ganado a pedido > 0');
assert_true($statsPed['kpis']['margen_pct'] !== null && (float) $statsPed['kpis']['margen_pct'] > 0, 'Margen promedio con costo informado');
assert_true(count($statsPed['por_marca']) >= 1, 'Análisis por marca');
$libreMarca = false;
foreach ($statsPed['por_marca'] as $pm) {
    if ((string) $pm['marca'] === 'Marca Libre XYZ' && (int) $pm['en_catalogo'] === 0) {
        $libreMarca = true;
    }
}
assert_true($libreMarca, 'Marca fuera de catálogo identificada');
assert_true(count($statsPed['sugerencias']) >= 1, 'Sugerencia de alta por recurrencia');

$stockAntes = (float) $pdo->query("SELECT stock FROM productos WHERE codigo = '13451' LIMIT 1")->fetchColumn();
$alta = \Crm\Productos::altaCatalogo(array(
    'nombre' => 'Soplador X200 a pedido',
    'descripcion' => 'Soplador X200 a pedido',
    'marca_nombre' => $marcaSeedNom,
    'precio_unitario' => 100000,
));
assert_true(strpos((string) $alta['producto']['codigo'], 'APD-') === 0, 'Código APD generado en alta a catálogo');
assert_true((float) $alta['producto']['stock'] === 0.0, 'Alta a catálogo con stock 0');
$stockDespues = (float) $pdo->query("SELECT stock FROM productos WHERE codigo = '13451' LIMIT 1")->fetchColumn();
assert_true($stockAntes === $stockDespues, 'Alta a catálogo no altera stock existente');
$dupAlta = false;
try {
    \Crm\Productos::altaCatalogo(array(
        'nombre' => 'Otro',
        'codigo' => $alta['producto']['codigo'],
        'precio_unitario' => 1,
    ));
} catch (\Crm\ApiException $e) {
    $dupAlta = strpos($e->getMessage(), 'Ya existe') !== false;
}
assert_true($dupAlta, 'Alta duplicada por código se rechaza');

echo "\n$passed passed, $failed failed\n";
exit($failed > 0 ? 1 : 0);
