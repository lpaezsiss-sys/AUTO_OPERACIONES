<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
$fail = 0;
    $patterns = array(
        '/\bmatch\s*\(/' => 'match()',
        '/\?->/' => 'nullsafe ?->',
        '/(?:^|[\s\(,])mixed\s+\$/' => 'tipo mixed',
        '/\):\s*never\b/' => 'tipo never',
        '/#\[/' => 'atributos PHP 8',
        '/public\s+function\s+__construct\s*\(\s*(?:private|protected|public)\s+/' => 'constructor property promotion',
    );

foreach ($iterator as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }
    $path = $file->getPathname();
    if (strpos($path, '/vendor/') !== false) {
        continue;
    }
    if (strpos($path, '/lib/pdf/deps/') !== false) {
        continue;
    }
    $src = file_get_contents($path);
    $src = preg_replace('#/\*.*?\*/#s', '', $src);
    $src = preg_replace('#^\s*//.*$#m', '', $src);
    foreach ($patterns as $re => $label) {
        if (preg_match($re, $src)) {
            // Permitir la palabra en comentarios de AGENTS / este scanner
            if (strpos($path, 'tests/php74_scan.php') !== false) {
                continue;
            }
            echo "FAIL $label in $path\n";
            $fail++;
        }
    }
}

echo $fail === 0 ? "OK PHP 7.4 syntax scan\n" : "PHP 7.4 scan failed: $fail\n";
exit($fail > 0 ? 1 : 0);
