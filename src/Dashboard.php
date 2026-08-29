<?php

declare(strict_types=1);

namespace Crm;

use PDO;

final class Dashboard
{
    /**
     * @return array
     */
    public static function stats()
    {
        $pdo = crm_pdo();
        $empresas = (int) $pdo->query('SELECT COUNT(*) FROM crm_empresas')->fetchColumn();
        $contactos = (int) $pdo->query('SELECT COUNT(*) FROM crm_contactos WHERE activo = 1')->fetchColumn();
        $opAbiertas = (int) $pdo->query(
            "SELECT COUNT(*) FROM crm_oportunidades WHERE etapa NOT IN ('ganada','perdida')"
        )->fetchColumn();
        $pipeline = (float) $pdo->query(
            "SELECT COALESCE(SUM(valor_estimado),0) FROM crm_oportunidades WHERE etapa NOT IN ('ganada','perdida')"
        )->fetchColumn();
        $mesStmt = $pdo->prepare('SELECT COUNT(*) FROM crm_cotizaciones WHERE fecha_emision LIKE ?');
        $mesStmt->execute(array(date('Y-m') . '%'));
        $cotsMes = (int) $mesStmt->fetchColumn();
        $pendientes = (int) $pdo->query(
            "SELECT COUNT(*) FROM crm_actividades WHERE estado = 'pendiente'"
        )->fetchColumn();
        $bajoStock = (int) $pdo->query(
            'SELECT COUNT(*) FROM productos WHERE activo = 1 AND stock <= umbral_stock'
        )->fetchColumn();

        $porEtapa = $pdo->query(
            'SELECT etapa, COUNT(*) AS cantidad, COALESCE(SUM(valor_estimado),0) AS valor
             FROM crm_oportunidades GROUP BY etapa'
        )->fetchAll(PDO::FETCH_ASSOC);

        $porCanal = $pdo->query(
            'SELECT canal, COUNT(*) AS cantidad
             FROM crm_actividades GROUP BY canal ORDER BY cantidad DESC'
        )->fetchAll(PDO::FETCH_ASSOC);

        $recientes = $pdo->query(
            'SELECT a.id, a.titulo, a.tipo, a.canal, a.estado, a.fecha_programada, a.created_at, e.razon_social
             FROM crm_actividades a
             LEFT JOIN crm_empresas e ON e.id = a.empresa_id
             ORDER BY a.created_at DESC LIMIT 8'
        )->fetchAll(PDO::FETCH_ASSOC);

        $cots = $pdo->query(
            'SELECT c.id, c.folio, c.estado, c.total, c.fecha_emision, e.razon_social
             FROM crm_cotizaciones c
             INNER JOIN crm_empresas e ON e.id = c.empresa_id
             ORDER BY c.created_at DESC LIMIT 6'
        )->fetchAll(PDO::FETCH_ASSOC);

        return array(
            'kpis' => array(
                'empresas' => $empresas,
                'contactos' => $contactos,
                'oportunidades_abiertas' => $opAbiertas,
                'pipeline_clp' => $pipeline,
                'cotizaciones_mes' => $cotsMes,
                'actividades_pendientes' => $pendientes,
                'productos_bajo_stock' => $bajoStock,
            ),
            'pipeline' => $porEtapa,
            'canales' => $porCanal,
            'actividades_recientes' => $recientes,
            'cotizaciones_recientes' => $cots,
        );
    }
}
