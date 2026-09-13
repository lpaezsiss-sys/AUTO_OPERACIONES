<?php

declare(strict_types=1);

namespace Crm;

/**
 * Autoload PSR-4 para `Crm\` → `src/Crm/`.
 * Linux (CageFS) es case-sensitive: el directorio debe llamarse exactamente `Crm`.
 */
final class Autoloader
{
    public static function register(string $projectRoot): void
    {
        $base = rtrim($projectRoot, '/\\') . '/src/Crm';

        spl_autoload_register(static function (string $class) use ($base): void {
            $prefix = 'Crm\\';
            if (!str_starts_with($class, $prefix)) {
                return;
            }

            $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
            if ($relative === '' || str_contains($relative, '..')) {
                return;
            }

            $file = $base . '/' . $relative . '.php';
            if (!is_file($file)) {
                return;
            }

            if (!self::pathMatchesCase($file)) {
                throw new \RuntimeException(
                    'PSR-4 case mismatch for ' . $class . ' (Linux requiere src/Crm/...).'
                );
            }

            require $file;
        });
    }

    /**
     * Compara cada segmento del path con el nombre real en disco (scandir).
     * Detecta `src/crm` vs `src/Crm` incluso en FS case-insensitive.
     */
    public static function pathMatchesCase(string $absolutePath): bool
    {
        $normalized = str_replace('\\', '/', $absolutePath);
        $parts = explode('/', $normalized);
        $acc = '';

        foreach ($parts as $part) {
            if ($part === '') {
                $acc = '';
                continue;
            }

            $parent = $acc === '' ? '/' : $acc;
            $entries = @scandir($parent);
            if (!is_array($entries) || !in_array($part, $entries, true)) {
                return false;
            }

            $acc = rtrim($parent, '/') . '/' . $part;
        }

        return is_file($acc) || is_dir($acc);
    }
}
