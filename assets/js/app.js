(function () {
  "use strict";

  window.crmApi = async function (path, options) {
    options = options || {};
    var isForm = typeof FormData !== "undefined" && options.body instanceof FormData;
    var headers = { Accept: "application/json" };
    if (options.body && !isForm) {
      headers["Content-Type"] = "application/json";
    }
    var res = await fetch(path, {
      credentials: "same-origin",
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
