<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';

$health = \Crm\Comex\Health::payload();
$inv = $health['inventory'];
$uploads = $health['uploads'];
$phpBadge = $health['php_is_81'] ? 'PHP 8.1 OK' : ('PHP ' . $health['php']);
$invBadge = !empty($inv['connected']) ? 'Inventario conectado' : 'Inventario pendiente';

crm_layout_start('Dashboard', 'dashboard');
?>
<div class="d-flex justify-content-between align-items-start mb-4">
    <div>
        <h1 class="page-title h3 mb-1">COMEX LPAEZsis</h1>
        <p class="text-secondary mb-0">Landed cost Chile · crm.lpaezsis.cl · inventario.lpaezsis.cl</p>
    </div>
    <a class="btn btn-yellow" href="landed.php">Evaluar operación</a>
</div>
<div class="row g-3">
    <div class="col-6 col-xl-3">
        <div class="card kpi p-3">
            <div class="kpi-label">Runtime</div>
            <div class="kpi-value"><?php echo $health['php_ok'] ? crm_h($phpBadge) : 'PHP N/A'; ?></div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card kpi p-3">
            <div class="kpi-label">Inventario</div>
            <div class="kpi-value"><?php echo crm_h($invBadge); ?></div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card kpi p-3">
            <div class="kpi-label">uploads/</div>
            <div class="kpi-value"><?php echo crm_h((string) ($uploads['perm'] ?: 'n/d')); ?></div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card kpi p-3">
            <div class="kpi-label">IVA aduanero</div>
            <div class="kpi-value"><?php echo crm_h((string) crm_iva_pct()); ?>%</div>
        </div>
    </div>
</div>
<div class="card card-soft p-3 mt-3">
    <h2 class="h6" style="color:#05294B">Módulos</h2>
    <ul class="mb-0">
        <li><a href="landed.php">Landed cost</a> — prorrateo FOB, CIF, IVA 19%, Estimada vs Real</li>
        <li><a href="operaciones.php">Pipeline</a> — 13 etapas Evaluación / Ejecución, Kanban y lista</li>
        <li><a href="operaciones.php">Finanzas / landed cost</a> — matriz Estimación vs Real, XLSX y PDF en el detalle</li>
        <li><a href="fichas.php">Fichas</a> — SKU vinculados a prod.db</li>
        <li><a href="api/health.php"><code>api/health.php</code></a></li>
    </ul>
</div>
<?php crm_layout_end(); ?>
