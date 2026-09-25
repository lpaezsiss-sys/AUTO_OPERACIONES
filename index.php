<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
\Auth::requireLogin();
require __DIR__ . '/includes/layout.php';

$health = \Crm\Comex\Health::payload();
$inv = $health['inventory'];
$uploads = $health['uploads'];
$phpBadge = $health['php_is_81'] ? 'PHP 8.1 OK' : ('PHP ' . $health['php']);
$invBadge = !empty($inv['connected']) ? 'Inventario conectado' : 'Inventario pendiente';

try {
    $dash = \Crm\Comex\Dashboard::kpis();
} catch (\Throwable) {
    $dash = [
        'operaciones_activas' => 0,
        'operaciones_cerradas' => 0,
        'operaciones_totales' => 0,
        'ciclo_promedio_dias' => null,
        'ciclo_fuente' => 'n/d',
        'ciclo_muestra' => 0,
        'costo_promedio_embarque_clp' => 0,
        'costo_muestra' => 0,
        'alertas_retraso' => 0,
        'bloqueadas' => 0,
        'volumen_mensual' => [],
    ];
}

$ciclo = $dash['ciclo_promedio_dias'];
$cicloTxt = $ciclo === null ? '—' : (rtrim(rtrim(number_format((float) $ciclo, 1, ',', '.'), '0'), ',') ?: '0');
$cicloHint = ($dash['ciclo_fuente'] ?? '') === 'cierre'
    ? 'Cierre operativo (CIERRE completado)'
    : (($dash['ciclo_fuente'] ?? '') === 'abiertas' ? 'Días transcurridos en operaciones abiertas' : 'Sin operaciones');
$costo = (float) ($dash['costo_promedio_embarque_clp'] ?? 0);
$alertas = (int) ($dash['alertas_retraso'] ?? 0);

crm_layout_start('Dashboard', 'dashboard');
?>
<div class="d-flex justify-content-between align-items-start mb-4">
    <div>
        <h1 class="page-title h3 mb-1">Dashboard principal</h1>
        <p class="text-secondary mb-0">Operaciones COMEX · landed cost Chile · crm.lpaezsis.cl</p>
    </div>
    <a class="btn btn-yellow" href="operaciones.php">Ver pipeline</a>
</div>
<div class="row g-3 mb-3">
    <div class="col-6 col-xl-3">
        <div class="card kpi p-3">
            <div class="kpi-label">Operaciones activas</div>
            <div class="kpi-value" id="kpiActivas"><?php echo (int) $dash['operaciones_activas']; ?></div>
            <div class="small text-secondary"><?php echo (int) $dash['operaciones_totales']; ?> en total · <?php echo (int) $dash['operaciones_cerradas']; ?> cerradas</div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card kpi p-3">
            <div class="kpi-label">Tiempo promedio de ciclo (días)</div>
            <div class="kpi-value" id="kpiCiclo"><?php echo crm_h((string) $cicloTxt); ?></div>
            <div class="small text-secondary"><?php echo crm_h($cicloHint); ?></div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card kpi p-3">
            <div class="kpi-label">Costo promedio por embarque</div>
            <div class="kpi-value" id="kpiCosto"><?php echo $costo > 0 ? crm_h('$' . number_format($costo, 0, ',', '.')) : '—'; ?></div>
            <div class="small text-secondary">Landed REAL, o ESTIMADA si no hay real</div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card kpi p-3 <?php echo $alertas > 0 ? 'kpi-alert' : ''; ?>">
            <div class="kpi-label">Alertas de retraso</div>
            <div class="kpi-value" id="kpiAlertas"><?php echo $alertas; ?></div>
            <div class="small text-secondary">Operaciones con etapa atrasada</div>
        </div>
    </div>
</div>
<div class="card card-soft p-3 mb-3">
    <h2 class="h6 mb-2" style="color:#05294B">Volumen de operaciones por mes</h2>
    <p class="small text-secondary mb-2">Últimos 12 meses · gráfico cartesiano (equivalente Recharts, SVG nativo)</p>
    <div id="chartVolumen" class="dash-chart" role="img" aria-label="Volumen de operaciones por mes"></div>
</div>
<div class="row g-3">
    <div class="col-6 col-xl-3">
        <div class="card kpi p-3">
            <div class="kpi-label">Runtime</div>
            <div class="kpi-value" style="font-size:1.05rem"><?php echo $health['php_ok'] ? crm_h($phpBadge) : 'PHP N/A'; ?></div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card kpi p-3">
            <div class="kpi-label">Inventario</div>
            <div class="kpi-value" style="font-size:1.05rem"><?php echo crm_h($invBadge); ?></div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card kpi p-3">
            <div class="kpi-label">uploads/</div>
            <div class="kpi-value" style="font-size:1.05rem"><?php echo crm_h((string) ($uploads['perm'] ?: 'n/d')); ?></div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card kpi p-3">
            <div class="kpi-label">IVA aduanero</div>
            <div class="kpi-value" style="font-size:1.05rem"><?php echo crm_h((string) crm_iva_pct()); ?>%</div>
        </div>
    </div>
</div>
<script>
window.COMEX_DASH = <?php echo json_encode($dash, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
</script>
<script src="assets/js/dashboard-chart.js"></script>
<script src="assets/js/dashboard.js"></script>
<?php crm_layout_end(); ?>
