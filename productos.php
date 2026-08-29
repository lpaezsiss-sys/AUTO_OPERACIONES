<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';

$user = crm_page_user();
crm_layout_start('Inventario', 'productos', $user);
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="page-title h3 mb-1">Inventario (solo lectura)</h1>
        <p class="text-secondary mb-0">Stock y precio se leen de la tabla <code>productos</code>. El CRM no modifica inventario existente. <a href="estadisticas_a_pedido.php">Estadísticas a pedido</a></p>
    </div>
    <div class="form-check">
        <input class="form-check-input" type="checkbox" id="bajo">
        <label class="form-check-label" for="bajo">Solo bajo stock</label>
    </div>
</div>
<div class="card card-soft p-3">
    <input id="q" class="form-control mb-3" placeholder="Buscar código o nombre">
    <div class="table-responsive">
        <table class="table align-middle">
            <thead><tr><th>Código</th><th>Nombre</th><th>Stock</th><th>Umbral</th><th class="text-end">Precio lista</th></tr></thead>
            <tbody id="rows"></tbody>
        </table>
    </div>
</div>
<script>
function load() {
  var q = document.getElementById("q").value;
  var bajo = document.getElementById("bajo").checked ? "1" : "0";
  crmApi("api/productos.php?q="+encodeURIComponent(q)+"&bajo_stock="+bajo).then(function (d) {
    document.getElementById("rows").innerHTML = (d.productos||[]).map(function (p) {
      var cls = p.bajo_stock ? "low" : "";
      return '<tr><td>'+p.codigo+'</td><td>'+p.nombre+'</td><td><span class="badge badge-stock '+cls+'">'+p.stock+' '+p.unidad+'</span></td><td>'+p.umbral_stock+'</td><td class="text-end">'+crmClp(p.precio_unitario)+'</td></tr>';
    }).join("");
  }).catch(function (e) { crmToast(e.message, true); });
}
document.getElementById("q").addEventListener("input", load);
document.getElementById("bajo").addEventListener("change", load);
load();
</script>
<?php crm_layout_end(); ?>
