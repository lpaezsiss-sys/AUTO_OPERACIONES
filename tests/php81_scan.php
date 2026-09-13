<?php

declare(strict_types=1);

/**
 * Escaneo estático de riesgos PHP 8.1 en el código de aplicación (sin vendor/deps).
 *
 *   php tests/php81_scan.php
 *
 * @return int cantidad de fallos
 */
function crm_php81_scan()
{
    $root = dirname(__DIR__);
    $fail = 0;
    $dirs = array(
        $root . '/src',
        $root . '/api',
        $root . '/includes',
        $root . '/config',
    );
    $files = array(
        $root . '/cotizacion.php',
        $root . '/cotizador.php',
        $root . '/cotizaciones.php',
    );
    foreach ($dirs as $dir) {
        if (!is_dir($dir)) {
            continue;
        }
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
        foreach ($it as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }
    }

    $banned = array(
        '/\bFILTER_SANITIZE_STRING\b/' => 'FILTER_SANITIZE_STRING (eliminado en 8.1)',
        '/\bstrftime\s*\(/' => 'strftime() deprecado en 8.1',
        '/\bstrptime\s*\(/' => 'strptime() deprecado en 8.1',
        '/\butf8_encode\s*\(/' => 'utf8_encode() deprecado en 8.1',
        '/\butf8_decode\s*\(/' => 'utf8_decode() deprecado en 8.1',
        '/\beach\s*\(/' => 'each() eliminado en 8.0',
        '/\bcreate_function\s*\(/' => 'create_function() eliminado en 8.0',
        '/(?<![\?\\\\])\b(?:PDO|string|int|bool|array|float)\s+\$\w+\s*=\s*null\b/' => 'nullable implícito (usar ?Tipo \$x = null)',
    );

    foreach ($files as $path) {
        $src = (string) file_get_contents($path);
        $src = preg_replace('#/\*.*?\*/#s', '', $src);
        $src = preg_replace('#^\s*//.*$#m', '', $src);
        foreach ($banned as $re => $label) {
            if (preg_match($re, $src)) {
                echo 'FAIL PHP 8.1 ' . $label . ' in ' . $path . "\n";
                $fail++;
            }
        }
    }

    if ($fail === 0) {
        echo "OK PHP 8.1 static scan\n";
    }
    return $fail;
}

if (PHP_SAPI === 'cli' && isset($_SERVER['SCRIPT_FILENAME'])
    && realpath((string) $_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__)) {
    $n = crm_php81_scan();
    exit($n > 0 ? 1 : 0);
}
