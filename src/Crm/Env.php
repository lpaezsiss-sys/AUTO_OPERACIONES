<?php

declare(strict_types=1);

namespace Crm;

/**
 * Carga `.env` del document root. getenv() gana sobre el archivo
 * (útil en tests y en CageFS si se inyectan variables).
 */
final class Env
{
    private static ?self $instance = null;

    /** @var array<string, string> */
    private array $map = [];

    private function __construct(private readonly string $root)
    {
        $file = $this->root . '/.env';
        if (!is_file($file) || !is_readable($file)) {
            return;
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES);
        if (!is_array($lines)) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            $eq = strpos($line, '=');
            if ($eq === false) {
                continue;
            }
            $key = trim(substr($line, 0, $eq));
            if ($key === '') {
                continue;
            }
            $value = trim(substr($line, $eq + 1));
            if (
                (str_starts_with($value, '"') && str_ends_with($value, '"'))
                || (str_starts_with($value, "'") && str_ends_with($value, "'"))
            ) {
                $value = substr($value, 1, -1);
            }
            $this->map[$key] = $value;
        }
    }

    public static function boot(string $root): self
    {
        self::$instance = new self($root);
        return self::$instance;
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            throw new \RuntimeException('Env no inicializado. Llama a Env::boot().');
        }
        return self::$instance;
    }

    public static function reset(): void
    {
        self::$instance = null;
    }

    public function get(string $key, ?string $default = null): ?string
    {
        $fromEnv = getenv($key);
        if ($fromEnv !== false && $fromEnv !== '') {
            return $fromEnv;
        }
        if (array_key_exists($key, $this->map) && $this->map[$key] !== '') {
            return $this->map[$key];
        }
        return $default;
    }

    public function string(string $key, string $default = ''): string
    {
        return (string) $this->get($key, $default);
    }

    public function bool(string $key, bool $default = false): bool
    {
        $raw = $this->get($key);
        if ($raw === null) {
            return $default;
        }
        return in_array(strtolower($raw), ['1', 'true', 'yes', 'on'], true);
    }

    public function root(): string
    {
        return $this->root;
    }
}
