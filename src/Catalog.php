<?php

declare(strict_types=1);

namespace Crm;

final class Catalog
{
    /**
     * @return string[]
     */
    public static function regiones()
    {
        return array(
            'Arica y Parinacota',
            'Tarapacá',
            'Antofagasta',
            'Atacama',
            'Coquimbo',
            'Valparaíso',
            'Metropolitana de Santiago',
            "Libertador General Bernardo O'Higgins",
            'Maule',
            'Ñuble',
            'Biobío',
            'La Araucanía',
            'Los Ríos',
            'Los Lagos',
            'Aysén',
            'Magallanes y la Antártica Chilena',
        );
    }

    /**
     * @return string[]
     */
    public static function industrias()
    {
        return array(
            'Alimentos',
            'Bebidas',
            'Agroindustria',
            'Envasado y embalaje',
            'Packaging',
            'Farmacéutica',
            'Química',
            'Minería',
            'Logística',
            'Otro',
        );
    }

    /**
     * @return string[]
     */
    public static function origenes()
    {
        return array('web', 'whatsapp', 'telefono', 'email', 'linkedin', 'feria', 'visita', 'referido');
    }

    /**
     * @return string[]
     */
    public static function canales()
    {
        return array('whatsapp', 'email', 'telefono', 'web', 'linkedin', 'visita', 'feria');
    }

    /**
     * @return string[]
     */
    public static function etapas()
    {
        return array('prospecto', 'calificacion', 'propuesta', 'negociacion', 'ganada', 'perdida');
    }

    /**
     * @return string[]
     */
    public static function cotizacionEstados()
    {
        return array('borrador', 'enviada', 'aceptada', 'rechazada', 'vencida');
    }

    /**
     * @return string[]
     */
    public static function itemTipos()
    {
        return array('producto', 'servicio');
    }

    /**
     * @return string[]
     */
    public static function monedas()
    {
        return array('CLP', 'USD', 'UF', 'EUR');
    }

    /**
     * @return string[]
     */
    public static function actividadTipos()
    {
        return array('llamada', 'reunion', 'correo', 'nota', 'tarea', 'email', 'whatsapp', 'visita');
    }

    /**
     * @return string[]
     */
    public static function actividadEstados()
    {
        return array('pendiente', 'realizada', 'completada', 'cancelada');
    }

    /**
     * @param string $value
     * @param string[] $allowed
     * @param string $field
     * @return string
     */
    public static function inList($value, array $allowed, $field)
    {
        $value = crm_lower(trim((string) $value));
        if (!in_array($value, $allowed, true)) {
            Http::fail('Valor inválido para ' . $field);
        }
        return $value;
    }

    /**
     * @return array
     */
    public static function all()
    {
        return array(
            'regiones' => self::regiones(),
            'industrias' => self::industrias(),
            'origenes' => self::origenes(),
            'canales' => self::canales(),
            'etapas' => self::etapas(),
            'cotizacion_estados' => self::cotizacionEstados(),
            'item_tipos' => self::itemTipos(),
            'monedas' => self::monedas(),
            'actividad_tipos' => self::actividadTipos(),
            'actividad_estados' => self::actividadEstados(),
            'iva_pct' => crm_iva_pct(),
        );
    }
}
