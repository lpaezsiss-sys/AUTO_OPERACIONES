(function () {
  "use strict";

  var board = null;
  var vista = "kanban";

  function badgeAlerta(row) {
    if (row.bloqueada) {
      return '<span class="badge-alert blocked">Bloqueada</span>';
    }
    if (row.atrasada) {
      return '<span class="badge-alert overdue">Atrasada</span>';
    }
    return '<span class="text-secondary">—</span>';
  }

  function cardHtml(t) {
    var et = t.etapa || {};
    var cls = "kanban-card";
    if (t.bloqueada) {
      cls += " is-blocked";
    } else if (t.atrasada) {
      cls += " is-overdue";
    }
    return '<a class="' + cls + '" href="operacion.php?id=' + encodeURIComponent(t.id) + '">' +
      '<div class="d-flex justify-content-between gap-1"><code>' + crmEsc(t.folio) + "</code>" +
      badgeAlerta(t) + "</div>" +
      '<div class="small text-secondary">' + crmEsc(t.tipo) + "</div>" +
      '<div class="small fw-semibold">' + crmEsc(et.nombre || "") + "</div>" +
      '<div class="small text-secondary">' + crmEsc((et.responsable || "Sin responsable")) +
      " · " + crmEsc(et.fecha_estimada || "s/est") + "</div>" +
      '<div class="progress mt-2" role="progressbar"><div class="progress-bar" style="width:' +
      crmEsc((t.progreso && t.progreso.pct) || 0) + '%"></div></div></a>';
  }

  function renderKpis(k) {
    k = k || {};
    document.getElementById("kpis").innerHTML = [
      ["Operaciones", k.operaciones || 0, ""],
      ["Atrasadas", k.atrasadas || 0, k.atrasadas ? "kpi-alert" : ""],
      ["Bloqueadas", k.bloqueadas || 0, k.bloqueadas ? "kpi-warn" : ""],
      ["Cerradas", k.completadas || 0, ""],
    ].map(function (it) {
      return '<div class="col-6 col-xl-3"><div class="card kpi p-3 ' + it[2] + '"><div class="kpi-label">' +
        it[0] + '</div><div class="kpi-value">' + it[1] + "</div></div></div>";
    }).join("");
  }

  function renderKanban() {
    var wrap = document.getElementById("vistaKanban");
    wrap.innerHTML = (board.fases || []).map(function (fase) {
      return '<section class="fase-block">' +
        '<h2 class="fase-title">' + crmEsc(fase.nombre) + " <span>" + crmEsc((fase.columnas || []).length) +
        " etapas</span></h2>" +
        '<div class="kanban-board">' + (fase.columnas || []).map(function (col) {
          var alert = (col.atrasadas || col.bloqueadas)
            ? '<div class="col-alert">' +
              (col.bloqueadas ? '<span class="badge-alert blocked">Bloqueadas</span>' : "") +
              (col.atrasadas ? '<span class="badge-alert overdue">Atrasadas</span>' : "") +
              "</div>"
            : "";
          return '<div class="kanban-col" data-codigo="' + crmEsc(col.codigo) + '">' +
            '<div class="kanban-col-h"><div>' + crmEsc(col.nombre) +
            '</div><span class="count">' + (col.tarjetas || []).length + "</span></div>" +
            alert + '<div class="kanban-col-b">' +
            ((col.tarjetas || []).map(cardHtml).join("") || '<div class="empty-col">Sin operaciones</div>') +
            "</div></div>";
        }).join("") + "</div></section>";
    }).join("");
  }

  function renderLista() {
    var tb = document.querySelector("#tablaOps tbody");
    tb.innerHTML = (board.operaciones || []).map(function (t) {
      var et = t.etapa || {};
      var vs = crmEsc(et.fecha_estimada || "—") + " / " + crmEsc(et.fecha_real || "—");
      return '<tr class="' + (t.bloqueada ? "row-blocked" : (t.atrasada ? "row-overdue" : "")) + '">' +
        '<td><a href="operacion.php?id=' + t.id + '"><code>' + crmEsc(t.folio) + "</code></a></td>" +
        "<td>" + crmEsc(t.tipo) + "</td><td>" + crmEsc(et.nombre || "") + "</td>" +
        "<td>" + crmEsc(et.fase || "") + "</td><td>" + crmEsc(et.responsable || "—") + "</td>" +
        "<td>" + vs + "</td><td>" + crmEsc((t.progreso && t.progreso.hechas) || 0) + "/" +
        crmEsc((t.progreso && t.progreso.total) || 13) + "</td><td>" + badgeAlerta(t) + "</td></tr>";
    }).join("") || '<tr><td colspan="8" class="text-secondary">Sin operaciones en el pipeline.</td></tr>';
  }

  function showVista(name) {
    vista = name;
    document.getElementById("vistaKanban").classList.toggle("d-none", name !== "kanban");
    document.getElementById("vistaLista").classList.toggle("d-none", name !== "lista");
    document.getElementById("btnKanban").className = name === "kanban" ? "btn btn-navy" : "btn btn-outline-secondary";
    document.getElementById("btnLista").className = name === "lista" ? "btn btn-navy" : "btn btn-outline-secondary";
  }

  async function load() {
    board = await crmApi("api/pipeline.php");
    renderKpis(board.kpis);
    renderKanban();
    renderLista();
  }

  document.getElementById("btnKanban").addEventListener("click", function () { showVista("kanban"); });
  document.getElementById("btnLista").addEventListener("click", function () { showVista("lista"); });

  document.getElementById("fecha").value = new Date().toISOString().slice(0, 10);
  document.getElementById("formCrear").addEventListener("submit", function (ev) {
    ev.preventDefault();
    var sku = document.getElementById("sku").value.trim();
    var body = {
      action: "crear",
      tipo: document.getElementById("tipo").value,
      folio: document.getElementById("folio").value.trim(),
      fecha: document.getElementById("fecha").value,
      referencia: document.getElementById("referencia").value.trim(),
      items: [],
    };
    if (sku) {
      body.items = [{
        sku: sku,
        cantidad: crmParseNum(document.getElementById("cantidad").value),
        precio_unitario: crmParseNum(document.getElementById("precio").value),
      }];
    }
    crmApi("api/pipeline.php", { method: "POST", body: body }).then(function (d) {
      crmToast("Operación creada con 13 etapas");
      var modal = bootstrap.Modal.getInstance(document.getElementById("modalCrear"));
      if (modal) {
        modal.hide();
      }
      if (d.operacion && d.operacion.id) {
        location.href = "operacion.php?id=" + d.operacion.id;
        return;
      }
      return load();
    }).catch(function (e) { crmToast(e.message, true); });
  });

  load().catch(function (e) { crmToast(e.message, true); });
})();
