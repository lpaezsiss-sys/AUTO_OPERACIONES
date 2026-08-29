#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Verifica que el paquete de despliegue tenga index.php y .htaccess en la raíz
 * (no encapsulados en una subcarpeta tipo AUTO_OPERACIONES-tag/ o crm_backup/...).
 *
 *   php scripts/check_deploy_paths.php
 *   php scripts/check_deploy_paths.php downloads/crm_backup_YYYYMMDD_HHMM.zip
 */

$root = dirname(__DIR__);
$pass = 0;
$fail = 0;

/**
 * @param bool $ok
 * @param string $name
 * @param string $detail
 * @return void
 */
function deploy_check($ok, $name, $detail)
{
    global $pass, $fail;
    if ($ok) {
        $pass++;
        echo 'PASS  ' . $name;
    } else {
        $fail++;
        echo 'FAIL  ' . $name;
    }
    if ($detail !== '') {
        echo ' — ' . $detail;
    }
    echo PHP_EOL;
}

/**
 * @param string $zipPath
 * @return array
 */
function deploy_zip_nombres($zipPath)
{
    $out = array();
    if (class_exists('ZipArchive')) {
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            return array();
        }
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $n = $zip->getNameIndex($i);
            if (is_string($n) && $n !== '' && substr($n, -1) !== '/') {
                $out[] = str_replace('\\', '/', $n);
            }
        }
        $zip->close();
        return $out;
    }
    $bin = trim((string) shell_exec('command -v zipinfo'));
    if ($bin !== '') {
        exec(escapeshellarg($bin) . ' -1 ' . escapeshellarg($zipPath) . ' 2>/dev/null', $out);
        $clean = array();
        foreach ($out as $n) {
            $n = str_replace('\\', '/', trim((string) $n));
            if ($n !== '' && substr($n, -1) !== '/') {
                $clean[] = $n;
            }
        }
        return $clean;
    }
    $zipBin = trim((string) shell_exec('command -v zip'));
    if ($zipBin === '') {
        return array();
    }
    $raw = array();
    exec(escapeshellarg($zipBin) . ' -sf ' . escapeshellarg($zipPath) . ' 2>/dev/null', $raw);
    $clean = array();
    foreach ($raw as $line) {
        $n = str_replace('\\', '/', trim((string) $line));
        if ($n === '' || strpos($n, 'Archive') === 0 || $n === 'Actual' || substr($n, -1) === '/') {
            continue;
        }
        $clean[] = $n;
    }
    return $clean;
}

echo '=== CRM verificación de rutas de despliegue ===' . PHP_EOL;
echo 'Raíz: ' . $root . PHP_EOL;
echo PHP_EOL;

$archivosRaiz = array(
    'index.php' => 'Portada / dashboard',
    'login.php' => 'Login',
    '.htaccess' => 'Apache (DirectoryIndex, HTTPS, sin listado)',
    '.env.production' => 'Plantilla de entorno producción',
);
foreach ($archivosRaiz as $rel => $label) {
    $path = $root . '/' . $rel;
    deploy_check(is_file($path), 'Raíz local: ' . $rel, is_file($path) ? $label : 'no está en la raíz del proyecto');
}

$dirs = array('api', 'config', 'uploads', 'sql', 'src', 'includes', 'assets');
foreach ($dirs as $dir) {
    deploy_check(is_dir($root . '/' . $dir), 'Carpeta local: ' . $dir . '/', is_dir($root . '/' . $dir) ? 'OK' : 'ausente');
}

$htFile = $root . '/.htaccess';
$ht = is_file($htFile) ? (string) file_get_contents($htFile) : '';
$hasDi = (bool) preg_match('/DirectoryIndex\s+index\.php\s+login\.php/i', $ht);
$hasNoIndex = (bool) preg_match('/Options\s+-Indexes/i', $ht);
deploy_check($hasDi, '.htaccess DirectoryIndex index.php login.php', $hasDi ? 'Apache servirá login si no hay index' : 'falta DirectoryIndex');
deploy_check($hasNoIndex, '.htaccess Options -Indexes', $hasNoIndex ? 'sin listado de directorio' : 'falta Options -Indexes');

$zipArg = isset($argv[1]) ? (string) $argv[1] : '';
$zips = array();
if ($zipArg !== '') {
    $zips[] = str_starts_with($zipArg, '/') ? $zipArg : $root . '/' . ltrim($zipArg, '/');
} else {
    $found = glob($root . '/downloads/crm_backup_*.zip');
    if (is_array($found) && count($found) > 0) {
        usort($found, static function ($a, $b) {
            return filemtime($b) - filemtime($a);
        });
        $zips[] = $found[0];
    }
}

if (count($zips) === 0) {
    echo PHP_EOL . 'NOTA: no hay ZIP en downloads/. Genera uno con: php scripts/crear_respaldo.php' . PHP_EOL;
} else {
    echo PHP_EOL;
    foreach ($zips as $zipPath) {
        $relZip = str_replace($root . '/', '', $zipPath);
        deploy_check(is_file($zipPath), 'ZIP existe', $relZip);
        if (!is_file($zipPath)) {
            continue;
        }
        $names = deploy_zip_nombres($zipPath);
        deploy_check(count($names) > 0, 'ZIP listable', count($names) . ' archivo(s)');
        $enRaiz = array(
            'index.php' => false,
            'login.php' => false,
            '.htaccess' => false,
            '.env.production' => false,
        );
        $anidadoIndex = array();
        foreach ($names as $n) {
            if (isset($enRaiz[$n])) {
                $enRaiz[$n] = true;
            }
            if (preg_match('#^([^/]+)/index\.php$#', $n, $m)) {
                $anidadoIndex[] = $m[1];
            }
        }
        foreach ($enRaiz as $req => $ok) {
            deploy_check($ok, 'ZIP raíz: ' . $req, $ok ? 'en la raíz del ZIP' : 'NO está en la raíz (típico del ZIP de GitHub: carpeta-repo-tag/)');
        }
        $capsula = array();
        foreach ($anidadoIndex as $pref) {
            $capsula[$pref] = true;
        }
        $capsulas = array_keys($capsula);
        deploy_check(
            $enRaiz['index.php'] === true,
            'ZIP sin encapsulado',
            $enRaiz['index.php']
                ? 'index.php está en la raíz; se puede extraer directo al document root'
                : ('index.php está dentro de: ' . implode(', ', $capsulas) . ' — en cPanel hay que MOVER el contenido de esa carpeta a la raíz del subdominio')
        );
        $dirsZip = array('api/' => false, 'config/' => false, 'uploads/' => false, 'sql/' => false);
        foreach ($names as $n) {
            foreach (array_keys($dirsZip) as $d) {
                if (strpos($n, $d) === 0) {
                    $dirsZip[$d] = true;
                }
            }
        }
        foreach ($dirsZip as $d => $ok) {
            deploy_check($ok, 'ZIP carpeta ' . $d, $ok ? 'presente' : 'ausente');
        }
    }
}

echo PHP_EOL;
echo 'Estructura esperada en cPanel (document root del subdominio):' . PHP_EOL;
echo '  public_html/crm/   (o public_html/crm.lpaezsis.cl/)' . PHP_EOL;
echo '    index.php' . PHP_EOL;
echo '    login.php' . PHP_EOL;
echo '    .htaccess' . PHP_EOL;
echo '    .env' . PHP_EOL;
echo '    api/  config/  uploads/  sql/' . PHP_EOL;
echo PHP_EOL;
echo 'Resultado: ' . $pass . ' PASS / ' . $fail . ' FAIL' . PHP_EOL;
if ($fail > 0) {
    echo 'FAIL' . PHP_EOL;
    exit(1);
}
echo 'PASS' . PHP_EOL;
exit(0);
