<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';

$user = crm_page_user();
crm_layout_start('Informes y reportes', 'reportes', $user);
?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <h1 class="page-title h3 mb-1">Informes y reportes de gestión</h1>
        <p class="text-secondary mb-0">KPIs de cotizaciones, pipeline, ranking de vendedores y mix de productos/servicios.</p>
    </div>
    <button class="btn btn-outline-secondary" type="button" id="btnCsv">Exportar CSV</button>
</div>

<form class="card card-soft p-3 mb-4" id="filtrosReportes">
    <div class="row g-3 align-items-end">
        <div class="col-md-3">
            <label class="form-label" for="fDesde">Desde</label>
            <input class="form-control" type="date" id="fDesde" name="desde">
        </div>
        <div class="col-md-3">
            <label class="form-label" for="fHasta">Hasta</label>
            <input class="form-control" type="date" id="fHasta" name="hasta">
        </div>
        <div class="col-md-4">
            <label class="form-label" for="fVendedor">Vendedor</label>
            <select class="form-select" id="fVendedor" name="vendedor_id">
                <option value="">Todos</option>
            </select>
        </div>
        <div class="col-md-2">
            <button class="btn w-100" type="submit" style="background:#fec001;color:#05294B;font-weight:700">Aplicar</button>
        </div>
    </div>
</form>

<div class="row g-3 mb-4" id="kpiCards">
    <div class="col-6 col-xl-3">
        <div class="card kpi p-3">
            <div class="kpi-label">Monto cotizado</div>
            <div class="kpi-value" id="kpiCotizado">—</div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card kpi p-3">
            <div class="kpi-label">Ventas ganadas</div>
            <div class="kpi-value" id="kpiGanadas">—</div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card kpi p-3">
            <div class="kpi-label">Conversión</div>
            <div class="kpi-value" id="kpiConversion">—</div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card kpi p-3">
            <div class="kpi-label">Comisiones del período</div>
            <div class="kpi-value" id="kpiComisiones">—</div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-6">
        <div class="card card-soft p-3 h-100">
            <h2 class="h6" style="color:#05294B">Pipeline de ventas por etapa</h2>
            <canvas id="chartPipeline" height="220"></canvas>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card card-soft p-3 h-100">
            <h2 class="h6" style="color:#05294B">Ventas por vendedor</h2>
            <canvas id="chartVendedores" height="220"></canvas>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card card-soft p-3 h-100">
            <h2 class="h6" style="color:#05294B">Proporción productos vs. servicios</h2>
            <canvas id="chartMix" height="220"></canvas>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card card-soft p-3 h-100">
            <h2 class="h6" style="color:#05294B">Top 10 productos y servicios</h2>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0" id="tablaProductos">
                    <thead>
                        <tr>
                            <th>Tipo</th>
                            <th>Código</th>
                            <th>Descripción</th>
                            <th class="text-end">Cant.</th>
                            <th class="text-end">Monto</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="card card-soft p-3">
    <h2 class="h6" style="color:#05294B">Ranking de vendedores</h2>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" id="tablaVendedores">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Vendedor</th>
                    <th>Email</th>
                    <th class="text-end">Cotizado</th>
                    <th class="text-end">Cerrado</th>
                    <th class="text-end">Tasa de cierre</th>
                    <th class="text-end">Comisión</th>
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
  var palette = ["#05294B", "#FEC001", "#0A3A66", "#5B6B7A", "#146C43", "#8B6914"];
  var charts = { pipeline: null, vendedores: null, mix: null };
  var rankingRows = [];
  var topRows = [];

  function ymd(d) {
    var m = String(d.getMonth() + 1);
    var day = String(d.getDate());
    if (m.length < 2) m = "0" + m;
    if (day.length < 2) day = "0" + day;
    return d.getFullYear() + "-" + m + "-" + day;
  }

  function qs() {
    var desde = document.getElementById("fDesde").value;
    var hasta = document.getElementById("fHasta").value;
    var vend = document.getElementById("fVendedor").value;
    var q = "desde=" + encodeURIComponent(desde) + "&hasta=" + encodeURIComponent(hasta);
    if (vend) {
      q += "&vendedor_id=" + encodeURIComponent(vend);
    }
    return q;
  }

  function destroyChart(key) {
    if (charts[key]) {
      charts[key].destroy();
      charts[key] = null;
    }
  }

  function makeChart(key, canvasId, config) {
    destroyChart(key);
    if (typeof Chart === "undefined") {
      crmToast("No se pudo cargar Chart.js", true);
      return;
    }
    var el = document.getElementById(canvasId);
    charts[key] = new Chart(el, config);
  }

  function csvCell(v) {
    var s = String(v == null ? "" : v);
    if (/[",\n;]/.test(s)) {
      return '"' + s.replace(/"/g, '""') + '"';
    }
    return s;
  }

  function loadKpis() {
    return crmApi("api/reportes.php?tipo=resumen_kpis&" + qs()).then(function (d) {
      var k = d.kpis || {};
      document.getElementById("kpiCotizado").textContent = crmClp(k.monto_cotizado);
      document.getElementById("kpiGanadas").textContent = crmClp(k.ventas_ganadas);
      document.getElementById("kpiConversion").textContent = Number(k.conversion_pct || 0).toFixed(2) + "%";
      document.getElementById("kpiComisiones").textContent = crmClp(k.comisiones);
    });
  }

  function loadPipeline() {
    return crmApi("api/reportes.php?tipo=pipeline&" + qs()).then(function (d) {
      var etapas = d.etapas || [];
      makeChart("pipeline", "chartPipeline", {
        type: "bar",
        data: {
          labels: etapas.map(function (e) { return e.label; }),
          datasets: [{
            label: "Monto acumulado",
            data: etapas.map(function (e) { return e.monto; }),
            backgroundColor: etapas.map(function (e) {
              return e.etapa === "ganado" ? yellow : navy;
            }),
            borderRadius: 6
          }]
        },
        options: {
          responsive: true,
          plugins: { legend: { display: false } },
          scales: {
            y: {
              beginAtZero: true,
              ticks: {
                callback: function (v) { return crmClp(v); }
              }
            }
          }
        }
      });
    });
  }

  function loadVendedores() {
    return crmApi("api/reportes.php?tipo=vendedores&" + qs()).then(function (d) {
      rankingRows = d.vendedores || [];
      var tb = document.querySelector("#tablaVendedores tbody");
      tb.innerHTML = rankingRows.map(function (v, i) {
        return "<tr>" +
          "<td>" + (i + 1) + "</td>" +
          "<td>" + v.nombre + "</td>" +
          "<td>" + v.email + "</td>" +
          "<td class=\"text-end\">" + crmClp(v.total_cotizado) + "</td>" +
          "<td class=\"text-end\">" + crmClp(v.total_cerrado) + "</td>" +
          "<td class=\"text-end\">" + Number(v.tasa_cierre_pct || 0).toFixed(2) + "%</td>" +
          "<td class=\"text-end\">" + crmClp(v.comisiones) + "</td>" +
          "</tr>";
      }).join("") || "<tr><td colspan=\"7\" class=\"text-secondary\">Sin vendedores en el período.</td></tr>";

      var metric = rankingRows.some(function (v) { return Number(v.total_cerrado) > 0; })
        ? "total_cerrado"
        : "total_cotizado";
      makeChart("vendedores", "chartVendedores", {
        type: "doughnut",
        data: {
          labels: rankingRows.map(function (v) { return v.nombre; }),
          datasets: [{
            data: rankingRows.map(function (v) { return v[metric]; }),
            backgroundColor: rankingRows.map(function (_, i) { return palette[i % palette.length]; }),
            borderWidth: 1
          }]
        },
        options: {
          responsive: true,
          plugins: {
            legend: { position: "bottom" }
          }
        }
      });
    });
  }

  function loadProductos() {
    return crmApi("api/reportes.php?tipo=productos_top&" + qs()).then(function (d) {
      topRows = d.items || [];
      var tb = document.querySelector("#tablaProductos tbody");
      tb.innerHTML = topRows.map(function (it) {
        return "<tr>" +
          "<td>" + it.tipo_item + "</td>" +
          "<td>" + it.codigo + "</td>" +
          "<td>" + it.descripcion + "</td>" +
          "<td class=\"text-end\">" + it.cantidad + "</td>" +
          "<td class=\"text-end\">" + crmClp(it.monto) + "</td>" +
          "</tr>";
      }).join("") || "<tr><td colspan=\"5\" class=\"text-secondary\">Sin ítems cotizados.</td></tr>";

      var prop = d.proporcion || {};
      var prod = (prop.producto && prop.producto.monto) || 0;
      var serv = (prop.servicio && prop.servicio.monto) || 0;
      makeChart("mix", "chartMix", {
        type: "pie",
        data: {
          labels: ["Productos", "Servicios"],
          datasets: [{
            data: [prod, serv],
            backgroundColor: [navy, yellow],
            borderWidth: 1
          }]
        },
        options: {
          responsive: true,
          plugins: { legend: { position: "bottom" } }
        }
      });
    });
  }

  function cargar() {
    return Promise.all([loadKpis(), loadPipeline(), loadVendedores(), loadProductos()])
      .catch(function (e) { crmToast(e.message, true); });
  }

  document.getElementById("filtrosReportes").addEventListener("submit", function (ev) {
    ev.preventDefault();
    cargar();
  });

  document.getElementById("btnCsv").addEventListener("click", function () {
    var lines = [];
    lines.push(["#", "Vendedor", "Email", "Total cotizado", "Total cerrado", "Tasa de cierre %", "Comision"].map(csvCell).join(";"));
    rankingRows.forEach(function (v, i) {
      lines.push([
        i + 1,
        v.nombre,
        v.email,
        v.total_cotizado,
        v.total_cerrado,
        v.tasa_cierre_pct,
        v.comisiones
      ].map(csvCell).join(";"));
    });
    lines.push("");
    lines.push(["Tipo", "Codigo", "Descripcion", "Cantidad", "Monto"].map(csvCell).join(";"));
    topRows.forEach(function (it) {
      lines.push([it.tipo_item, it.codigo, it.descripcion, it.cantidad, it.monto].map(csvCell).join(";"));
    });
    var blob = new Blob(["\uFEFF" + lines.join("\n")], { type: "text/csv;charset=utf-8;" });
    var a = document.createElement("a");
    a.href = URL.createObjectURL(blob);
    a.download = "reportes-ventas.csv";
    a.click();
    URL.revokeObjectURL(a.href);
  });

  var now = new Date();
  document.getElementById("fDesde").value = ymd(new Date(now.getFullYear(), now.getMonth(), 1));
  document.getElementById("fHasta").value = ymd(now);

  crmApi("api/vendedores.php").then(function (d) {
    var sel = document.getElementById("fVendedor");
    (d.vendedores || []).forEach(function (v) {
      var o = document.createElement("option");
      o.value = v.id;
      o.textContent = v.nombre_completo;
      sel.appendChild(o);
    });
  }).catch(function (e) { crmToast(e.message, true); });

  cargar();
})();
</script>
<?php crm_layout_end(); ?>
