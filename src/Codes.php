<?php

declare(strict_types=1);

namespace Crm;

use PDO;

final class Codes
{
    /**
     * @param string $table
     * @param string $column
     * @param string $prefix
     * @return string
     */
    public static function next($table, $column, $prefix)
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
        $sql = 'SELECT ' . $column . ' FROM ' . $table . ' WHERE ' . $column . ' LIKE ? ORDER BY ' . $column . ' DESC LIMIT 1';
        $stmt = crm_pdo()->prepare($sql);
        $stmt->execute(array($like));
        $last = $stmt->fetchColumn();
        $n = 1;
        if (is_string($last) && preg_match('/-(\d+)$/', $last, $m)) {
            $n = ((int) $m[1]) + 1;
        }
        return $prefix . '-' . $year . '-' . str_pad((string) $n, 4, '0', STR_PAD_LEFT);
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
