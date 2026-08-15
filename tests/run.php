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
    'canal_preferido' => 'email',
    'es_principal' => 1,
));
assert_true((int) $cto['contacto']['id'] > 0, 'Alta de contacto');

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

$pdf = \Crm\CotizacionPdf::generar($acept['cotizacion']);
assert_true(strpos($pdf, '%PDF-1.4') === 0, 'PDF de cotización válido');
assert_true(strpos($pdf, '76.987.654-5') !== false, 'PDF incluye RUT de empresa emisora');
assert_true(strpos($pdf, 'LPAEZsis') !== false, 'PDF incluye razón social emisora');
assert_true(strpos($pdf, 'Luis') !== false, 'PDF incluye nombre del vendedor');

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
assert_true(strpos($pdfServ, '[Servicio]') !== false, 'PDF marca ítems de servicio');

$beforeCom = (int) $pdo->query('SELECT COUNT(*) FROM crm_comisiones')->fetchColumn();
$pdo->beginTransaction();
\Crm\Comisiones::registrarDesdeCotizacion($pdo, $cotId, (int) $adminVend['id'], 9999.00);
$pdo->rollBack();
$afterCom = (int) $pdo->query('SELECT COUNT(*) FROM crm_comisiones')->fetchColumn();
assert_true($beforeCom === $afterCom, 'Rollback de comisión en la misma transacción');

echo "\n$passed passed, $failed failed\n";
exit($failed > 0 ? 1 : 0);
