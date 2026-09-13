<?php

declare(strict_types=1);

/**
 * Compatibilidad PHP 7.4 (BlueHosting). No usar APIs de PHP 8+.
 */
if (!function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle)
    {
        $haystack = (string) $haystack;
        $needle = (string) $needle;
        if ($needle === '') {
            return true;
        }
        return strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}

if (!function_exists('str_contains')) {
    function str_contains($haystack, $needle)
    {
        $haystack = (string) $haystack;
        $needle = (string) $needle;
        if ($needle === '') {
            return true;
        }
        return strpos($haystack, $needle) !== false;
    }
}

if (!function_exists('str_ends_with')) {
    function str_ends_with($haystack, $needle)
    {
        $haystack = (string) $haystack;
        $needle = (string) $needle;
        if ($needle === '') {
            return true;
        }
        $len = strlen($needle);
        if ($len > strlen($haystack)) {
            return false;
        }
        return substr($haystack, -$len) === $needle;
    }
}

/**
 * Valor seguro para nativas de string (PHP 8.1 no acepta null implícito).
 *
 * @param mixed $value
 * @return string
 */
function crm_string($value)
{
    if ($value === null) {
        return '';
    }
    if (is_string($value)) {
        return $value;
    }
    if (is_bool($value)) {
        return $value ? '1' : '';
    }
    if (is_int($value) || is_float($value)) {
        return (string) $value;
    }
    if (is_object($value) && method_exists($value, '__toString')) {
        return (string) $value;
    }
    return '';
}

function crm_lower($value)
{
    $value = crm_string($value);
    if (function_exists('mb_strtolower')) {
        return mb_strtolower($value, 'UTF-8');
    }
    return strtolower($value);
}
