<?php

declare(strict_types=1);

namespace Crm;

final class Rut
{
    /**
     * @param string $rut
     * @return string
     */
    public static function normalize($rut)
    {
        return strtoupper(str_replace(array('.', ' ', '-'), '', trim((string) $rut)));
    }

    /**
     * @param string $rut
     * @return string
     */
    public static function format($rut)
    {
        $n = self::normalize($rut);
        if (strlen($n) < 2) {
            return (string) $rut;
        }
        $dv = substr($n, -1);
        $num = substr($n, 0, -1);
        return number_format((float) $num, 0, ',', '.') . '-' . $dv;
    }

    /**
     * @param string $rut
     * @return bool
     */
    public static function isValid($rut)
    {
        $n = self::normalize($rut);
        if (!preg_match('/^(\d{7,8})([0-9K])$/', $n, $m)) {
            return false;
        }
        $num = $m[1];
        $dv = $m[2];
        $sum = 0;
        $mul = 2;
        for ($i = strlen($num) - 1; $i >= 0; $i--) {
            $sum += (int) $num[$i] * $mul;
            $mul = $mul === 7 ? 2 : $mul + 1;
        }
        $res = 11 - ($sum % 11);
        $calc = $res === 11 ? '0' : ($res === 10 ? 'K' : (string) $res);
        return $calc === $dv;
    }

    /**
     * @param string $rut
     * @return string
     */
    public static function requireValid($rut)
    {
        $n = self::normalize($rut);
        if ($n === '') {
            Http::fail('El RUT es obligatorio');
        }
        if (!self::isValid($n)) {
            Http::fail('RUT inválido');
        }
        return self::format($n);
    }
}
