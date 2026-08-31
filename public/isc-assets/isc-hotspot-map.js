(function () {
  var mapEl = document.getElementById("map");
  if (!mapEl || !window.L) {
    return;
  }

  var boundariesUrl = mapEl.getAttribute("data-boundaries-url") || "";
  var overlayUrl = mapEl.getAttribute("data-overlay-url") || "";
  var connected = mapEl.getAttribute("data-connected") === "1";

  var listEl = document.getElementById("zone-list");
  var countEl = document.getElementById("list-count");
  var listLabel = document.getElementById("list-label");
  var loadingEl = document.getElementById("map-loading");
  var liveStatus = document.getElementById("live-status");
  var detailEmpty = document.getElementById("detail-empty");
  var detailCard = document.getElementById("detail-card");
  var sumIupk = document.getElementById("sum-iupk");
  var sumBesigma = document.getElementById("sum-besigma");
  var sumViolations = document.getElementById("sum-violations");
  var sumEntries = document.getElementById("sum-entries");

  var scope = "semua";
  var showOps = true;
  var showBesigma = true;
  var iupkLayers = [];
  var besigmaGeo = { type: "FeatureCollection", features: [] };
  var overlay = { violations: [], entries: [] };

  var sat = L.tileLayer("https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}", {
    maxZoom: 18
  });
  var dark = L.tileLayer("https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png", {
    maxZoom: 18
  });

  var map = L.map("map", {
    center: [2.08, 117.42],
    zoom: 10,
    layers: [sat],
    zoomControl: false,
    attributionControl: false
  });

  map.createPane("iupk");
  map.getPane("iupk").style.zIndex = 450;
  var iupkRenderer = L.canvas({ pane: "iupk", padding: 0.8 });

  var opsLayer = L.geoJSON(window.IUPK_BOUNDARY || { type: "FeatureCollection", features: [] }, {
    pane: "iupk",
    renderer: iupkRenderer,
    style: {
      color: "#d4ff7a",
      weight: 4,
      fillColor: "#6bb443",
      fillOpacity: 0.22
    },
    onEachFeature: function (feature, layer) {
      var p = feature.properties || {};
      var luas = p.Luas != null ? Number(p.Luas).toLocaleString("id-ID", { maximumFractionDigits: 0 }) + " ha" : "";
      var title = [p.Site, p.Layer].filter(Boolean).join(" · ");
      layer.bindTooltip((title || "Konsesi IUPK") + (luas ? " · " + luas : ""), { sticky: true, className: "iupk-tip" });
      iupkLayers.push({ feature: feature, layer: layer });
    }
  });

  var besigmaLayer = L.geoJSON({ type: "FeatureCollection", features: [] }, {
    style: {
      color: "#86d15c",
      weight: 2,
      fillColor: "#86d15c",
      fillOpacity: 0.18
    },
    pointToLayer: function (feature, latlng) {
      return L.circleMarker(latlng, { radius: 7, color: "#86d15c", fillOpacity: 0.85 });
    },
    onEachFeature: function (feature, layer) {
      var p = feature.properties || {};
      var title = p.name || p.title || p.code || ("Boundary #" + (p.id || ""));
      layer.bindTooltip(String(title), { sticky: true, className: "iupk-tip" });
    }
  });

  opsLayer.addTo(map);

  function hideLoading() {
    if (loadingEl) {
      loadingEl.hidden = true;
    }
  }

  function fitOps() {
    if (opsLayer.getLayers().length) {
      map.fitBounds(opsLayer.getBounds(), { padding: [24, 24] });
    }
  }

  function esc(value) {
    return String(value == null || value === "" ? "—" : value)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
  }

  function applyScope() {
    if (showOps && (scope === "semua" || scope === "iupk")) {
      if (!map.hasLayer(opsLayer)) {
        opsLayer.addTo(map);
      }
    } else if (map.hasLayer(opsLayer)) {
      map.removeLayer(opsLayer);
    }

    if (showBesigma && (scope === "semua" || scope === "besigma")) {
      if (!map.hasLayer(besigmaLayer)) {
        besigmaLayer.addTo(map);
      }
    } else if (map.hasLayer(besigmaLayer)) {
      map.removeLayer(besigmaLayer);
    }

    renderList();
  }

  function renderList() {
    if (!listEl) {
      return;
    }

    var items = [];
    if (scope === "semua" || scope === "iupk") {
      iupkLayers.forEach(function (entry, index) {
        var p = entry.feature.properties || {};
        items.push({
          kind: "iupk",
          title: p.Site || p.Layer || "IUPK",
          meta: (p.Layer || "") + (p.Luas != null ? " · " + Number(p.Luas).toLocaleString("id-ID", { maximumFractionDigits: 0 }) + " ha" : ""),
          badge: p.Layer || "IUPK",
          cls: "rendah",
          layer: entry.layer
        });
      });
    }
    if (scope === "semua" || scope === "besigma") {
      (besigmaGeo.features || []).forEach(function (feature, index) {
        var p = feature.properties || {};
        items.push({
          kind: "besigma",
          title: p.name || p.title || p.code || ("Boundary #" + (p.id || index + 1)),
          meta: p.status_name || p.risk_name || "Besigma",
          badge: p.status_name || p.risk_name || "DB",
          cls: "internal",
          layer: null,
          index: index
        });
      });
    }

    countEl.textContent = String(items.length);
    listEl.innerHTML = "";
    items.forEach(function (item) {
      var btn = document.createElement("button");
      btn.type = "button";
      btn.className = "hotspot-item";
      btn.innerHTML =
        '<span class="pin-mini ' + item.cls + '"><i></i></span>' +
        '<span class="copy"><b>' + esc(item.title) + '</b><span class="meta">' + esc(item.meta) + "</span></span>" +
        '<span class="badge ' + item.cls + '">' + esc(item.badge) + "</span>";
      btn.addEventListener("click", function () {
        listEl.querySelectorAll(".hotspot-item").forEach(function (el) { el.classList.remove("is-on"); });
        btn.classList.add("is-on");
        if (item.layer && item.layer.getBounds) {
          map.fitBounds(item.layer.getBounds(), { padding: [40, 40], maxZoom: 13 });
        } else if (item.kind === "besigma") {
          var layer = besigmaLayer.getLayers()[item.index];
          if (layer && layer.getBounds) {
            map.fitBounds(layer.getBounds(), { padding: [40, 40], maxZoom: 13 });
          } else if (layer && layer.getLatLng) {
            map.setView(layer.getLatLng(), 14);
          }
        }
        showDetail(item.title, item.meta);
      });
      listEl.appendChild(btn);
    });
  }

  function showDetail(title, meta) {
    if (!detailCard || !detailEmpty) {
      return;
    }
    detailEmpty.hidden = true;
    detailCard.hidden = false;
    detailCard.innerHTML = "<small>DETAIL ZONA</small><p><strong>" + esc(title) + "</strong><br>" + esc(meta) + "</p>";
  }

  function loadBesigma() {
    if (!boundariesUrl) {
      hideLoading();
      return;
    }
    fetch(boundariesUrl, { headers: { Accept: "application/json" } })
      .then(function (res) { return res.json(); })
      .then(function (geojson) {
        besigmaGeo = geojson && geojson.features ? geojson : { type: "FeatureCollection", features: [] };
        besigmaLayer.clearLayers();
        if (besigmaGeo.features.length) {
          besigmaLayer.addData(besigmaGeo);
        }
        if (sumBesigma) {
          sumBesigma.textContent = String(besigmaGeo.features.length);
        }
        applyScope();
        hideLoading();
      })
      .catch(function () {
        hideLoading();
      });

    if (!overlayUrl) {
      return;
    }
    fetch(overlayUrl, { headers: { Accept: "application/json" } })
      .then(function (res) { return res.json(); })
      .then(function (payload) {
        overlay = (payload && payload.overlay) || overlay;
        if (sumViolations) {
          sumViolations.textContent = String((overlay.violations || []).length);
        }
        if (sumEntries) {
          sumEntries.textContent = String((overlay.entries || []).length);
        }
        if (liveStatus && payload && payload.connected) {
          liveStatus.textContent = "Besigma terhubung.";
        }
      })
      .catch(function () {});
  }

  if (sumIupk) {
    sumIupk.textContent = String(iupkLayers.length);
  }
  if (countEl) {
    countEl.textContent = String(iupkLayers.length);
  }
  if (listLabel) {
    listLabel.textContent = "zona tampil";
  }

  fitOps();
  renderList();
  loadBesigma();
  setTimeout(hideLoading, 1200);

  document.querySelectorAll("[data-scope]").forEach(function (btn) {
    btn.addEventListener("click", function () {
      document.querySelectorAll("[data-scope]").forEach(function (el) { el.classList.remove("is-on"); });
      btn.classList.add("is-on");
      scope = btn.getAttribute("data-scope") || "semua";
      applyScope();
    });
  });

  document.querySelectorAll("[data-basemap]").forEach(function (btn) {
    btn.addEventListener("click", function () {
      document.querySelectorAll("[data-basemap]").forEach(function (el) { el.classList.remove("is-on"); });
      btn.classList.add("is-on");
      var kind = btn.getAttribute("data-basemap");
      if (kind === "dark") {
        if (!map.hasLayer(dark)) {
          dark.addTo(map);
        }
        if (map.hasLayer(sat)) {
          map.removeLayer(sat);
        }
      } else {
        if (!map.hasLayer(sat)) {
          sat.addTo(map);
        }
        if (map.hasLayer(dark)) {
          map.removeLayer(dark);
        }
      }
    });
  });

  document.querySelectorAll("[data-layer]").forEach(function (btn) {
    btn.addEventListener("click", function () {
      var layer = btn.getAttribute("data-layer");
      btn.classList.toggle("is-on");
      if (layer === "ops") {
        showOps = btn.classList.contains("is-on");
      }
      if (layer === "besigma") {
        showBesigma = btn.classList.contains("is-on");
      }
      applyScope();
    });
  });

  var zoomIn = document.getElementById("zoom-in");
  var zoomOut = document.getElementById("zoom-out");
  var zoomFit = document.getElementById("zoom-fit");
  if (zoomIn) {
    zoomIn.addEventListener("click", function () { map.zoomIn(); });
  }
  if (zoomOut) {
    zoomOut.addEventListener("click", function () { map.zoomOut(); });
  }
  if (zoomFit) {
    zoomFit.addEventListener("click", fitOps);
  }

  var refreshBtn = document.getElementById("btn-refresh");
  if (refreshBtn) {
    refreshBtn.addEventListener("click", function () {
      if (loadingEl) {
        loadingEl.hidden = false;
      }
      loadBesigma();
    });
  }

  window.addEventListener("resize", function () {
    map.invalidateSize();
  });
})();
