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
    id TEXT PRIMARY KEY,
    code TEXT UNIQUE NOT NULL,
    name TEXT NOT NULL,
    description TEXT DEFAULT \'\',
    stock REAL DEFAULT 0,
    averageUnitCost REAL DEFAULT 0
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
