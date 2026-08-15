<?php

declare(strict_types=1);

/**
 * Suite de integración PHP 7.4 (SQLite en archivo temporal).
 */
$root = dirname(__DIR__);
$tmp = $root . '/data/test-crm.sqlite';
if (is_file($tmp)) {
    unlink($tmp);
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

echo "\n$passed passed, $failed failed\n";
exit($failed > 0 ? 1 : 0);
