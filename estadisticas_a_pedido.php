<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';

$user = crm_page_user();
crm_layout_start('Estadísticas a pedido', 'estadisticas_a_pedido', $user);
?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <h1 class="page-title h3 mb-1">Estadísticas de productos a pedido</h1>
        <p class="text-secondary mb-0">Demanda de ítems fuera de inventario, por marca y sugerencia de alta a catálogo (stock inicial 0).</p>
    </div>
</div>

<form class="card card-soft p-3 mb-4" id="filtrosPedido">
    <div class="row g-3 align-items-end">
        <div class="col-md-2">
            <label class="form-label" for="fPeriodo">Período</label>
            <select class="form-select" id="fPeriodo">
                <option value="mes">Mes actual</option>
                <option value="trimestre">Trimestre</option>
                <option value="anio">Año</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label" for="fMarca">Marca</label>
            <select class="form-select" id="fMarca">
                <option value="">Todas</option>
            </select>
        </div>
        <div class="col-md-2">
            <button class="btn w-100" type="submit" style="background:#fec001;color:#05294B;font-weight:700">Aplicar</button>
        </div>
    </div>
</form>

<div class="row g-3 mb-4">
    <div class="col-6 col-xl-3">
        <div class="card kpi p-3">
            <div class="kpi-label">Ítems cotizados</div>
            <div class="kpi-value" id="kpiNCot">—</div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card kpi p-3">
            <div class="kpi-label">Ítems ganados</div>
            <div class="kpi-value" id="kpiNGan">—</div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card kpi p-3">
            <div class="kpi-label">Monto cotizado</div>
            <div class="kpi-value" id="kpiMontoCot">—</div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card kpi p-3">
            <div class="kpi-label">Ventas efectivas</div>
            <div class="kpi-value" id="kpiMontoGan">—</div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card kpi p-3">
            <div class="kpi-label">Conversión</div>
            <div class="kpi-value" id="kpiConv">—</div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card kpi p-3">
            <div class="kpi-label">Margen promedio</div>
            <div class="kpi-value" id="kpiMargen">—</div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-6">
        <div class="card card-soft p-3 h-100">
            <h2 class="h6" style="color:#05294B">Cotizado vs ganado por marca</h2>
            <canvas id="chartMarcas" height="240"></canvas>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card card-soft p-3 h-100">
            <h2 class="h6" style="color:#05294B">Marcas (catálogo vs texto libre)</h2>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0" id="tablaMarcas">
                    <thead>
                        <tr>
                            <th>Marca</th>
                            <th>Origen</th>
                            <th class="text-end">Cotizado</th>
                            <th class="text-end">Ganado</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="card card-soft p-3 mb-4">
    <h2 class="h6" style="color:#05294B">Top productos a pedido</h2>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0" id="tablaTop">
            <thead>
                <tr>
                    <th>Descripción</th>
                    <th>Marca</th>
                    <th class="text-end">Veces</th>
                    <th class="text-end">Cant.</th>
                    <th class="text-end">Cotizado</th>
                    <th class="text-end">Ganado</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>

<div class="card card-soft p-3">
    <h2 class="h6" style="color:#05294B">Sugerencia de alta a catálogo</h2>
    <p class="small text-secondary">Productos a pedido con 2 o más apariciones. El alta crea un SKU en <code>productos</code> con stock 0 (no modifica stock existente).</p>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0" id="tablaSugerencias">
            <thead>
                <tr>
                    <th>Descripción</th>
                    <th>Marca</th>
                    <th class="text-end">Frecuencia</th>
                    <th></th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function () {
  "use strict";
  var navy = "#05294B";
  var yellow = "#FEC001";
  var chartMarcas = null;

  function qs() {
    var periodo = document.getElementById("fPeriodo").value;
    var marca = document.getElementById("fMarca").value;
    var q = "periodo=" + encodeURIComponent(periodo);
    if (marca) q += "&marca=" + encodeURIComponent(marca);
    return q;
  }

  function load() {
    crmApi("api/estadisticas_a_pedido.php?" + qs()).then(function (d) {
      var k = d.kpis || {};
      document.getElementById("kpiNCot").textContent = k.n_cotizados != null ? k.n_cotizados : "0";
      document.getElementById("kpiNGan").textContent = k.n_ganados != null ? k.n_ganados : "0";
      document.getElementById("kpiMontoCot").textContent = crmClp(k.monto_cotizado || 0);
      document.getElementById("kpiMontoGan").textContent = crmClp(k.monto_ganado || 0);
      document.getElementById("kpiConv").textContent = (k.conversion_pct != null ? k.conversion_pct : 0) + "%";
      document.getElementById("kpiMargen").textContent = k.margen_pct == null ? "s/costo" : (k.margen_pct + "%");

      var sel = document.getElementById("fMarca");
      var current = sel.value;
      sel.innerHTML = '<option value="">Todas</option>' + (d.marcas || []).map(function (m) {
        return '<option value="' + m + '">' + m + '</option>';
      }).join("");
      if (current) sel.value = current;

      var por = d.por_marca || [];
      document.querySelector("#tablaMarcas tbody").innerHTML = por.map(function (r) {
        return '<tr><td>' + r.marca + '</td><td>' + (Number(r.en_catalogo) === 1 ? "Catálogo" : "Texto libre") +
          '</td><td class="text-end">' + crmClp(r.monto_cotizado) + '</td><td class="text-end">' + crmClp(r.monto_ganado) + '</td></tr>';
      }).join("") || '<tr><td colspan="4" class="text-secondary">Sin ítems a pedido en el período.</td></tr>';

      document.querySelector("#tablaTop tbody").innerHTML = (d.top || []).map(function (r) {
        return '<tr><td>' + r.descripcion + '</td><td>' + r.marca + '</td><td class="text-end">' + r.veces +
          '</td><td class="text-end">' + r.cantidad + '</td><td class="text-end">' + crmClp(r.monto_cotizado) +
          '</td><td class="text-end">' + crmClp(r.monto_ganado) + '</td></tr>';
      }).join("") || '<tr><td colspan="6" class="text-secondary">Sin datos.</td></tr>';

      document.querySelector("#tablaSugerencias tbody").innerHTML = (d.sugerencias || []).map(function (r, idx) {
        var btn = Number(r.ya_en_catalogo) === 1
          ? '<span class="small text-secondary">Ya en inventario</span>'
          : '<button class="btn btn-sm btn-outline-primary" type="button" data-sug="' + idx + '">Convertir en producto</button>';
        return '<tr><td>' + r.descripcion + '</td><td>' + r.marca + '</td><td class="text-end">' + r.veces + '</td><td>' + btn + '</td></tr>';
      }).join("") || '<tr><td colspan="4" class="text-secondary">Aún no hay recurrencia suficiente (mínimo 2 cotizaciones).</td></tr>';
      window._sugRows = d.sugerencias || [];

      if (chartMarcas) chartMarcas.destroy();
      var ctx = document.getElementById("chartMarcas");
      chartMarcas = new Chart(ctx, {
        type: "bar",
        data: {
          labels: por.map(function (r) { return r.marca; }),
          datasets: [
            { label: "Cotizado", data: por.map(function (r) { return r.monto_cotizado; }), backgroundColor: navy },
            { label: "Ganado", data: por.map(function (r) { return r.monto_ganado; }), backgroundColor: yellow }
          ]
        },
        options: {
          responsive: true,
          plugins: { legend: { position: "bottom" } },
          scales: { y: { beginAtZero: true } }
        }
      });
    }).catch(function (e) { crmToast(e.message, true); });
  }

  document.getElementById("filtrosPedido").addEventListener("submit", function (ev) {
    ev.preventDefault();
    load();
  });
  document.getElementById("tablaSugerencias").addEventListener("click", function (ev) {
    var idx = ev.target.getAttribute("data-sug");
    if (idx == null) return;
    var r = (window._sugRows || [])[Number(idx)];
    if (!r) return;
    if (!window.confirm("¿Crear SKU en inventario con stock 0 para «" + r.descripcion + "»?")) return;
    crmApi("api/estadisticas_a_pedido.php", {
      method: "POST",
      body: {
        action: "convertir_inventario",
        nombre: r.descripcion,
        descripcion: r.descripcion,
        marca_nombre: r.marca === "Sin marca" ? "" : r.marca,
        marca_id: r.marca_id || 0,
        precio_unitario: r.precio_promedio || 0
      }
    }).then(function (d) {
      crmToast("Alta " + d.producto.codigo + " con stock 0");
      load();
    }).catch(function (e) { crmToast(e.message, true); });
  });
  load();
})();
</script>
<?php crm_layout_end(); ?>
