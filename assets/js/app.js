(function () {
  "use strict";

  window.crmApi = async function (path, options) {
    options = options || {};
    var isForm = typeof FormData !== "undefined" && options.body instanceof FormData;
    var headers = {
      Accept: "application/json",
      "Cache-Control": "no-cache",
      Pragma: "no-cache",
    };
    if (options.body && !isForm) {
      headers["Content-Type"] = "application/json";
    }
    var res = await fetch(path, {
      credentials: "same-origin",
      cache: "no-store",
      method: options.method || "GET",
      headers: Object.assign(headers, options.headers || {}),
      body: options.body ? (isForm ? options.body : JSON.stringify(options.body)) : undefined,
    });
    var data = {};
    try {
      data = await res.json();
    } catch (e) {
      data = { ok: false, success: false, error: "Respuesta inválida (HTTP " + res.status + ")" };
    }
    if (!res.ok || data.ok === false || data.success === false) {
      var err = new Error(data.error || "Error de API");
      err.status = res.status;
      throw err;
    }
    return data;
  };

  window.crmEsc = function (s) {
    return String(s == null ? "" : s)
      .replace(/&/g, "&amp;")
      .replace(/"/g, "&quot;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;");
  };

  window.crmClp = function (n) {
    return new Intl.NumberFormat("es-CL", {
      style: "currency",
      currency: "CLP",
      maximumFractionDigits: 0,
    }).format(Number(n || 0));
  };

  /**
   * Stock fresco: respuesta de api/precios.php, si no el ítem de búsqueda.
   * 0 es stock válido; null/undefined/"" se tratan como ausente.
   */
  window.crmStockFromApi = function (item, precio) {
    if (precio && precio.stock != null && precio.stock !== "") {
      return precio.stock;
    }
    if (item && item.stock != null && item.stock !== "") {
      return item.stock;
    }
    return null;
  };

  /**
   * Alineado con crm_float(): 24.38, 24,38 y 1.234,56.
   */
  window.crmParseNum = function (v) {
    if (typeof v === "number") {
      return isFinite(v) ? v : 0;
    }
    if (v == null) {
      return 0;
    }
    var s = String(v).trim();
    if (s === "") {
      return 0;
    }
    if (/^-?\d+(\.\d+)?$/.test(s)) {
      var direct = parseFloat(s);
      return isFinite(direct) ? direct : 0;
    }
    var stripped = s.replace(/[^\d,.\-]/g, "");
    var normalized = stripped.replace(/\./g, "").replace(/,/g, ".");
    var n = parseFloat(normalized);
    return isFinite(n) ? n : 0;
  };

  window.crmToast = function (msg, danger) {
    var el = document.getElementById("crmToast");
    var body = document.getElementById("crmToastBody");
    if (!el || !body) {
      window.alert(msg);
      return;
    }
    body.textContent = msg;
    el.classList.toggle("text-bg-danger", !!danger);
    el.classList.toggle("text-bg-dark", !danger);
    bootstrap.Toast.getOrCreateInstance(el, { delay: 3200 }).show();
  };

  window.crmWhatsAppUrl = function (raw) {
    var d = String(raw == null ? "" : raw).replace(/\D/g, "");
    if (d.length === 9 && d.charAt(0) === "9") {
      d = "56" + d;
    }
    if (d.length === 8) {
      d = "569" + d;
    }
    if (d.length < 10) {
      return "";
    }
    return "https://wa.me/" + d;
  };

  /**
   * Typeahead de empresa (RUT / razón social) sobre api/empresas.php.
   * cfg: { inputId, hiddenId, listId, onSelect }
   */
  window.crmEmpresaPicker = function (cfg) {
    var input = document.getElementById(cfg.inputId);
    var hidden = document.getElementById(cfg.hiddenId);
    var list = document.getElementById(cfg.listId);
    var timer = null;
    var lastList = [];
    if (!input || !hidden || !list) {
      return null;
    }
    function labelOf(e) {
      if (!e) {
        return "";
      }
      return (e.razon_social || "") + (e.rut ? " · " + e.rut : "");
    }
    function setEmpresa(e) {
      hidden.value = e && e.id ? String(e.id) : "";
      input.value = e ? labelOf(e) : "";
      list.style.display = "none";
      if (typeof cfg.onSelect === "function") {
        cfg.onSelect(e || null);
      }
    }
    function search(q) {
      var url = "api/empresas.php?limit=25";
      if (q) {
        url += "&q=" + encodeURIComponent(q);
      }
      crmApi(url).then(function (d) {
        lastList = d.empresas || [];
        if (!lastList.length) {
          list.innerHTML = '<div class="list-group-item small text-secondary">Sin empresas</div>';
          list.style.display = "block";
          return;
        }
        list.innerHTML = lastList.map(function (e, i) {
          return '<button type="button" class="list-group-item list-group-item-action" data-idx="' + i + '">' +
            crmEsc(e.razon_social) +
            '<div class="small text-secondary">' + crmEsc(e.rut || "") + "</div></button>";
        }).join("");
        list.style.display = "block";
        list.querySelectorAll("button").forEach(function (btn) {
          btn.addEventListener("click", function () {
            setEmpresa(lastList[Number(btn.getAttribute("data-idx"))]);
          });
        });
      }).catch(function (e) {
        crmToast(e.message, true);
      });
    }
    input.addEventListener("input", function () {
      hidden.value = "";
      if (timer) {
        clearTimeout(timer);
      }
      timer = setTimeout(function () {
        search(input.value);
      }, 220);
    });
    input.addEventListener("focus", function () {
      search(input.value);
    });
    document.addEventListener("click", function (ev) {
      if (ev.target === input || list.contains(ev.target)) {
        return;
      }
      list.style.display = "none";
    });
    return {
      setEmpresa: setEmpresa,
      loadById: function (id) {
        if (!id) {
          setEmpresa(null);
          return Promise.resolve(null);
        }
        return crmApi("api/empresas.php?id=" + encodeURIComponent(id)).then(function (d) {
          var e = d.empresa || null;
          if (e) {
            setEmpresa(e);
          }
          return e;
        });
      }
    };
  };

  window.crmForm = function (id) {
    var form = document.getElementById(id);
    var data = {};
    if (!form) {
      return data;
    }
    new FormData(form).forEach(function (value, key) {
      if (data[key] !== undefined) {
        return;
      }
      if (form.elements[key] && form.elements[key].type === "checkbox") {
        data[key] = form.elements[key].checked;
      } else {
        data[key] = value;
      }
    });
    return data;
  };

  document.addEventListener("click", function (ev) {
    if (ev.target && ev.target.id === "btnLogout") {
      crmApi("api/auth.php", { method: "POST", body: { action: "logout" } })
        .then(function () {
          window.location.href = "login.php";
        })
        .catch(function () {
          window.location.href = "login.php";
        });
    }
  });
})();
