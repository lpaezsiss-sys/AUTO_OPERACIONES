(function (w) {
  "use strict";

  var cache = {};

  function modalEl(id) {
    return document.getElementById(id);
  }

  function showModal(id) {
    var el = modalEl(id);
    if (!el || typeof bootstrap === "undefined") {
      return;
    }
    bootstrap.Modal.getOrCreateInstance(el).show();
  }

  function hideModal(id) {
    var el = modalEl(id);
    if (!el || typeof bootstrap === "undefined") {
      return;
    }
    var inst = bootstrap.Modal.getInstance(el);
    if (inst) {
      inst.hide();
    }
  }

  function fillEdit(op) {
    op = op || {};
    cache[op.id] = op;
    document.getElementById("editOpId").value = String(op.id || "");
    document.getElementById("editOpNombre").value = op.nombre || "";
    document.getElementById("editOpProveedor").value = op.proveedor || "";
    document.getElementById("editOpReferencia").value = op.referencia || "";
    document.getElementById("editOpMoneda").value = op.moneda_base || "USD";
  }

  function fillDelete(op) {
    op = op || {};
    cache[op.id] = op;
    document.getElementById("delOpId").value = String(op.id || "");
    var folio = (op.folio || "esta operación") + (op.nombre ? " · " + op.nombre : "");
    document.getElementById("delOpFolio").textContent = folio;
    var stock = !!op.tiene_movimientos;
    document.getElementById("delOpStockBox").classList.toggle("d-none", !stock);
    document.getElementById("btnDelRevertir").classList.toggle("d-none", !stock);
    var isAdmin = (window.COMEX_ROL || "comex") === "admin";
    var wrap = document.getElementById("delOpAdminWrap");
    var note = document.getElementById("delOpNoAdmin");
    if (wrap) {
      wrap.classList.toggle("d-none", !isAdmin);
    }
    if (note) {
      note.classList.toggle("d-none", isAdmin || !stock);
    }
    var chk = document.getElementById("delOpAdmin");
    if (chk) {
      chk.checked = false;
    }
  }

  function loadOp(id) {
    if (cache[id]) {
      return Promise.resolve(cache[id]);
    }
    return crmApi("api/operaciones.php?id=" + encodeURIComponent(id)).then(function (d) {
      cache[id] = d.operacion || {};
      return cache[id];
    });
  }

  w.comexOpMenuHtml = function (t, variant) {
    t = t || {};
    var id = crmEsc(t.id);
    if (variant === "row") {
      return '<div class="btn-group btn-group-sm op-row-actions" role="group" aria-label="Acciones">' +
        '<button class="btn btn-outline-secondary" type="button" data-op-edit="' + id + '">Editar</button>' +
        '<button class="btn btn-outline-danger" type="button" data-op-del="' + id + '">Eliminar</button>' +
        "</div>";
    }
    return '<div class="dropdown kanban-card-menu">' +
      '<button class="btn btn-sm btn-card-more" type="button" data-bs-toggle="dropdown" data-bs-auto-close="true" data-bs-popper-config=\'{"strategy":"fixed"}\' aria-expanded="false" aria-label="Acciones" onclick="event.preventDefault(); event.stopPropagation();">' +
      '<i class="bi bi-three-dots-vertical" aria-hidden="true"></i></button>' +
      '<ul class="dropdown-menu dropdown-menu-end">' +
      '<li><button class="dropdown-item" type="button" data-op-edit="' + id + '">Editar</button></li>' +
      '<li><button class="dropdown-item text-danger" type="button" data-op-del="' + id + '">Eliminar</button></li>' +
      "</ul></div>";
  };

  w.comexOpAbrirEditar = function (opOrId) {
    if (opOrId && typeof opOrId === "object") {
      fillEdit(opOrId);
      showModal("modalEditarOp");
      return;
    }
    loadOp(Number(opOrId)).then(function (op) {
      fillEdit(op);
      showModal("modalEditarOp");
    }).catch(function (e) { crmToast(e.message, true); });
  };

  w.comexOpAbrirEliminar = function (opOrId) {
    if (opOrId && typeof opOrId === "object") {
      fillDelete(opOrId);
      showModal("modalEliminarOp");
      return;
    }
    loadOp(Number(opOrId)).then(function (op) {
      fillDelete(op);
      showModal("modalEliminarOp");
    }).catch(function (e) { crmToast(e.message, true); });
  };

  function afterChanged() {
    if (typeof w.comexOpOnChanged === "function") {
      w.comexOpOnChanged();
      return;
    }
    location.reload();
  }

  function afterDeleted() {
    if (typeof w.comexOpOnDeleted === "function") {
      w.comexOpOnDeleted();
      return;
    }
    location.href = "operaciones.php";
  }

  function enviarDelete(opts) {
    var id = Number(document.getElementById("delOpId").value || 0);
    var body = { id: id, action: "delete" };
    if (opts && opts.admin && (window.COMEX_ROL || "comex") === "admin") {
      body.confirmar_admin = true;
    }
    if (opts && opts.revertir) {
      body.revertir_stock = true;
    }
    return crmApi("api/operaciones.php?action=delete", { method: "POST", body: body }).then(function () {
      crmToast("Operación eliminada");
      hideModal("modalEliminarOp");
      afterDeleted();
    }).catch(function (e) {
      if (e.status === 409 || e.codigo === "STOCK_MOVIMIENTOS") {
        document.getElementById("delOpStockBox").classList.remove("d-none");
        document.getElementById("btnDelRevertir").classList.remove("d-none");
      }
      crmToast(e.message, true);
    });
  }

  document.addEventListener("click", function (ev) {
    var ed = ev.target.closest("[data-op-edit]");
    if (ed) {
      ev.preventDefault();
      ev.stopPropagation();
      w.comexOpAbrirEditar(Number(ed.getAttribute("data-op-edit")));
      return;
    }
    var del = ev.target.closest("[data-op-del]");
    if (del) {
      ev.preventDefault();
      ev.stopPropagation();
      w.comexOpAbrirEliminar(Number(del.getAttribute("data-op-del")));
    }
  });

  function bindCrud() {
    var form = document.getElementById("formEditarOp");
    if (form && !form.getAttribute("data-bound")) {
      form.setAttribute("data-bound", "1");
      form.addEventListener("submit", function (ev) {
        ev.preventDefault();
        var id = Number(document.getElementById("editOpId").value || 0);
        crmApi("api/operaciones.php?action=update", {
          method: "POST",
          body: {
            id: id,
            action: "update",
            nombre: document.getElementById("editOpNombre").value.trim(),
            proveedor: document.getElementById("editOpProveedor").value.trim(),
            referencia: document.getElementById("editOpReferencia").value.trim(),
            moneda_base: document.getElementById("editOpMoneda").value,
          },
        }).then(function () {
          crmToast("Operación actualizada");
          hideModal("modalEditarOp");
          afterChanged();
        }).catch(function (e) { crmToast(e.message, true); });
      });
    }
    var btnDel = document.getElementById("btnDelConfirmar");
    if (btnDel && !btnDel.getAttribute("data-bound")) {
      btnDel.setAttribute("data-bound", "1");
      btnDel.addEventListener("click", function () {
        var admin = !!(document.getElementById("delOpAdmin") && document.getElementById("delOpAdmin").checked);
        enviarDelete({ admin: admin });
      });
    }
    var btnRev = document.getElementById("btnDelRevertir");
    if (btnRev && !btnRev.getAttribute("data-bound")) {
      btnRev.setAttribute("data-bound", "1");
      btnRev.addEventListener("click", function () {
        enviarDelete({ revertir: true });
      });
    }
    var btnE = document.getElementById("btnEditarOp");
    if (btnE && !btnE.getAttribute("data-bound")) {
      btnE.setAttribute("data-bound", "1");
      btnE.addEventListener("click", function () {
        w.comexOpAbrirEditar(Number(w.COMEX_OPERACION_ID || document.getElementById("editOpId").value || 0));
      });
    }
    var btnX = document.getElementById("btnEliminarOp");
    if (btnX && !btnX.getAttribute("data-bound")) {
      btnX.setAttribute("data-bound", "1");
      btnX.addEventListener("click", function () {
        w.comexOpAbrirEliminar(Number(w.COMEX_OPERACION_ID || document.getElementById("delOpId").value || 0));
      });
    }
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", bindCrud);
  } else {
    bindCrud();
  }
})(window);
