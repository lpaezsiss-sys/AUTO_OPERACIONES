(function () {
  "use strict";

  var catalogo = {};
  var operacionActual = null;
  var lastCalculo = null;
  var pack = null;

  function gastosPayload() {
    return Array.prototype.map.call(document.querySelectorAll("#tablaGastos tbody tr"), function (tr) {
      return {
        codigo: tr.getAttribute("data-codigo"),
        nombre: tr.getAttribute("data-nombre"),
        ambito: tr.getAttribute("data-ambito"),
        moneda: tr.querySelector(".g-moneda").value,
        monto: crmParseNum(tr.querySelector(".g-monto").value),
        cif: tr.getAttribute("data-cif") === "1",
      };
    });
  }

  function itemsPayload() {
    return Array.prototype.map.call(document.querySelectorAll("#tablaItems tbody tr"), function (tr) {
      return {
        operacion_item_id: Number(tr.getAttribute("data-id") || 0),
        sku: tr.getAttribute("data-sku"),
        descripcion: tr.querySelector(".i-desc").value,
        is_custom: tr.getAttribute("data-custom") === "1",
        cantidad: crmParseNum(tr.querySelector(".i-cant").value),
        fob_unitario: crmParseNum(tr.querySelector(".i-fob").value),
      };
    });
  }

  function payload(version) {
    return {
      operacion_id: Number(document.getElementById("operacion").value || 0),
      version: version || document.getElementById("version").value,
      moneda_origen: document.getElementById("moneda").value,
      tipo_cambio_usd: crmParseNum(document.getElementById("tcUsd").value),
      tipo_cambio_eur: crmParseNum(document.getElementById("tcEur").value),
      iva_pct: crmParseNum(document.getElementById("ivaPct").value),
      notas: document.getElementById("notas").value,
      gastos: gastosPayload(),
      items: itemsPayload(),
    };
  }

  function renderGastos(rows) {
    var tb = document.querySelector("#tablaGastos tbody");
    tb.innerHTML = (rows || []).map(function (g) {
      var moneda = g.moneda || "CLP";
      return '<tr data-codigo="' + crmEsc(g.codigo) + '" data-nombre="' + crmEsc(g.nombre) +
        '" data-ambito="' + crmEsc(g.ambito) + '" data-cif="' + (g.cif ? "1" : "0") + '">' +
        '<td><div class="fw-semibold">' + crmEsc(g.nombre) + '</div><div class="small text-secondary">' +
        crmEsc(g.ambito) + (g.cif ? " · CIF" : "") + "</div></td>" +
        '<td><select class="form-select form-select-sm g-moneda">' +
        ["CLP", "USD", "EUR"].map(function (m) {
          return '<option value="' + m + '"' + (m === moneda ? " selected" : "") + ">" + m + "</option>";
        }).join("") + "</select></td>" +
        '<td><input class="form-control form-control-sm g-monto" value="' + crmEsc(g.monto || 0) + '"></td></tr>';
    }).join("");
  }

  function skuCell(it) {
    var badge = it.is_custom
      ? ' <span class="badge-eval">Evaluación</span>'
      : "";
    return "<code>" + crmEsc(it.sku) + "</code>" + badge;
  }

  function renderItems(items) {
    var tb = document.querySelector("#tablaItems tbody");
    tb.innerHTML = (items || []).map(function (it) {
      return '<tr data-id="' + crmEsc(it.id || it.operacion_item_id || 0) + '" data-sku="' + crmEsc(it.sku) +
        '" data-custom="' + (it.is_custom ? "1" : "0") + '">' +
        "<td>" + skuCell(it) + "</td>" +
        '<td><input class="form-control form-control-sm i-cant" value="' + crmEsc(it.cantidad || 0) + '"></td>' +
        '<td><input class="form-control form-control-sm i-fob" value="' + crmEsc(it.fob_unitario || it.precio_unitario || 0) + '"></td>' +
        '<td><input class="form-control form-control-sm i-desc" value="' + crmEsc(it.descripcion || "") + '"></td></tr>';
    }).join("") || '<tr><td colspan="4" class="text-secondary">Seleccione una operación con ítems.</td></tr>';
  }

  function renderResultado(calc) {
    lastCalculo = calc;
    var t = calc.totales || {};
    document.getElementById("kpis").innerHTML = [
      ["FOB CLP", crmClp(t.fob_clp)],
      ["CIF", crmClp(t.cif_clp)],
      ["IVA aduanero", crmClp(t.iva_clp)],
      ["Landed cost", crmClp(t.landed_clp)],
    ].map(function (it) {
      return '<div class="col-6 col-xl-3"><div class="card kpi p-3"><div class="kpi-label">' + it[0] +
        '</div><div class="kpi-value">' + it[1] + "</div></div></div>";
    }).join("");
    var tb = document.querySelector("#tablaResultado tbody");
    tb.innerHTML = (calc.items || []).map(function (it) {
      return "<tr><td><code>" + crmEsc(it.sku) + "</code>" +
        (it.is_custom ? ' <span class="badge-eval">Evaluación</span>' : "") +
        "</td><td>" + crmNum(it.fob_origen, 2) +
        "</td><td>" + crmClp(it.fob_clp) + "</td><td>" + crmNum((it.share || 0) * 100, 1) +
        "%</td><td>" + crmClp(it.cif_clp) + "</td><td>" + crmClp(it.iva_clp) +
        "</td><td>" + crmClp(it.gastos_locales_clp) + "</td><td class=\"fw-semibold\">" +
        crmClp(it.landed_total_clp) + "</td><td>" + crmClp(it.landed_unitario_clp) + "</td></tr>";
    }).join("");
  }

  function renderComparar(comp) {
    var el = document.getElementById("comparar");
    if (!comp || !comp.disponible) {
      el.innerHTML = '<span class="text-secondary">Guarda Estimada y Costo real para ver la desviación.</span>';
      return;
    }
    var tot = comp.totales || {};
    el.innerHTML = '<div class="table-responsive"><table class="table table-sm table-landed"><thead><tr>' +
      "<th>Concepto</th><th>Estimada</th><th>Real</th><th>Delta</th></tr></thead><tbody>" +
      ["cif_clp", "iva_clp", "gastos_locales_clp", "landed_clp"].map(function (k) {
        var d = tot[k] || {};
        var cls = (d.delta || 0) > 0 ? "delta-up" : ((d.delta || 0) < 0 ? "delta-down" : "");
        var label = { cif_clp: "CIF", iva_clp: "IVA aduanero", gastos_locales_clp: "Gastos locales", landed_clp: "Landed cost" }[k];
        return "<tr><td>" + label + "</td><td>" + crmClp(d.estimada) + "</td><td>" + crmClp(d.real) +
          '</td><td class="' + cls + '">' + crmClp(d.delta) + " (" + crmEsc(d.delta_pct) + "%)</td></tr>";
      }).join("") + "</tbody></table></div>";
  }

  function applyEval(row) {
    if (!row || !row.calculo) {
      return;
    }
    var c = row.calculo;
    document.getElementById("version").value = c.version;
    document.getElementById("moneda").value = c.moneda_origen || "USD";
    document.getElementById("tcUsd").value = c.tipo_cambio_usd || "";
    document.getElementById("tcEur").value = c.tipo_cambio_eur || "";
    document.getElementById("notas").value = row.notas || "";
    renderGastos(c.gastos);
    renderItems(c.items);
    renderResultado(c);
  }

  async function loadOperacion() {
    var id = Number(document.getElementById("operacion").value || 0);
    if (!id) {
      return;
    }
    pack = await crmApi("api/landed.php?operacion_id=" + id);
    operacionActual = pack.operacion;
    catalogo = pack.catalogo_gastos || catalogo;
    var ver = document.getElementById("version").value;
    var saved = ver === "REAL" ? pack.real : pack.estimada;
    if (saved) {
      applyEval(saved);
    } else {
      var gastos = Object.keys(catalogo).map(function (k) {
        var m = catalogo[k];
        return { codigo: k, nombre: m.nombre, ambito: m.ambito, moneda: m.moneda, monto: 0, cif: m.cif };
      });
      renderGastos(gastos);
      renderItems((operacionActual.items || []).map(function (it) {
        return {
          id: it.id,
          sku: it.sku,
          cantidad: it.cantidad,
          fob_unitario: it.precio_unitario,
          descripcion: it.descripcion,
          is_custom: it.is_custom,
          origen: it.origen,
        };
      }));
      document.getElementById("kpis").innerHTML = "";
      document.querySelector("#tablaResultado tbody").innerHTML = "";
    }
    renderComparar(pack.comparacion);
  }

  async function init() {
    var data = await crmApi("api/landed.php");
    catalogo = data.catalogo_gastos || {};
    var sel = document.getElementById("operacion");
    sel.innerHTML = '<option value="">Seleccionar…</option>' + (data.operaciones || []).map(function (op) {
      return '<option value="' + op.id + '">' + crmEsc(op.folio) + " · " + crmEsc(op.tipo) + "</option>";
    }).join("");
    var q = new URLSearchParams(location.search).get("operacion_id");
    if (q) {
      sel.value = q;
      await loadOperacion();
    } else if (!sel.value) {
      renderGastos(Object.keys(catalogo).map(function (k) {
        var m = catalogo[k];
        return { codigo: k, nombre: m.nombre, ambito: m.ambito, moneda: m.moneda, monto: 0, cif: m.cif };
      }));
    }
  }

  document.getElementById("operacion").addEventListener("change", function () {
    loadOperacion().catch(function (e) { crmToast(e.message, true); });
  });
  document.getElementById("version").addEventListener("change", function () {
    loadOperacion().catch(function (e) { crmToast(e.message, true); });
  });
  document.getElementById("btnCalcular").addEventListener("click", function () {
    crmApi("api/landed.php", { method: "POST", body: Object.assign(payload(), { action: "calcular" }) })
      .then(function (d) { renderResultado(d.calculo); crmToast("Prorrateo calculado"); })
      .catch(function (e) { crmToast(e.message, true); });
  });
  document.getElementById("btnGuardar").addEventListener("click", function () {
    crmApi("api/landed.php", { method: "POST", body: Object.assign(payload(), { action: "guardar" }) })
      .then(function (d) {
        applyEval(d.evaluacion);
        crmToast("Versión " + d.evaluacion.version + " guardada");
        return loadOperacion();
      })
      .catch(function (e) { crmToast(e.message, true); });
  });
  document.getElementById("btnPdf").addEventListener("click", function () {
    var body = Object.assign(payload(), { action: "pdf" });
    if (pack) {
      var ver = document.getElementById("version").value;
      var row = ver === "REAL" ? pack.real : pack.estimada;
      if (row) {
        body.id = row.id;
      }
    }
    crmApi("api/landed.php", { method: "POST", body: body })
      .then(function (d) {
        var p = d.evaluacion && d.evaluacion.pdf_path;
        crmToast("PDF generado");
        if (p) {
          window.open(p, "_blank");
        }
      })
      .catch(function (e) { crmToast(e.message, true); });
  });

  init().catch(function (e) { crmToast(e.message, true); });
})();
