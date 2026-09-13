(function () {
  "use strict";

  if (window.COMEX_TAB && window.COMEX_TAB !== "pipeline") {
    return;
  }

  var pack = null;
  var opId = Number(window.COMEX_OPERACION_ID || 0);
  var panel = null;

  function badgeEstado(st) {
    st = st || "PENDING";
    return '<span class="st-badge st-' + crmEsc(st.toLowerCase()) + '">' + crmEsc(st) + "</span>";
  }

  function badgeAlerta(et) {
    if (et.bloqueada) {
      return '<span class="badge-alert blocked">Bloqueada</span>';
    }
    if (et.atrasada) {
      return '<span class="badge-alert overdue">Atrasada</span>';
    }
    return "";
  }

  function renderKpis() {
    var a = pack.alertas || {};
    var p = pack.progreso || {};
    var actual = pack.actual || {};
    document.getElementById("kpis").innerHTML = [
      ["Etapa actual", actual.nombre || "Cerrada", ""],
      ["Avance", (p.hechas || 0) + "/" + (p.total || 13), ""],
      ["Atrasadas", a.atrasadas || 0, a.atrasadas ? "kpi-alert" : ""],
      ["Bloqueadas", a.bloqueadas || 0, a.bloqueadas ? "kpi-warn" : ""],
    ].map(function (it) {
      return '<div class="col-6 col-xl-3"><div class="card kpi p-3 ' + it[2] + '"><div class="kpi-label">' +
        it[0] + '</div><div class="kpi-value" style="font-size:1.05rem">' + crmEsc(it[1]) + "</div></div></div>";
    }).join("");
  }

  function renderKanban() {
    var cols = pack.kanban_estado || {};
    var order = ["PENDING", "IN_PROGRESS", "COMPLETED", "BLOCKED"];
    var labels = { PENDING: "Pendiente", IN_PROGRESS: "En curso", COMPLETED: "Completada", BLOCKED: "Bloqueada" };
    document.getElementById("vistaKanban").innerHTML =
      '<section class="fase-block"><h2 class="fase-title">Tablero por estado</h2>' +
      '<div class="kanban-board">' + order.map(function (st) {
        var items = cols[st] || [];
        return '<div class="kanban-col">' +
          '<div class="kanban-col-h"><div>' + labels[st] + '</div><span class="count">' + items.length + "</span></div>" +
          '<div class="kanban-col-b">' + (items.map(function (et) {
            var cls = "kanban-card";
            if (et.bloqueada) {
              cls += " is-blocked";
            } else if (et.atrasada) {
              cls += " is-overdue";
            }
            return '<button type="button" class="' + cls + '" data-etapa="' + et.id + '">' +
              '<div class="d-flex justify-content-between">' +
              '<span class="fw-semibold">' + crmEsc(et.nombre) + "</span>" + badgeAlerta(et) + "</div>" +
              '<div class="small text-secondary">' + crmEsc(et.fase) + " · " + crmEsc(et.responsable || "Sin responsable") + "</div>" +
              '<div class="small">Est. ' + crmEsc(et.fecha_estimada || "—") + " · Real " + crmEsc(et.fecha_real || "—") + "</div>" +
              "</button>";
          }).join("") || '<div class="empty-col">Vacío</div>') + "</div></div>";
      }).join("") + "</div></section>";
    Array.prototype.forEach.call(document.querySelectorAll("#vistaKanban [data-etapa]"), function (btn) {
      btn.addEventListener("click", function () { openEtapa(Number(btn.getAttribute("data-etapa"))); });
    });
  }

  function renderLista() {
    var tb = document.querySelector("#tablaEtapas tbody");
    tb.innerHTML = (pack.etapas || []).map(function (et) {
      var cls = et.bloqueada ? "row-blocked" : (et.atrasada ? "row-overdue" : "");
      return '<tr class="' + cls + '" data-etapa="' + et.id + '" style="cursor:pointer">' +
        "<td>" + crmEsc(et.orden) + "</td><td>" + crmEsc(et.nombre) + "</td><td>" + crmEsc(et.fase) +
        "</td><td>" + badgeEstado(et.estado) + "</td><td>" + crmEsc(et.responsable || "—") +
        "</td><td>" + crmEsc(et.fecha_estimada || "—") + "</td><td>" + crmEsc(et.fecha_real || "—") +
        "</td><td>" + (badgeAlerta(et) || "—") + "</td></tr>";
    }).join("");
    Array.prototype.forEach.call(tb.querySelectorAll("tr[data-etapa]"), function (tr) {
      tr.addEventListener("click", function () { openEtapa(Number(tr.getAttribute("data-etapa"))); });
    });
  }

  function openEtapa(id) {
    var et = (pack.etapas || []).find(function (x) { return Number(x.id) === id; });
    if (!et) {
      return;
    }
    document.getElementById("etapaId").value = et.id;
    document.getElementById("panelTitulo").textContent = et.nombre;
    document.getElementById("etapaEstado").value = et.estado;
    document.getElementById("etapaResp").value = et.responsable || "";
    document.getElementById("etapaEst").value = et.fecha_estimada || "";
    document.getElementById("etapaReal").value = et.fecha_real || "";
    document.getElementById("etapaComentario").value = "";
    document.getElementById("listaBitacora").innerHTML = (et.bitacora || []).map(function (b) {
      return "<li><strong>" + crmEsc(b.autor || "COMEX") + "</strong> · " + crmEsc(b.created_at) +
        "<div>" + crmEsc(b.comentario) + "</div></li>";
    }).join("") || '<li class="text-secondary">Sin comentarios.</li>';
    panel.show();
  }

  function showVista(name) {
    document.getElementById("vistaKanban").classList.toggle("d-none", name !== "kanban");
    document.getElementById("vistaLista").classList.toggle("d-none", name !== "lista");
    document.getElementById("btnKanban").className = name === "kanban" ? "btn btn-navy" : "btn btn-outline-secondary";
    document.getElementById("btnLista").className = name === "lista" ? "btn btn-navy" : "btn btn-outline-secondary";
  }

  async function load() {
    if (!opId) {
      crmToast("Indique ?id= de la operación", true);
      return;
    }
    pack = await crmApi("api/pipeline.php?operacion_id=" + opId);
    var op = pack.operacion || {};
    document.getElementById("tituloOp").textContent = (op.folio || "Operación") + " · " + (op.tipo || "");
    document.getElementById("subOp").textContent = "Pipeline · " + ((pack.progreso && pack.progreso.hechas) || 0) +
      " de 13 etapas";
    renderKpis();
    renderKanban();
    renderLista();
  }

  document.getElementById("btnKanban").addEventListener("click", function () { showVista("kanban"); });
  document.getElementById("btnLista").addEventListener("click", function () { showVista("lista"); });
  document.getElementById("btnAvanzar").addEventListener("click", function () {
    crmApi("api/pipeline.php", {
      method: "POST",
      body: { action: "avanzar", operacion_id: opId, comentario: "Avance de etapa" },
    }).then(function () {
      crmToast("Etapa completada");
      return load();
    }).catch(function (e) { crmToast(e.message, true); });
  });
  document.getElementById("formEtapa").addEventListener("submit", function (ev) {
    ev.preventDefault();
    crmApi("api/pipeline.php", {
      method: "POST",
      body: {
        action: "actualizar",
        etapa_id: Number(document.getElementById("etapaId").value),
        estado: document.getElementById("etapaEstado").value,
        responsable: document.getElementById("etapaResp").value,
        fecha_estimada: document.getElementById("etapaEst").value,
        fecha_real: document.getElementById("etapaReal").value,
        comentario: document.getElementById("etapaComentario").value,
        autor: "COMEX",
      },
    }).then(function () {
      crmToast("Etapa actualizada");
      panel.hide();
      return load();
    }).catch(function (e) { crmToast(e.message, true); });
  });

  panel = bootstrap.Offcanvas.getOrCreateInstance(document.getElementById("panelEtapa"));
  load().catch(function (e) { crmToast(e.message, true); });
})();
