(function () {
  "use strict";

  if (window.COMEX_TAB !== "documents") {
    return;
  }

  var opId = Number(window.COMEX_OPERACION_ID || 0);
  var pack = null;
  var selectedId = 0;

  function fmtFecha(s) {
    if (!s) {
      return "—";
    }
    var d = new Date(String(s).replace(" ", "T"));
    if (isNaN(d.getTime())) {
      return crmEsc(s);
    }
    return d.toLocaleString("es-CL", { dateStyle: "short", timeStyle: "short" });
  }

  function fmtSize(n) {
    n = Number(n || 0);
    if (n < 1024) {
      return n + " B";
    }
    if (n < 1048576) {
      return (n / 1024).toFixed(1) + " KB";
    }
    return (n / 1048576).toFixed(1) + " MB";
  }

  function renderTipos() {
    var box = document.getElementById("docTipos");
    var rows = pack.por_tipo || [];
    box.innerHTML = rows.map(function (t) {
      return '<div class="col-6 col-xl"><div class="card kpi p-3"><div class="kpi-label">' +
        crmEsc(t.nombre) + '</div><div class="kpi-value">' + crmEsc(t.cantidad) + "</div></div></div>";
    }).join("");
  }

  function renderLista() {
    var docs = pack.documentos || [];
    var el = document.getElementById("listaDocs");
    if (!docs.length) {
      el.innerHTML = '<p class="text-secondary mb-0">Sin documentos. Suba Factura Comercial, Packing List, BL/AWB, Certificados o DIN/DUS.</p>';
      return;
    }
    el.innerHTML = docs.map(function (d) {
      var active = Number(d.id) === selectedId ? " is-active" : "";
      return '<button type="button" class="doc-item' + active + '" data-id="' + crmEsc(d.id) + '">' +
        '<div class="fw-semibold">' + crmEsc(d.tipo_label || d.tipo) + "</div>" +
        '<div class="small">' + crmEsc(d.nombre_original) + " · " + crmEsc(fmtSize(d.size_bytes)) + "</div>" +
        '<div class="small text-secondary">Usuario: ' + crmEsc(d.usuario) + " · " + fmtFecha(d.created_at) + "</div>" +
        "</button>";
    }).join("");
    Array.prototype.forEach.call(el.querySelectorAll(".doc-item"), function (btn) {
      btn.addEventListener("click", function () {
        preview(Number(btn.getAttribute("data-id")));
      });
    });
  }

  function preview(id) {
    var docs = pack.documentos || [];
    var doc = null;
    docs.forEach(function (d) {
      if (Number(d.id) === id) {
        doc = d;
      }
    });
    selectedId = id;
    renderLista();
    var empty = document.getElementById("previewEmpty");
    var pdf = document.getElementById("previewPdf");
    var img = document.getElementById("previewImg");
    var dl = document.getElementById("docDownload");
    if (!doc) {
      empty.classList.remove("d-none");
      pdf.classList.add("d-none");
      img.classList.add("d-none");
      dl.classList.add("d-none");
      return;
    }
    empty.classList.add("d-none");
    dl.classList.remove("d-none");
    dl.href = doc.download_url;
    if (doc.es_pdf) {
      pdf.src = doc.preview_url;
      pdf.classList.remove("d-none");
      img.classList.add("d-none");
      img.removeAttribute("src");
    } else if (doc.es_imagen) {
      img.src = doc.preview_url;
      img.classList.remove("d-none");
      pdf.classList.add("d-none");
      pdf.removeAttribute("src");
    } else {
      empty.classList.remove("d-none");
      empty.textContent = "Sin vista previa. Use Descargar.";
      pdf.classList.add("d-none");
      img.classList.add("d-none");
    }
  }

  async function load() {
    if (!opId) {
      crmToast("Indique ?id= de la operación", true);
      return;
    }
    pack = await crmApi("api/documentos.php?operacion_id=" + opId);
    var op = pack.operacion || {};
    document.getElementById("tituloOp").textContent = (op.folio || "Operación") + " · Documentos";
    document.getElementById("subOp").textContent = "Repositorio documental · usuario y fecha por archivo";
    var sel = document.getElementById("docTipo");
    var tipos = pack.tipos || {};
    Object.keys(tipos).forEach(function (k) {
      var opt = sel.querySelector('option[value="' + k + '"]');
      if (opt) {
        opt.textContent = tipos[k];
      }
    });
    renderTipos();
    renderLista();
    if (selectedId) {
      preview(selectedId);
    }
  }

  document.getElementById("formDoc").addEventListener("submit", function (ev) {
    ev.preventDefault();
    var fd = new FormData(document.getElementById("formDoc"));
    fd.set("operacion_id", String(opId));
    fetch("api/documentos.php", { method: "POST", body: fd, credentials: "same-origin", cache: "no-store" })
      .then(function (res) { return res.json().then(function (data) { return { res: res, data: data }; }); })
      .then(function (out) {
        if (!out.res.ok || out.data.ok === false || out.data.success === false) {
          throw new Error(out.data.error || "No se pudo subir");
        }
        crmToast("Documento cargado");
        document.getElementById("docArchivo").value = "";
        selectedId = out.data.documento && out.data.documento.id ? Number(out.data.documento.id) : selectedId;
        return load();
      })
      .catch(function (e) { crmToast(e.message, true); });
  });

  load().catch(function (e) { crmToast(e.message, true); });
})();
