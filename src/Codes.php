<?php

declare(strict_types=1);

namespace Crm;

use PDO;

final class Codes
{
    const COT_INICIO = 354;

    /**
     * Próximo código correlativo. No inserta ni reserva el número.
     *
     * @param string $table
     * @param string $column
     * @param string $prefix
     * @return string
     */
    public static function peek($table, $column, $prefix)
    {
        $allowed = array(
            'crm_oportunidades' => 'codigo',
            'crm_cotizaciones' => 'folio',
        );
        if (!isset($allowed[$table]) || $allowed[$table] !== $column) {
            Http::fail('Generación de código no permitida', 500);
        }
        $year = date('Y');
        $like = $prefix . '-' . $year . '-%';
        $pdo = crm_pdo();
        $sql = 'SELECT ' . $column . ' FROM ' . $table . ' WHERE ' . $column . ' LIKE ?';
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array($like));
        $maxN = 0;
        $ocupados = array();
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $code = isset($row[0]) ? (string) $row[0] : '';
            if (!preg_match('/-(\d+)$/', $code, $m)) {
                continue;
            }
            $num = (int) $m[1];
            $ocupados[$num] = true;
            if ($num > $maxN) {
                $maxN = $num;
            }
        }
        $n = $maxN + 1;
        if ($n < 1) {
            $n = 1;
        }
        $piso = self::piso($prefix);
        if ($n < $piso) {
            $n = $piso;
        }
        $guard = 0;
        while (isset($ocupados[$n]) && $guard < 100000) {
            $n++;
            $guard++;
        }
        return $prefix . '-' . $year . '-' . str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Piso del correlativo automático. COT parte en 354; números menores siguen libres para histórico.
     *
     * @param string $prefix
     * @return int
     */
    public static function piso($prefix)
    {
        $prefix = strtoupper(trim((string) $prefix));
        if ($prefix !== 'COT') {
            return 1;
        }
        $piso = self::COT_INICIO;
        try {
            $stmt = crm_pdo()->prepare('SELECT siguiente FROM crm_secuencias WHERE codigo = ? LIMIT 1');
            $stmt->execute(array($prefix));
            $v = $stmt->fetchColumn();
            if ($v !== false && (int) $v > 0) {
                $piso = (int) $v;
            }
        } catch (\PDOException $e) {
            $piso = self::COT_INICIO;
        }
        return $piso;
    }

    /**
     * @param string $table
     * @param string $column
     * @param string $prefix
     * @return string
     */
    public static function next($table, $column, $prefix)
    {
        return self::peek($table, $column, $prefix);
    }

    /**
     * @param PDO $pdo
     * @param int $id
     * @return array
     */
    public static function requireEmpresa(PDO $pdo, $id)
    {
        $id = (int) $id;
        if ($id <= 0) {
            Http::fail('Empresa inválida');
        }
        $stmt = $pdo->prepare('SELECT * FROM crm_empresas WHERE id = ? LIMIT 1');
        $stmt->execute(array($id));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            Http::fail('Empresa no encontrada', 404);
        }
        return $row;
    }
}
