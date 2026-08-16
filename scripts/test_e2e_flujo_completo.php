#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Prueba E2E del flujo comercial completo sobre la BD local (.env).
 *
 *   php scripts/test_e2e_flujo_completo.php
 */

$root = dirname(__DIR__);
require $root . '/includes/bootstrap.php';

$failed = 0;
$passed = 0;
$pasoActual = 0;

/**
 * @param string $paso
 * @param string $detalle
 * @return void
 */
function e2e_ok($paso, $detalle)
{
    global $passed;
    $passed++;
    echo 'PASO ' . $paso . ': OK';
    if ($detalle !== '') {
        echo ' — ' . $detalle;
    }
    echo PHP_EOL;
}

/**
 * @param string $paso
 * @param string $detalle
 * @return void
 */
function e2e_fail($paso, $detalle)
{
    global $failed;
    $failed++;
    echo 'PASO ' . $paso . ': FAIL — ' . $detalle . PHP_EOL;
    echo PHP_EOL . 'RESULTADO: FAIL' . PHP_EOL;
    exit(1);
}

/**
 * @param mixed $a
 * @param mixed $b
 * @param float $eps
 * @return bool
 */
function e2e_money_eq($a, $b, $eps = 0.009)
{
    return abs((float) $a - (float) $b) < $eps;
}

/**
 * GET/POST JSON contra el servidor local (misma cookie de sesión).
 *
 * @param string $url
 * @param string $method
 * @param array|null $body
 * @param string $cookieFile
 * @return array
 */
function e2e_http_json($url, $method, $body, $cookieFile)
{
    if (!function_exists('curl_init')) {
        return array('ok' => false, 'error' => 'extensión curl no disponible', '_http' => 0);
    }
    $ch = curl_init($url);
    $headers = array('Accept: application/json');
    $opts = array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_COOKIEJAR => $cookieFile,
        CURLOPT_COOKIEFILE => $cookieFile,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_CUSTOMREQUEST => strtoupper((string) $method),
    );
    if (is_array($body)) {
        $headers[] = 'Content-Type: application/json';
        $opts[CURLOPT_POSTFIELDS] = json_encode($body, JSON_UNESCAPED_UNICODE);
    }
    $opts[CURLOPT_HTTPHEADER] = $headers;
    curl_setopt_array($ch, $opts);
    $raw = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if (!is_string($raw) || $raw === '') {
        return array('ok' => false, 'error' => $err !== '' ? $err : 'respuesta vacía', '_http' => $code);
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        return array('ok' => false, 'error' => 'JSON inválido', '_http' => $code, '_raw' => substr($raw, 0, 200));
    }
    $data['_http'] = $code;
    return $data;
}

echo '=== E2E flujo comercial completo ===' . PHP_EOL;
echo 'PHP ' . PHP_VERSION . ' | driver=' . crm_pdo_driver() . PHP_EOL;
echo PHP_EOL;

$pdo = crm_pdo();
$user = null;

try {
    $user = \Crm\Auth::login('ivan.p@example.net', 'Lpaezsis.2026');
} catch (\Crm\ApiException $e) {
    e2e_fail('0', 'No se pudo iniciar sesión admin: ' . $e->getMessage());
}

/* ------------------------------------------------------------------ */
/* PASO 1 — Empresa y contacto                                        */
/* ------------------------------------------------------------------ */
$pasoActual = 1;
$rutPedido = '77.888.999-K';
$rutUsar = $rutPedido;
$notaRut = '';
if (!\Crm\Rut::isValid($rutPedido)) {
    /* 77.888.999-K no es módulo 11; el DV correcto es 4 y ya existe en el seed. */
    $rutUsar = '77.888.013-K';
    $notaRut = 'RUT pedido ' . $rutPedido . ' inválido (DV módulo 11 = 4, ocupado por seed); se usa ' . $rutUsar;
}

try {
    $empId = 0;
    $fmt = \Crm\Rut::format($rutUsar);
    $findEmp = $pdo->prepare('SELECT id, rut, razon_social FROM crm_empresas WHERE rut = ? OR razon_social = ? LIMIT 1');
    $findEmp->execute(array($fmt, 'Industrial Test SpA'));
    $empRow = $findEmp->fetch(PDO::FETCH_ASSOC);
    if (is_array($empRow)) {
        $empId = (int) $empRow['id'];
        $fmt = (string) $empRow['rut'];
    } else {
        $created = \Crm\Empresas::store(array(
            'rut' => $rutUsar,
            'razon_social' => 'Industrial Test SpA',
            'nombre_fantasia' => 'Industrial Test',
            'industria' => 'Agroindustria',
            'origen' => 'web',
            'estado' => 'prospecto',
            'email' => 'contacto@industrialtest.cl',
            'notas' => 'E2E flujo comercial completo',
        ), $user);
        $empId = (int) $created['empresa']['id'];
        $fmt = (string) $created['empresa']['rut'];
    }
    if ($empId <= 0) {
        e2e_fail('1', 'No se obtuvo id de empresa');
    }

    $ctoId = 0;
    $findCto = $pdo->prepare(
        'SELECT id FROM crm_contactos WHERE empresa_id = ? AND email = ? LIMIT 1'
    );
    $findCto->execute(array($empId, 'cperez@industrialtest.cl'));
    $ctoRow = $findCto->fetch(PDO::FETCH_ASSOC);
    if (is_array($ctoRow)) {
        $ctoId = (int) $ctoRow['id'];
    } else {
        $cto = \Crm\Contactos::store(array(
            'empresa_id' => $empId,
            'nombre' => 'Carlos',
            'apellido' => 'Pérez',
            'cargo' => 'Gerente de Planta',
            'email' => 'cperez@industrialtest.cl',
            'canal_preferido' => 'email',
            'es_principal' => 1,
        ));
        $ctoId = (int) $cto['contacto']['id'];
    }
    if ($ctoId <= 0) {
        e2e_fail('1', 'No se obtuvo id de contacto');
    }

    $det1 = 'empresa id=' . $empId . ' Industrial Test SpA RUT ' . $fmt
        . ' · contacto id=' . $ctoId . ' Carlos Pérez (Gerente de Planta)';
    if ($notaRut !== '') {
        $det1 .= ' · ' . $notaRut;
    }
    e2e_ok('1', $det1);
} catch (\Crm\ApiException $e) {
    e2e_fail('1', $e->getMessage());
} catch (Exception $e) {
    e2e_fail('1', $e->getMessage());
}

/* ------------------------------------------------------------------ */
/* PASO 2 — Vendedor Luis Páez al 5.00%                               */
/* ------------------------------------------------------------------ */
$pasoActual = 2;
try {
    $findVend = $pdo->prepare(
        "SELECT * FROM crm_vendedores
         WHERE nombre_completo LIKE ? OR email = ?
         ORDER BY id ASC LIMIT 1"
    );
    $findVend->execute(array('%Luis%Páez%', 'ivan.p@example.net'));
    $vend = $findVend->fetch(PDO::FETCH_ASSOC);
    if (!is_array($vend)) {
        $saved = \Crm\Vendedores::guardar(array(
            'usuario_id' => (int) $user['id'],
            'nombre_completo' => 'Luis Páez',
            'email' => 'ivan.p@example.net',
            'comision_porcentaje' => 5.00,
            'activo' => 1,
        ), 0);
        $vend = $saved['vendedor'];
    } else {
        $saved = \Crm\Vendedores::guardar(array(
            'usuario_id' => isset($vend['usuario_id']) ? $vend['usuario_id'] : (int) $user['id'],
            'nombre_completo' => 'Luis Páez',
            'email' => (string) $vend['email'],
            'telefono' => isset($vend['telefono']) ? $vend['telefono'] : '',
            'comision_porcentaje' => 5.00,
            'activo' => 1,
        ), (int) $vend['id']);
        $vend = $saved['vendedor'];
    }
    $vendId = (int) $vend['id'];
    $pct = round((float) $vend['comision_porcentaje'], 2);
    if ((int) $vend['activo'] !== 1) {
        e2e_fail('2', 'Vendedor no quedó activo');
    }
    if (!e2e_money_eq($pct, 5.0)) {
        e2e_fail('2', 'Comisión esperada 5.00%, obtenida ' . number_format($pct, 2, '.', ''));
    }
    e2e_ok('2', 'vendedor id=' . $vendId . ' Luis Páez activo, comisión ' . number_format($pct, 2, '.', '') . '%');
} catch (\Crm\ApiException $e) {
    e2e_fail('2', $e->getMessage());
} catch (Exception $e) {
    e2e_fail('2', $e->getMessage());
}

/* ------------------------------------------------------------------ */
/* PASO 3 — Oportunidad en pipeline (lead = prospecto)                */
/* ------------------------------------------------------------------ */
$pasoActual = 3;
try {
    $opp = \Crm\Oportunidades::store(array(
        'empresa_id' => $empId,
        'contacto_id' => $ctoId,
        'titulo' => 'Prototipo Secador + Montaje',
        'etapa' => 'prospecto',
        'valor_estimado' => 0,
        'origen_canal' => 'web',
        'probabilidad' => 20,
        'notas' => 'E2E: etapa lead del reporte = prospecto en CRM',
    ), $user);
    $oppId = (int) $opp['oportunidad']['id'];
    $etapa = (string) $opp['oportunidad']['etapa'];
    if ($etapa !== 'prospecto') {
        e2e_fail('3', 'Etapa esperada prospecto (lead), obtenida ' . $etapa);
    }
    e2e_ok('3', 'oportunidad ' . $opp['oportunidad']['codigo'] . ' id=' . $oppId
        . ' «Prototipo Secador + Montaje» etapa=prospecto (lead en reportes)');
} catch (\Crm\ApiException $e) {
    e2e_fail('3', $e->getMessage());
} catch (Exception $e) {
    e2e_fail('3', $e->getMessage());
}

/* ------------------------------------------------------------------ */
/* PASO 4 — Cotización mixta producto + servicio                      */
/* ------------------------------------------------------------------ */
$pasoActual = 4;
try {
    $prod = $pdo->query(
        'SELECT id, codigo, nombre, precio_unitario, stock
         FROM productos
         WHERE precio_unitario > 0
         ORDER BY id ASC
         LIMIT 1'
    )->fetch(PDO::FETCH_ASSOC);
    if (!is_array($prod)) {
        e2e_fail('4', 'No hay productos en inventario para cotizar');
    }

    $precioProd = round((float) $prod['precio_unitario'], 2);
    $montoServicio = 150000.0;
    $items = array(
        array(
            'tipo_item' => 'producto',
            'producto_id' => (int) $prod['id'],
            'cantidad' => 1,
        ),
        array(
            'tipo_item' => 'servicio',
            'producto_id' => null,
            'codigo' => 'SERV',
            'descripcion' => 'Servicio de Instalación y Calibración',
            'cantidad' => 1,
            'precio_unitario' => $montoServicio,
        ),
    );

    $cot = \Crm\Cotizaciones::store(array(
        'empresa_id' => $empId,
        'contacto_id' => $ctoId,
        'oportunidad_id' => $oppId,
        'vendedor_id' => $vendId,
        'estado' => 'borrador',
        'fecha_emision' => date('Y-m-d'),
        'items' => $items,
        'notas' => 'E2E cotización mixta',
    ), $user);
    $cotizacion = $cot['cotizacion'];
    $cotId = (int) $cotizacion['id'];
    $cotItems = isset($cotizacion['items']) && is_array($cotizacion['items']) ? $cotizacion['items'] : array();
    if (count($cotItems) !== 2) {
        e2e_fail('4', 'Se esperaban 2 ítems, hay ' . count($cotItems));
    }

    $itemProd = null;
    $itemServ = null;
    foreach ($cotItems as $it) {
        if ((string) $it['tipo_item'] === 'servicio') {
            $itemServ = $it;
        } else {
            $itemProd = $it;
        }
    }
    if ($itemProd === null || $itemServ === null) {
        e2e_fail('4', 'Falta ítem producto o servicio');
    }
    if ($itemServ['producto_id'] !== null && $itemServ['producto_id'] !== '') {
        e2e_fail('4', 'Servicio debe dejar producto_id NULL');
    }
    if ((string) $itemServ['codigo'] !== 'SERV') {
        e2e_fail('4', 'Código de servicio esperado SERV, obtenido ' . $itemServ['codigo']);
    }

    $netoEsp = round($precioProd + $montoServicio, 2);
    $ivaEsp = round($netoEsp * (crm_iva_pct() / 100), 2);
    $totalEsp = round($netoEsp + $ivaEsp, 2);
    $neto = round((float) $cotizacion['subtotal'] - (float) $cotizacion['descuento'], 2);
    $iva = round((float) $cotizacion['iva'], 2);
    $total = round((float) $cotizacion['total'], 2);

    if (!e2e_money_eq($neto, $netoEsp) || !e2e_money_eq($iva, $ivaEsp) || !e2e_money_eq($total, $totalEsp)) {
        e2e_fail(
            '4',
            'Totales incorrectos. neto=' . $neto . ' (esp ' . $netoEsp . ')'
            . ' iva=' . $iva . ' (esp ' . $ivaEsp . ')'
            . ' total=' . $total . ' (esp ' . $totalEsp . ')'
        );
    }
    if ((int) $cotizacion['vendedor_id'] !== $vendId) {
        e2e_fail('4', 'Cotización no quedó vinculada al vendedor');
    }
    if ((int) $cotizacion['oportunidad_id'] !== $oppId || (int) $cotizacion['contacto_id'] !== $ctoId) {
        e2e_fail('4', 'Cotización no quedó vinculada a oportunidad/contacto');
    }

    e2e_ok(
        '4',
        $cotizacion['folio'] . ' id=' . $cotId
        . ' SKU ' . $prod['codigo'] . ' @ ' . number_format($precioProd, 2, '.', '')
        . ' + SERV 150000 · neto=' . number_format($neto, 2, '.', '')
        . ' IVA 19%=' . number_format($iva, 2, '.', '')
        . ' total=' . number_format($total, 2, '.', '')
    );
} catch (\Crm\ApiException $e) {
    e2e_fail('4', $e->getMessage());
} catch (Exception $e) {
    e2e_fail('4', $e->getMessage());
}

/* Baseline de KPIs antes del cierre (borrador no suma a ganadas). */
$kpisAntes = \Crm\Reportes::obtener('resumen_kpis', array(
    'desde' => date('Y-m-01'),
    'hasta' => date('Y-m-d'),
));
$ganadasAntes = (float) $kpisAntes['kpis']['ventas_ganadas'];
$comAntes = (float) $kpisAntes['kpis']['comisiones'];

/* ------------------------------------------------------------------ */
/* PASO 5 — Cierre aceptada + oportunidad ganada + comisión 5%        */
/* ------------------------------------------------------------------ */
$pasoActual = 5;
try {
    $acept = \Crm\Cotizaciones::update($cotId, array(
        'empresa_id' => $empId,
        'contacto_id' => $ctoId,
        'oportunidad_id' => $oppId,
        'vendedor_id' => $vendId,
        'estado' => 'aceptada',
        'fecha_emision' => date('Y-m-d'),
        'items' => $items,
    ), $user);
    $estadoCot = (string) $acept['cotizacion']['estado'];
    if ($estadoCot !== 'aceptada') {
        e2e_fail('5', 'Estado de cotización esperado aceptada, obtenido ' . $estadoCot);
    }

    \Crm\Oportunidades::update($oppId, array(
        'empresa_id' => $empId,
        'contacto_id' => $ctoId,
        'titulo' => 'Prototipo Secador + Montaje',
        'etapa' => 'ganada',
        'valor_estimado' => $total,
        'origen_canal' => 'web',
        'probabilidad' => 100,
    ), $user);
    $oppAfter = \Crm\Oportunidades::show($oppId);
    $etapaGanada = (string) $oppAfter['oportunidad']['etapa'];
    if ($etapaGanada !== 'ganada') {
        e2e_fail('5', 'Etapa esperada ganada (ganado), obtenida ' . $etapaGanada);
    }

    $netoCierre = round((float) $acept['cotizacion']['subtotal'] - (float) $acept['cotizacion']['descuento'], 2);
    $comEsp = \Crm\Comisiones::calcularMonto($netoCierre, 5.00);
    $comStmt = $pdo->prepare(
        'SELECT * FROM crm_comisiones WHERE cotizacion_id = ? AND vendedor_id = ? LIMIT 1'
    );
    $comStmt->execute(array($cotId, $vendId));
    $comision = $comStmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($comision)) {
        e2e_fail('5', 'No se insertó registro en crm_comisiones');
    }
    $montoCom = round((float) $comision['monto_comision'], 2);
    $pctCom = round((float) $comision['porcentaje_aplicado'], 2);
    if (!e2e_money_eq($pctCom, 5.0)) {
        e2e_fail('5', 'porcentaje_aplicado esperado 5.00, obtenido ' . $pctCom);
    }
    if (!e2e_money_eq($montoCom, $comEsp)) {
        e2e_fail('5', 'monto_comision esperado ' . $comEsp . ' (5% de neto ' . $netoCierre . '), obtenido ' . $montoCom);
    }
    if (!e2e_money_eq($comision['monto_venta_neto'], $netoCierre)) {
        e2e_fail('5', 'monto_venta_neto no coincide con el neto de la cotización');
    }

    e2e_ok(
        '5',
        'cotización aceptada, oportunidad etapa=ganada (ganado) · comisión id='
        . (int) $comision['id'] . ' ' . number_format($montoCom, 2, '.', '')
        . ' = 5% × neto ' . number_format($netoCierre, 2, '.', '')
    );
} catch (\Crm\ApiException $e) {
    e2e_fail('5', $e->getMessage());
} catch (Exception $e) {
    e2e_fail('5', $e->getMessage());
}

/* ------------------------------------------------------------------ */
/* PASO 6 — Reportes resumen_kpis refleja la venta                    */
/* ------------------------------------------------------------------ */
$pasoActual = 6;
try {
    $kpisDom = \Crm\Reportes::obtener('resumen_kpis', array(
        'desde' => date('Y-m-01'),
        'hasta' => date('Y-m-d'),
    ));
    $ganadasDesp = (float) $kpisDom['kpis']['ventas_ganadas'];
    $comDesp = (float) $kpisDom['kpis']['comisiones'];
    $deltaGanadas = round($ganadasDesp - $ganadasAntes, 2);
    $deltaCom = round($comDesp - $comAntes, 2);
    $totalVenta = round((float) $acept['cotizacion']['total'], 2);

    if (!e2e_money_eq($deltaGanadas, $totalVenta)) {
        e2e_fail(
            '6',
            'ventas_ganadas no refleja la venta. delta=' . $deltaGanadas
            . ' esperado total=' . $totalVenta
            . ' (antes=' . $ganadasAntes . ' después=' . $ganadasDesp . ')'
        );
    }
    if (!e2e_money_eq($deltaCom, $montoCom)) {
        e2e_fail(
            '6',
            'comisiones no reflejan el 5%. delta=' . $deltaCom
            . ' esperado=' . $montoCom
            . ' (antes=' . $comAntes . ' después=' . $comDesp . ')'
        );
    }

    $httpDetalle = 'dominio Reportes::obtener';
    $base = rtrim((string) crm_env('APP_URL', 'http://127.0.0.1:8080'), '/');
    $cookieFile = sys_get_temp_dir() . '/crm-e2e-flujo.cookie';
    if (is_file($cookieFile)) {
        unlink($cookieFile);
    }
    $loginHttp = e2e_http_json(
        $base . '/api/auth.php',
        'POST',
        array('email' => 'ivan.p@example.net', 'password' => 'Lpaezsis.2026'),
        $cookieFile
    );
    if (!empty($loginHttp['ok'])) {
        $apiKpis = e2e_http_json(
            $base . '/api/reportes.php?tipo=resumen_kpis&desde=' . date('Y-m-01') . '&hasta=' . date('Y-m-d'),
            'GET',
            null,
            $cookieFile
        );
        if (empty($apiKpis['ok']) || !isset($apiKpis['kpis']['ventas_ganadas'])) {
            e2e_fail('6', 'API HTTP resumen_kpis inválida: ' . json_encode($apiKpis, JSON_UNESCAPED_UNICODE));
        }
        if (!e2e_money_eq($apiKpis['kpis']['ventas_ganadas'], $ganadasDesp)) {
            e2e_fail('6', 'API HTTP ventas_ganadas distinta del dominio');
        }
        if (!e2e_money_eq($apiKpis['kpis']['comisiones'], $comDesp)) {
            e2e_fail('6', 'API HTTP comisiones distinta del dominio');
        }
        $httpDetalle = 'GET api/reportes.php?tipo=resumen_kpis HTTP ' . (int) $apiKpis['_http'];
    }

    e2e_ok(
        '6',
        $httpDetalle
        . ' · ventas_ganadas +' . number_format($deltaGanadas, 2, '.', '')
        . ' · comisiones +' . number_format($deltaCom, 2, '.', '')
        . ' (ganadas=' . number_format($ganadasDesp, 2, '.', '')
        . ' comisiones=' . number_format($comDesp, 2, '.', '') . ')'
    );
} catch (\Crm\ApiException $e) {
    e2e_fail('6', $e->getMessage());
} catch (Exception $e) {
    e2e_fail('6', $e->getMessage());
}

echo PHP_EOL;
echo 'RESULTADO: ' . $passed . '/6 OK' . PHP_EOL;
exit(0);
