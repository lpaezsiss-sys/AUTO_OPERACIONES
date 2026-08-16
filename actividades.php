<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';

$user = crm_page_user();
crm_layout_start('Agenda y seguimiento', 'actividades', $user);
?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <h1 class="page-title h3 mb-1">Agenda y seguimiento de postventa</h1>
        <p class="text-secondary mb-0">Compromisos del vendedor: llamadas, reuniones y tareas vinculadas a cotizaciones.</p>
    </div>
    <button class="btn" style="background:#fec001;color:#05294B;font-weight:700" data-bs-toggle="modal" data-bs-target="#modalAct" type="button" id="btnNueva">Programar seguimiento</button>
</div>

<div class="row g-3 mb-4" id="resumenAgenda">
    <div class="col-6 col-xl-3"><div class="card kpi p-3"><div class="kpi-label">Pendientes</div><div class="kpi-value" id="kpiPendientes">—</div></div></div>
    <div class="col-6 col-xl-3"><div class="card kpi p-3"><div class="kpi-label">Vencidas</div><div class="kpi-value text-danger" id="kpiVencidas">—</div></div></div>
    <div class="col-6 col-xl-3"><div class="card kpi p-3"><div class="kpi-label">Para hoy</div><div class="kpi-value" id="kpiHoy">—</div></div></div>
    <div class="col-6 col-xl-3"><div class="card kpi p-3"><div class="kpi-label">Realizadas</div><div class="kpi-value" id="kpiRealizadas">—</div></div></div>
</div>

<form class="card card-soft p-3 mb-4" id="filtrosAgenda">
    <div class="row g-2 align-items-end">
        <div class="col-md-3">
            <label class="form-label" for="fVendedor">Vendedor</label>
            <select class="form-select" id="fVendedor">
                <option value="">Todos</option>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label" for="fEstado">Estado</label>
            <select class="form-select" id="fEstado">
                <option value="">Todos</option>
                <option value="pendiente" selected>Pendiente</option>
                <option value="realizada">Realizada</option>
                <option value="cancelada">Cancelada</option>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label" for="fDesde">Desde</label>
            <input class="form-control" type="date" id="fDesde">
        </div>
        <div class="col-md-2">
            <label class="form-label" for="fHasta">Hasta</label>
            <input class="form-control" type="date" id="fHasta">
        </div>
        <div class="col-md-3">
            <button class="btn w-100" type="submit" style="background:#05294B;color:#fff">Filtrar</button>
        </div>
    </div>
</form>

<h2 class="h6" style="color:#05294B">Compromisos pendientes</h2>
<div class="row g-3 mb-4" id="cardsPendientes"></div>

<div class="card card-soft p-3">
    <h2 class="h6" style="color:#05294B">Listado de actividades</h2>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" id="tablaAct">
            <thead>
                <tr>
                    <th>Cuando</th>
                    <th>Actividad</th>
                    <th>Tipo</th>
                    <th>Empresa / Cotización</th>
                    <th>Vendedor</th>
                    <th>Estado</th>
                    <th></th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="modalAct" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content" id="formAct">
      <div class="modal-header">
        <h5 class="modal-title">Programar seguimiento</h5>
        <button class="btn-close" data-bs-dismiss="modal" type="button"></button>
      </div>
      <div class="modal-body row g-2">
        <div class="col-12">
          <div class="btn-group w-100" role="group">
            <input type="radio" class="btn-check" name="tipo" id="tipoLlamada" value="llamada" checked>
            <label class="btn btn-outline-secondary" for="tipoLlamada">Llamada</label>
            <input type="radio" class="btn-check" name="tipo" id="tipoReunion" value="reunion">
            <label class="btn btn-outline-secondary" for="tipoReunion">Reunión</label>
            <input type="radio" class="btn-check" name="tipo" id="tipoTarea" value="tarea">
            <label class="btn btn-outline-secondary" for="tipoTarea">Tarea</label>
          </div>
        </div>
        <div class="col-12">
          <label class="form-label">Título</label>
          <input class="form-control" name="titulo" id="actTitulo" required placeholder="Ej. Llamada de postventa">
        </div>
        <div class="col-12">
          <label class="form-label">Cotización</label>
          <select class="form-select" name="cotizacion_id" id="selCotizacion">
            <option value="">(sin vincular)</option>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label">Empresa</label>
          <select class="form-select" name="empresa_id" id="selEmpresa">
            <option value="">(opcional)</option>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label">Vendedor</label>
          <select class="form-select" name="vendedor_id" id="selVendedor">
            <option value="">Yo / asignado</option>
          </select>
        </div>
        <div class="col-12">
          <label class="form-label">Fecha y hora</label>
          <input class="form-control" type="datetime-local" name="fecha_programada" id="actFecha" required>
        </div>
        <div class="col-12">
          <label class="form-label">Descripción</label>
          <textarea class="form-control" name="descripcion" rows="2" placeholder="Notas del compromiso"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn" type="submit" style="background:#fec001;color:#05294B;font-weight:700">Guardar</button>
      </div>
    </form>
  </div>
</div>
<script>
(function () {
  var cotizaciones = [];

  function qs() {
    var v = document.getElementById("fVendedor").value;
    var e = document.getElementById("fEstado").value;
    var d = document.getElementById("fDesde").value;
    var h = document.getElementById("fHasta").value;
    var q = "estado=" + encodeURIComponent(e);
    if (v) q += "&vendedor_id=" + encodeURIComponent(v);
    if (d) q += "&desde=" + encodeURIComponent(d);
    if (h) q += "&hasta=" + encodeURIComponent(h);
    return q;
  }

  function badgeAgenda(a) {
    if (a.vencida) return '<span class="badge text-bg-danger">Vencida</span>';
    if (a.es_hoy) return '<span class="badge" style="background:#fec001;color:#05294B">Hoy</span>';
    if (a.estado === "pendiente") return '<span class="badge text-bg-secondary">Programada</span>';
    if (a.estado === "realizada") return '<span class="badge text-bg-success">Realizada</span>';
    return '<span class="badge text-bg-light">' + a.estado + '</span>';
  }

  function load() {
    crmApi("api/actividades.php?" + qs()).then(function (d) {
      var r = d.resumen || {};
      document.getElementById("kpiPendientes").textContent = r.pendientes || 0;
      document.getElementById("kpiVencidas").textContent = r.vencidas || 0;
      document.getElementById("kpiHoy").textContent = r.hoy || 0;
      document.getElementById("kpiRealizadas").textContent = r.realizadas || 0;

      var pend = (d.actividades || []).filter(function (a) { return a.estado === "pendiente"; });
      var cards = document.getElementById("cardsPendientes");
      cards.innerHTML = pend.map(function (a) {
        var cls = "act-card card card-soft p-3 h-100";
        if (a.vencida) cls += " vencida";
        else if (a.es_hoy) cls += " hoy";
        return '<div class="col-md-6 col-xl-4"><div class="'+cls+'">' +
          '<div class="d-flex justify-content-between align-items-start mb-2">' +
            '<strong>'+a.titulo+'</strong>' + badgeAgenda(a) +
          '</div>' +
          '<div class="small text-secondary mb-2">'+(a.tipo||"")+' · '+(a.fecha_programada||"sin fecha")+'</div>' +
          '<div class="small mb-2">'+(a.razon_social||"Sin empresa")+(a.cotizacion_folio ? " · "+a.cotizacion_folio : "")+'</div>' +
          '<button class="btn btn-sm btn-outline-success" data-completar="'+a.id+'">Marcar realizada</button>' +
          '</div></div>';
      }).join("") || '<div class="col-12 text-secondary">Sin compromisos pendientes en el filtro.</div>';

      var tb = document.querySelector("#tablaAct tbody");
      tb.innerHTML = (d.actividades || []).map(function (a) {
        var btn = a.estado === "pendiente"
          ? '<button class="btn btn-sm btn-outline-success" data-completar="'+a.id+'">Completar</button>'
          : "";
        return "<tr>" +
          "<td>"+(a.fecha_programada||a.creado_en||"")+"</td>" +
          "<td>"+a.titulo+"</td>" +
          "<td>"+a.tipo+"</td>" +
          "<td>"+(a.razon_social||"—")+(a.cotizacion_folio ? "<div class='small text-secondary'>"+a.cotizacion_folio+"</div>" : "")+"</td>" +
          "<td>"+(a.vendedor_nombre||a.usuario_nombre||"—")+"</td>" +
          "<td>"+badgeAgenda(a)+"</td>" +
          "<td>"+btn+"</td>" +
          "</tr>";
      }).join("") || '<tr><td colspan="7" class="text-secondary">Sin actividades.</td></tr>';
    }).catch(function (e) { crmToast(e.message, true); });
  }

  function completar(id) {
    crmApi("api/actividades.php?action=completar&id="+encodeURIComponent(id), { method: "POST", body: { id: Number(id) } })
      .then(function () { crmToast("Actividad realizada"); load(); })
      .catch(function (e) { crmToast(e.message, true); });
  }

  document.getElementById("cardsPendientes").addEventListener("click", function (ev) {
    var id = ev.target.getAttribute("data-completar");
    if (id) completar(id);
  });
  document.querySelector("#tablaAct tbody").addEventListener("click", function (ev) {
    var id = ev.target.getAttribute("data-completar");
    if (id) completar(id);
  });

  document.getElementById("filtrosAgenda").addEventListener("submit", function (ev) {
    ev.preventDefault();
    load();
  });

  document.getElementById("selCotizacion").addEventListener("change", function () {
    var id = this.value;
    var found = cotizaciones.filter(function (c) { return String(c.id) === String(id); })[0];
    if (found && found.empresa_id) {
      document.getElementById("selEmpresa").value = found.empresa_id;
    }
  });

  document.getElementById("formAct").addEventListener("submit", function (ev) {
    ev.preventDefault();
    var body = crmForm("formAct");
    if (body.fecha_programada) body.fecha_programada = body.fecha_programada.replace("T", " ") + ":00";
    crmApi("api/actividades.php?action=crear", { method: "POST", body: body })
      .then(function () {
        bootstrap.Modal.getInstance(document.getElementById("modalAct")).hide();
        document.getElementById("formAct").reset();
        document.getElementById("tipoLlamada").checked = true;
        load();
        crmToast("Seguimiento programado");
      })
      .catch(function (e) { crmToast(e.message, true); });
  });

  Promise.all([
    crmApi("api/empresas.php"),
    crmApi("api/vendedores.php"),
    crmApi("api/cotizaciones.php")
  ]).then(function (arr) {
    document.getElementById("selEmpresa").innerHTML = '<option value="">(opcional)</option>' +
      (arr[0].empresas || []).map(function (e) { return '<option value="'+e.id+'">'+e.razon_social+'</option>'; }).join("");
    var vendOpts = (arr[1].vendedores || []).map(function (v) {
      return '<option value="'+v.id+'">'+v.nombre_completo+'</option>';
    }).join("");
    document.getElementById("fVendedor").innerHTML = '<option value="">Todos</option>' + vendOpts;
    document.getElementById("selVendedor").innerHTML = '<option value="">Yo / asignado</option>' + vendOpts;
    cotizaciones = arr[2].cotizaciones || [];
    document.getElementById("selCotizacion").innerHTML = '<option value="">(sin vincular)</option>' +
      cotizaciones.map(function (c) {
        return '<option value="'+c.id+'">'+(c.folio||c.id)+' · '+(c.razon_social||"")+'</option>';
      }).join("");
  }).catch(function (e) { crmToast(e.message, true); });

  var now = new Date();
  now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
  document.getElementById("actFecha").value = now.toISOString().slice(0, 16);

  load();
})();
</script>
<?php crm_layout_end(); ?>
