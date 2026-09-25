#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Suite de integración PHP 8.1 (SQLite temporal para inventario).
 */
$root = dirname(__DIR__);
$tmpInv = $root . '/data/test-inventory.sqlite';
$tmpApp = $root . '/data/test-comex.sqlite';
foreach ([$tmpInv, $tmpApp] as $base) {
    foreach ([$base, $base . '-wal', $base . '-shm', $base . '-journal'] as $f) {
        if (is_file($f)) {
            unlink($f);
        }
    }
}

$envFile = $root . '/.env';
if (!is_file($envFile)) {
    copy($root . '/.env.example', $envFile);
}

putenv('APP_ENV=test');
putenv('APP_DEBUG=1');
putenv('COMEX_DB_DRIVER=sqlite');
putenv('COMEX_SQLITE_PATH=' . $tmpApp);
putenv('INV_SQLITE_PATH=' . $tmpInv);
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTPS'] = 'off';
$_SERVER['SERVER_PORT'] = '80';

require $root . '/includes/bootstrap.php';

$failed = 0;
$passed = 0;

function assert_true(mixed $cond, string $msg): void
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

echo '== PHP ' . PHP_VERSION . " ==\n";

$platform = \Crm\Platform::inspect();
assert_true($platform['php_ok'], 'PHP >= 8.1.0');
assert_true($platform['php_is_81'], 'PHP rama 8.1.x (BlueHosting)');
assert_true($platform['missing'] === [], 'Extensiones requeridas: ' . implode(',', $platform['missing']));
foreach (\Crm\Platform::REQUIRED_EXTENSIONS as $ext) {
    assert_true(extension_loaded($ext), 'ext ' . $ext);
}

$crmDir = $root . '/src/Crm';
$parentEntries = scandir($root . '/src') ?: [];
assert_true(in_array('Crm', $parentEntries, true), 'Directorio src/Crm (case Linux)');
assert_true(!in_array('crm', $parentEntries, true) || in_array('Crm', $parentEntries, true), 'No hay colisión src/crm');
assert_true(\Crm\Autoloader::pathMatchesCase($crmDir . '/Http.php'), 'Autoload case-sensitive Http.php');
assert_true(class_exists(\Crm\Http::class), 'Crm\\Http autoload');
assert_true(class_exists(\Crm\Inventory\SqliteConnector::class), 'Crm\\Inventory\\SqliteConnector autoload');
assert_true(class_exists(\Crm\Inventory\InventarioStock::class), 'Crm\\Inventory\\InventarioStock autoload');
assert_true(class_exists(\Crm\Comex\Health::class), 'Crm\\Comex\\Health autoload');

$example = (string) file_get_contents($root . '/.env.example');
$prodEnv = (string) file_get_contents($root . '/.env.production');
assert_true(
    str_contains($example, 'INV_SQLITE_PATH=/home/sistem29/app/data/prod.db'),
    '.env.example apunta al SQLite de inventario'
);
assert_true(
    str_contains($prodEnv, 'INV_SQLITE_PATH=/home/sistem29/app/data/prod.db'),
    '.env.production apunta al SQLite de inventario'
);
assert_true(crm_env('INV_SQLITE_PATH') === $tmpInv, 'INV_SQLITE_PATH de test vía getenv');

$pdoInv = new PDO('sqlite:' . $tmpInv, null, null, \Crm\Database\Connection::options());
$pdoInv->exec('CREATE TABLE Product (
    id TEXT NOT NULL PRIMARY KEY,
    code TEXT NOT NULL UNIQUE,
    name TEXT NOT NULL,
    description TEXT NOT NULL DEFAULT \'\',
    stock REAL NOT NULL DEFAULT 0,
    averageUnitCost REAL NOT NULL DEFAULT 0,
    lowStockThreshold REAL NOT NULL DEFAULT 2,
    createdAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updatedAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
)');
$pdoInv->exec('CREATE TABLE Movement (
    id TEXT NOT NULL PRIMARY KEY,
    type TEXT NOT NULL,
    documentNumber TEXT NOT NULL,
    productId TEXT NOT NULL,
    quantity REAL NOT NULL,
    unitPrice REAL NOT NULL DEFAULT 0,
    date TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    createdAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (productId) REFERENCES Product(id)
)');
$ins = $pdoInv->prepare(
    'INSERT INTO Product (id, code, name, description, stock, averageUnitCost) VALUES (?,?,?,?,?,?)'
);
$ins->execute(['p1', '12852-48', 'Banda transportadora', 'SKU demo', 12.5, 1500.0]);
$ins->execute(['p2', 'ABC-99', 'Rodamiento', 'otro', 3, 80]);
$pdoInv = null;
\Crm\Database\Connection::reset();

assert_true(\Crm\Inventory\InventarioStock::disponible(), 'PDO inventario SQLite');
assert_true(\Crm\Inventory\InventarioStock::stockPorCodigo('12852-48') === 12.5, 'stockPorCodigo exacto');
assert_true(\Crm\Inventory\InventarioStock::stockPorCodigo('no-existe') === null, 'SKU inexistente');
$hits = \Crm\Inventory\InventarioStock::buscar('12852-48');
assert_true(isset($hits[0]['code']) && $hits[0]['code'] === '12852-48', 'buscar código exacto');
assert_true(\Crm\Inventory\InventarioStock::likeNeedle('12852-48') === '%12852-48%', 'likeNeedle conserva guion');
assert_true(\Crm\Inventory\InventarioStock::likeNeedle('a%b_c') === '%a!%b!_c%', 'likeNeedle escapa % y _');
assert_true(\Crm\Inventory\InventarioStock::likeEscapeSql() === " ESCAPE '!'", 'LIKE ESCAPE !');

$ht = (string) file_get_contents($root . '/.htaccess');
assert_true(str_contains($ht, 'Require all denied'), '.htaccess deniega .env (Require all denied)');
assert_true((bool) preg_match('/RewriteRule.*\.env.*\[F/', $ht), '.htaccess RewriteRule 403 para .env');
assert_true(str_contains($ht, 'RewriteCond %{HTTPS} !=on'), '.htaccess fuerza HTTPS');
assert_true(str_contains($ht, 'RewriteRule ^ index.php'), '.htaccess front controller');
assert_true((bool) preg_match('#RewriteRule \^\(config\|src\|includes#', $ht), '.htaccess 403 a config/src/includes');

$dav = (string) file_get_contents($root . '/.webdavignore');
assert_true(str_contains($dav, 'uploads/'), '.webdavignore excluye uploads/');
assert_true(str_contains($dav, '.env'), '.webdavignore excluye .env');
assert_true(is_file($root . '/uploads/.webdav-exclude'), 'uploads/.webdav-exclude marker');
assert_true(is_file($root . '/deploy/webdav.exclude'), 'deploy/webdav.exclude');

\Crm\Storage\Uploads::ensure();
$up = \Crm\Storage\Uploads::status();
assert_true($up['exists'] && $up['writable'], 'uploads/ existe y es escribible');
$mode = (int) fileperms($root . '/uploads') & 0777;
assert_true(\Crm\Storage\Uploads::isSafeMode($mode), 'uploads/ modo 755 o 775 (actual ' . decoct($mode) . ')');
$modeComex = (int) fileperms($root . '/uploads/comex') & 0777;
assert_true(\Crm\Storage\Uploads::isSafeMode($modeComex), 'uploads/comex/ modo 755 o 775');

$health = \Crm\Comex\Health::payload();
assert_true($health['service'] === 'comex-lpaezsis', 'Health service comex-lpaezsis');
assert_true($health['inventory']['connected'] === true, 'Health inventario connected');
assert_true($health['db'] === 'ok', 'Health COMEX sqlite ok');

$write = \Crm\Inventory\SqliteConnector::write();
$journal = strtolower((string) $write->query('PRAGMA journal_mode')->fetchColumn());
assert_true($journal === 'wal', 'prod.db en WAL mode (' . $journal . ')');
$busy = (int) $write->query('PRAGMA busy_timeout')->fetchColumn();
assert_true($busy === \Crm\Inventory\SqliteConnector::busyTimeoutMs(), 'busy_timeout ' . $busy . ' ms');

$read = \Crm\Inventory\SqliteConnector::read();
assert_true($read instanceof PDO, 'Conexión de lectura query_only');
$readBlocked = false;
try {
    $read->exec("UPDATE Product SET stock = 0 WHERE code = 'ABC-99'");
} catch (PDOException) {
    $readBlocked = true;
}
assert_true($readBlocked, 'La conexión de lectura no escribe (query_only)');

$cat = \Crm\Inventory\Catalogo::porCodigo('12852-48');
assert_true(is_array($cat) && $cat['id'] === 'p1', 'Catalogo::porCodigo vincula Product.id');

$sync = \Crm\Comex\Fichas::sincronizarDesdeInventario();
assert_true($sync['total'] === 2 && $sync['created'] >= 2, 'Sync fichas desde prod.db');
$ficha = \Crm\Comex\Fichas::porSku('12852-48');
assert_true(is_array($ficha) && $ficha['vinculado'] === true, 'Ficha vinculada a inventario');
assert_true((float) $ficha['stock'] === 12.5, 'Ficha muestra stock vivo');
assert_true(is_string($ficha['imagen_path']) && str_starts_with((string) $ficha['imagen_path'], 'uploads/'), 'Ficha guarda imagen en uploads/');
$imgAbs = $root . '/' . $ficha['imagen_path'];
assert_true(is_file($imgAbs), 'PNG de ficha existe');
$imgInfo = @getimagesize($imgAbs);
assert_true(is_array($imgInfo) && (int) $imgInfo[2] === IMAGETYPE_PNG, 'Imagen GD PNG válida');
$imgMode = (int) fileperms($imgAbs) & 0777;
assert_true($imgMode === 0644, 'Imagen modo 644 (actual ' . decoct($imgMode) . ')');
$prodDirMode = (int) fileperms($root . '/uploads/comex/productos') & 0777;
assert_true(\Crm\Storage\Uploads::isSafeMode($prodDirMode), 'uploads/comex/productos 755/775');

$imp = \Crm\Comex\Operaciones::crear([
    'tipo' => 'IMPORTACION',
    'folio' => 'IMP-TEST-1',
    'fecha' => '2026-09-13',
    'items' => [
        ['sku' => '12852-48', 'cantidad' => 2, 'precio_unitario' => 1600],
    ],
]);
assert_true((int) $imp['id'] > 0 && $imp['estado'] === 'borrador', 'Alta importación borrador');
$conf = \Crm\Comex\Operaciones::confirmar((int) $imp['id']);
assert_true($conf['estado'] === 'confirmada', 'Importación confirmada');
assert_true(\Crm\Inventory\InventarioStock::stockPorCodigo('12852-48') === 14.5, 'ENTRADA suma stock 12.5+2');
$cup = \Crm\Inventory\Catalogo::porCodigo('12852-48');
$expectedCup = \Crm\Inventory\StockSync::nuevoCostoPromedio(12.5, 1500.0, 2, 1600);
assert_true(is_array($cup) && abs((float) $cup['averageUnitCost'] - $expectedCup) < 0.0001, 'CUP/PMP actualizado');
assert_true(str_starts_with((string) $conf['pdf_path'], 'uploads/comex/pdf/'), 'PDF en uploads/comex/pdf');
$pdfAbs = $root . '/' . $conf['pdf_path'];
assert_true(is_file($pdfAbs) && str_starts_with((string) file_get_contents($pdfAbs), '%PDF'), 'PDF válido');
$pdfDirMode = (int) fileperms($root . '/uploads/comex/pdf') & 0777;
assert_true(\Crm\Storage\Uploads::isSafeMode($pdfDirMode), 'uploads/comex/pdf 755/775');
$movs = \Crm\Inventory\Catalogo::movimientosPorProducto('p1');
assert_true($movs !== [] && $movs[0]['type'] === 'ENTRADA', 'Movement ENTRADA en prod.db');

$exp = \Crm\Comex\Operaciones::crear([
    'tipo' => 'EXPORTACION',
    'folio' => 'EXP-TEST-1',
    'items' => [['sku' => 'ABC-99', 'cantidad' => 1, 'precio_unitario' => 0]],
]);
$expOk = \Crm\Comex\Operaciones::confirmar((int) $exp['id']);
assert_true($expOk['estado'] === 'confirmada', 'Exportación confirmada');
assert_true(\Crm\Inventory\InventarioStock::stockPorCodigo('ABC-99') === 2.0, 'SALIDA resta stock 3-1');

$over = \Crm\Comex\Operaciones::crear([
    'tipo' => 'EXPORTACION',
    'folio' => 'EXP-TEST-OVER',
    'items' => [['sku' => 'ABC-99', 'cantidad' => 99]],
]);
$overFail = false;
try {
    \Crm\Comex\Operaciones::confirmar((int) $over['id']);
} catch (\Crm\ApiException $e) {
    $overFail = $e->status === 409;
}
assert_true($overFail, 'Exportación sin stock = 409');

putenv('IVA_PCT=19');
$gastosEst = [
    ['codigo' => 'FLETE_INTL', 'moneda' => 'USD', 'monto' => 30, 'ambito' => 'ORIGEN', 'cif' => true, 'nombre' => 'Flete internacional'],
    ['codigo' => 'SEGURO', 'moneda' => 'USD', 'monto' => 6, 'ambito' => 'ORIGEN', 'cif' => true, 'nombre' => 'Seguro'],
    ['codigo' => 'ADUANA', 'moneda' => 'CLP', 'monto' => 15000, 'ambito' => 'LOCAL', 'cif' => false, 'nombre' => 'Aduana'],
    ['codigo' => 'AGENCIA', 'moneda' => 'CLP', 'monto' => 9000, 'ambito' => 'LOCAL', 'cif' => false, 'nombre' => 'Agencia'],
    ['codigo' => 'FLETE_INTERNO', 'moneda' => 'CLP', 'monto' => 6000, 'ambito' => 'LOCAL', 'cif' => false, 'nombre' => 'Flete interno'],
    ['codigo' => 'BANCARIOS', 'moneda' => 'CLP', 'monto' => 3000, 'ambito' => 'LOCAL', 'cif' => false, 'nombre' => 'Gastos bancarios'],
];
$parts = \Crm\Comex\LandedCost::prorratear(33000, [200.0, 100.0]);
assert_true($parts[0] === 22000.0 && $parts[1] === 11000.0, 'Prorrateo FOB 2/3 y 1/3');
assert_true(\Crm\Comex\LandedCost::aClp(10, 'EUR', 900, 1050) === 10500.0, 'EUR × TC');
assert_true(abs(crm_iva_pct() - 19.0) < 0.001, 'IVA_PCT 19 Chile');

$opLc = \Crm\Comex\Operaciones::crear([
    'tipo' => 'IMPORTACION',
    'folio' => 'IMP-LANDED-1',
    'items' => [
        ['sku' => '12852-48', 'cantidad' => 2, 'precio_unitario' => 100, 'descripcion' => 'Banda'],
        ['sku' => 'ABC-99', 'cantidad' => 1, 'precio_unitario' => 100, 'descripcion' => 'Rodamiento'],
    ],
]);
$calc = \Crm\Comex\LandedCostStore::calcularDesde([
    'operacion_id' => $opLc['id'],
    'version' => 'ESTIMADA',
    'moneda_origen' => 'USD',
    'tipo_cambio_usd' => 900,
    'tipo_cambio_eur' => 1050,
    'iva_pct' => 19,
    'gastos' => $gastosEst,
]);
assert_true((float) $calc['totales']['fob_clp'] === 270000.0, 'FOB CLP 300 USD × 900');
assert_true((float) $calc['totales']['gastos_cif_clp'] === 32400.0, 'Flete+seguro CIF 36 USD × 900');
assert_true((float) $calc['totales']['cif_clp'] === 302400.0, 'CIF = FOB + flete + seguro');
assert_true((float) $calc['totales']['iva_clp'] === 57456.0, 'IVA 19% sobre CIF');
assert_true((float) $calc['totales']['gastos_locales_clp'] === 33000.0, 'Gastos locales CLP');
assert_true((float) $calc['totales']['landed_clp'] === 392856.0, 'Landed = CIF + IVA + locales');
assert_true((float) $calc['items'][0]['share'] === 0.666667, 'Share ítem A 200/300');

$est = \Crm\Comex\LandedCostStore::guardar([
    'operacion_id' => $opLc['id'],
    'version' => 'ESTIMADA',
    'moneda_origen' => 'USD',
    'tipo_cambio_usd' => 900,
    'tipo_cambio_eur' => 1050,
    'gastos' => $gastosEst,
    'notas' => 'estimacion',
]);
$gastosReal = $gastosEst;
$gastosReal[2]['monto'] = 27000;
$real = \Crm\Comex\LandedCostStore::guardar([
    'operacion_id' => $opLc['id'],
    'version' => 'REAL',
    'moneda_origen' => 'USD',
    'tipo_cambio_usd' => 900,
    'tipo_cambio_eur' => 1050,
    'gastos' => $gastosReal,
    'notas' => 'real',
]);
assert_true($est['version'] === 'ESTIMADA' && $real['version'] === 'REAL', 'Versiones Estimada y Real');
$pack = \Crm\Comex\LandedCostStore::paraOperacion((int) $opLc['id']);
assert_true(!empty($pack['comparacion']['disponible']), 'Comparación Estimada vs Real');
$dLand = $pack['comparacion']['totales']['landed_clp']['delta'] ?? null;
assert_true((float) $dLand === 12000.0, 'Delta landed = extra aduana 12000');

$pdfRow = \Crm\Comex\LandedCostStore::exportarPdf((int) $est['id']);
$pdfLc = $root . '/' . $pdfRow['pdf_path'];
assert_true(is_file($pdfLc) && str_starts_with((string) file_get_contents($pdfLc), '%PDF'), 'PDF landed cost');
assert_true(str_contains((string) $pdfRow['pdf_path'], 'uploads/comex/pdf/'), 'PDF landed en uploads/');

$ui = (string) file_get_contents($root . '/landed.php');
$layout = (string) file_get_contents($root . '/includes/layout.php');
$css = (string) file_get_contents($root . '/assets/css/app.css');
assert_true(str_contains($layout, 'app-sidebar') && str_contains($css, '--navy: #05294b'), 'UI alineada al panel CRM');
assert_true(str_contains($ui, 'IVA aduanero'), 'UI menciona IVA aduanero');

$zeroFail = false;
try {
    \Crm\Comex\LandedCost::calcular([
        'tipo_cambio_usd' => 900,
        'items' => [['sku' => 'X', 'cantidad' => 1, 'fob_unitario' => 0]],
        'gastos' => [['codigo' => 'ADUANA', 'moneda' => 'CLP', 'monto' => 1000]],
    ]);
} catch (\Crm\ApiException $e) {
    $zeroFail = $e->status === 400;
}
assert_true($zeroFail, 'FOB 0 no prorratea');

putenv('INV_SQLITE_BUSY_TIMEOUT_MS=120');
putenv('INV_SQLITE_RETRIES=1');
\Crm\Inventory\SqliteConnector::reset();
$holder = \Crm\Inventory\SqliteConnector::open(true);
$holder->exec('BEGIN IMMEDIATE');
$busyHit = false;
try {
    \Crm\Inventory\SqliteConnector::transaction(static function (PDO $pdo): void {
        $pdo->exec("UPDATE Product SET stock = stock WHERE code = 'ABC-99'");
    });
} catch (PDOException $e) {
    $busyHit = \Crm\Inventory\SqliteConnector::isBusy($e);
}
$holder->exec('COMMIT');
$holder = null;
assert_true($busyHit, 'BEGIN IMMEDIATE concurrente → SQLITE_BUSY');
putenv('INV_SQLITE_BUSY_TIMEOUT_MS');
putenv('INV_SQLITE_RETRIES');
\Crm\Inventory\SqliteConnector::reset();
\Crm\Database\Connection::reset();

$router = $root . '/router.php';
$denyPaths = ['/.env', '/.env.production', '/config/app.php', '/src/Crm/Http.php', '/includes/bootstrap.php'];
foreach ($denyPaths as $uri) {
    $cmd = 'php -r ' . escapeshellarg(
        '$_SERVER["REQUEST_URI"]=' . var_export($uri, true) . ';'
        . '$_SERVER["REQUEST_METHOD"]="GET";'
        . 'require ' . var_export($router, true) . ';'
    );
    $out = [];
    $code = 0;
    exec($cmd . ' 2>/dev/null', $out, $code);
    $body = implode("\n", $out);
    assert_true(str_contains($body, 'Forbidden'), 'router 403 ' . $uri);
}

echo PHP_EOL . "Resultado: $passed PASS / $failed FAIL" . PHP_EOL;
exit($failed > 0 ? 1 : 0);
