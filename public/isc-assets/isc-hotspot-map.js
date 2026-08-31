(function () {
  var mapEl = document.getElementById("map");
  if (!mapEl || !window.L) {
    return;
  }

  var boundariesUrl = mapEl.getAttribute("data-boundaries-url") || "";
  var overlayUrl = mapEl.getAttribute("data-overlay-url") || "";

  var listEl = document.getElementById("zone-list");
  var countEl = document.getElementById("gm-count");
  var loadingEl = document.getElementById("map-loading");
  var liveStatus = document.getElementById("gm-status");
  var panel = document.getElementById("gm-panel");
  var resultsEl = document.getElementById("gm-results");
  var placeEl = document.getElementById("gm-place");
  var placeTitle = document.getElementById("gm-place-title");
  var placeSub = document.getElementById("gm-place-sub");
  var placeHero = document.getElementById("gm-place-hero");
  var placeFacts = document.getElementById("gm-place-facts");
  var placeData = document.getElementById("gm-place-data");
  var searchInput = document.getElementById("gm-search-input");
  var searchClear = document.getElementById("gm-search-clear");
  var layersBtn = document.getElementById("gm-layers-btn");
  var layersPop = document.getElementById("gm-layers-pop");
  var layersThumb = document.getElementById("gm-layers-thumb");
  var saveLabel = document.getElementById("gm-save-label");
  var toastEl = document.getElementById("gm-toast");
  var toastTimer = 0;

  var scope = "semua";
  var query = "";
  var listMode = "all";
  var showOps = true;
  var showBesigma = true;
  var showLabels = true;
  var selected = null;
  var recents = [];
  var saved = loadSaved();
  var iupkLayers = [];
  var besigmaGeo = { type: "FeatureCollection", features: [] };
  var overlay = { violations: [], entries: [] };

  var sat = L.tileLayer("https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}", {
    maxZoom: 18
  });
  var osm = L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
    maxZoom: 19
  });
  var dark = L.tileLayer("https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png", {
    maxZoom: 18
  });
  var basemaps = { sat: sat, map: osm, dark: dark };

  var map = L.map("map", {
    center: [2.08, 117.42],
    zoom: 10,
    layers: [sat],
    zoomControl: false,
    attributionControl: true
  });

  L.control.scale({ imperial: false, position: "bottomright" }).addTo(map);

  map.createPane("iupk");
  map.getPane("iupk").style.zIndex = 450;
  map.createPane("labels");
  map.getPane("labels").style.zIndex = 650;
  map.getPane("labels").style.pointerEvents = "none";

  var iupkRenderer = L.canvas({ pane: "iupk", padding: 0.8 });
  var labelsLayer = L.layerGroup();

  function iupkStyle() {
    return { color: "#d4ff7a", weight: 3, fillColor: "#7cb342", fillOpacity: 0.2 };
  }

  function iupkHover() {
    return { color: "#fff59d", weight: 4.5, fillColor: "#9ccc65", fillOpacity: 0.32 };
  }

  function iupkSelected() {
    return { color: "#1a73e8", weight: 5, fillColor: "#1a73e8", fillOpacity: 0.28 };
  }

  var opsLayer = L.geoJSON(window.IUPK_BOUNDARY || { type: "FeatureCollection", features: [] }, {
    pane: "iupk",
    renderer: iupkRenderer,
    style: iupkStyle,
    onEachFeature: function (feature, layer) {
      var p = feature.properties || {};
      var luas = formatHa(p.Luas);
      var title = [p.Site, p.Layer].filter(Boolean).join(" · ");
      layer.bindTooltip((title || "Konsesi IUPK") + (luas ? " · " + luas : ""), { sticky: true, className: "iupk-tip" });
      layer.on("click", function () {
        openPlace(itemFromIupk(feature, layer));
      });
      layer.on("mouseover", function () {
        if (!selected || selected.layer !== layer) {
          layer.setStyle(iupkHover());
        }
      });
      layer.on("mouseout", function () {
        paintSelection();
      });
      iupkLayers.push({ feature: feature, layer: layer });
    }
  });

  var besigmaLayer = L.geoJSON({ type: "FeatureCollection", features: [] }, {
    style: {
      color: "#1a73e8",
      weight: 2,
      fillColor: "#1a73e8",
      fillOpacity: 0.16
    },
    pointToLayer: function (feature, latlng) {
      return L.circleMarker(latlng, { radius: 7, color: "#1a73e8", fillOpacity: 0.85, weight: 2 });
    },
    onEachFeature: function (feature, layer) {
      var p = feature.properties || {};
      var title = p.name || p.title || p.code || ("Boundary #" + (p.id || ""));
      layer.bindTooltip(String(title), { sticky: true, className: "iupk-tip" });
      layer.on("click", function () {
        openPlace(itemFromBesigma(feature, layer));
      });
    }
  });

  opsLayer.addTo(map);

  function loadSaved() {
    try {
      return JSON.parse(localStorage.getItem("isc-maps-saved") || "[]");
    } catch (e) {
      return [];
    }
  }

  function persistSaved() {
    localStorage.setItem("isc-maps-saved", JSON.stringify(saved.slice(0, 40)));
  }

  function hideLoading() {
    if (loadingEl) {
      loadingEl.hidden = true;
    }
  }

  function formatHa(value) {
    if (value == null || value === "") {
      return "";
    }
    return Number(value).toLocaleString("id-ID", { maximumFractionDigits: 0 }) + " ha";
  }

  function fitOps() {
    if (opsLayer.getLayers().length) {
      map.fitBounds(opsLayer.getBounds(), { padding: [56, 56] });
    }
  }

  function esc(value) {
    return String(value == null || value === "" ? "—" : value)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
  }

  function toast(message) {
    if (!toastEl) {
      return;
    }
    toastEl.textContent = message;
    toastEl.hidden = false;
    window.clearTimeout(toastTimer);
    toastTimer = window.setTimeout(function () {
      toastEl.hidden = true;
    }, 1800);
  }

  function itemKey(item) {
    return item.kind + ":" + item.title;
  }

  function itemFromIupk(feature, layer) {
    var p = feature.properties || {};
    return {
      kind: "iupk",
      title: p.Site || p.Layer || "IUPK",
      meta: [p.Layer, formatHa(p.Luas)].filter(Boolean).join(" · "),
      badge: p.Layer || "IUPK",
      props: p,
      layer: layer
    };
  }

  function itemFromBesigma(feature, layer) {
    var p = feature.properties || {};
    return {
      kind: "besigma",
      title: p.name || p.title || p.code || ("Boundary #" + (p.id || "")),
      meta: p.status_name || p.risk_name || "Besigma",
      badge: p.status_name || p.risk_name || "DB",
      props: p,
      layer: layer
    };
  }

  function allItems() {
    var items = [];
    iupkLayers.forEach(function (entry) {
      items.push(itemFromIupk(entry.feature, entry.layer));
    });
    (besigmaGeo.features || []).forEach(function (feature, index) {
      items.push(itemFromBesigma(feature, besigmaLayer.getLayers()[index] || null));
    });
    return items;
  }

  function visibleItems() {
    var q = query.trim().toLowerCase();
    return allItems().filter(function (item) {
      if (scope === "iupk" && item.kind !== "iupk") {
        return false;
      }
      if (scope === "besigma" && item.kind !== "besigma") {
        return false;
      }
      if (listMode === "saved" && saved.indexOf(itemKey(item)) === -1) {
        return false;
      }
      if (listMode === "recents" && recents.indexOf(itemKey(item)) === -1) {
        return false;
      }
      if (!q) {
        return true;
      }
      return (item.title + " " + item.meta + " " + item.badge).toLowerCase().indexOf(q) !== -1;
    });
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

    if (showLabels) {
      if (!map.hasLayer(labelsLayer)) {
        labelsLayer.addTo(map);
      }
    } else if (map.hasLayer(labelsLayer)) {
      map.removeLayer(labelsLayer);
    }

    paintSelection();
    renderList();
  }

  function paintSelection() {
    opsLayer.eachLayer(function (layer) {
      layer.setStyle(selected && selected.layer === layer ? iupkSelected() : iupkStyle());
    });
    besigmaLayer.eachLayer(function (layer) {
      var on = selected && selected.layer === layer;
      if (layer.setStyle) {
        layer.setStyle({
          color: on ? "#174ea6" : "#1a73e8",
          weight: on ? 4 : 2,
          fillColor: on ? "#174ea6" : "#1a73e8",
          fillOpacity: on ? 0.32 : 0.16
        });
      }
    });
  }

  function addLabels() {
    labelsLayer.clearLayers();
    iupkLayers.forEach(function (entry) {
      if (!entry.layer.getBounds) {
        return;
      }
      var center = entry.layer.getBounds().getCenter();
      var name = (entry.feature.properties || {}).Layer || (entry.feature.properties || {}).Site || "IUPK";
      L.marker(center, {
        pane: "labels",
        interactive: false,
        keyboard: false,
        icon: L.divIcon({
          className: "gm-label-wrap",
          html: '<span class="gm-label">' + esc(name) + "</span>",
          iconSize: [0, 0],
          iconAnchor: [0, 0]
        })
      }).addTo(labelsLayer);
    });
    if (showLabels) {
      labelsLayer.addTo(map);
    }
  }

  function pinSvg() {
    return '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21s7-6.2 7-11a7 7 0 1 0-14 0c0 4.8 7 11 7 11z"/></svg>';
  }

  function renderList() {
    if (!listEl) {
      return;
    }
    var items = visibleItems();
    if (countEl) {
      countEl.textContent = String(items.length) + " tempat";
    }
    listEl.innerHTML = "";
    if (!items.length) {
      listEl.innerHTML = '<p class="gm-item"><span class="copy"><b>Tidak ada hasil</b><span class="meta">Ubah kata kunci atau filter.</span></span></p>';
      return;
    }
    items.forEach(function (item) {
      var btn = document.createElement("button");
      btn.type = "button";
      btn.className = "gm-item" + (selected && itemKey(selected) === itemKey(item) ? " is-on" : "");
      btn.innerHTML =
        '<span class="gm-pin ' + item.kind + '">' + pinSvg() + "</span>" +
        '<span class="copy"><b>' + esc(item.title) + '</b><span class="meta">' + esc(item.meta) + "</span></span>";
      btn.addEventListener("click", function () {
        openPlace(item);
      });
      listEl.appendChild(btn);
    });
  }

  function flyToItem(item) {
    if (!item || !item.layer) {
      return;
    }
    if (item.layer.getBounds) {
      map.fitBounds(item.layer.getBounds(), { padding: [72, 72], maxZoom: 13 });
    } else if (item.layer.getLatLng) {
      map.setView(item.layer.getLatLng(), 14);
    }
  }

  function syncSaveButton() {
    var act = document.getElementById("gm-act-save");
    if (!act || !selected) {
      return;
    }
    var on = saved.indexOf(itemKey(selected)) !== -1;
    act.classList.toggle("is-saved", on);
    if (saveLabel) {
      saveLabel.textContent = on ? "Tersimpan" : "Simpan";
    }
  }

  function openPlace(item) {
    selected = item;
    recents = [itemKey(item)].concat(recents.filter(function (key) { return key !== itemKey(item); })).slice(0, 12);
    openPanel();
    if (resultsEl) {
      resultsEl.hidden = true;
    }
    if (placeEl) {
      placeEl.hidden = false;
    }
    if (searchInput) {
      searchInput.value = item.title;
    }
    if (searchClear) {
      searchClear.hidden = false;
    }
    if (placeTitle) {
      placeTitle.textContent = item.title;
    }
    if (placeSub) {
      placeSub.textContent = (item.kind === "iupk" ? "Konsesi IUPK · " : "Boundary Besigma · ") + item.meta;
    }
    if (placeHero) {
      placeHero.textContent = item.badge || (item.kind === "iupk" ? "IUPK" : "DB");
      placeHero.setAttribute("data-site", item.badge || "IUPK");
    }
    if (placeFacts) {
      var p = item.props || {};
      placeFacts.innerHTML =
        fact("pin", item.title, "Site / nama") +
        fact("tag", item.badge, "Layer / status") +
        fact("area", formatHa(p.Luas) || "—", "Luas") +
        fact("db", item.kind === "iupk" ? "BounderyBC.js" : "Besigma", "Sumber") +
        fact("alert", String((overlay.violations || []).length), "Pelanggaran") +
        fact("in", String((overlay.entries || []).length), "Entry");
    }
    if (placeData) {
      placeData.textContent = JSON.stringify(item.props || {}, null, 2);
    }
    setTab("overview");
    syncSaveButton();
    paintSelection();
    renderList();
    flyToItem(item);
  }

  function fact(kind, value, label) {
    var icons = {
      pin: "<path d='M12 21s7-6.2 7-11a7 7 0 1 0-14 0c0 4.8 7 11 7 11z'/>",
      tag: "<path d='M4 8h16M4 12h10M4 16h13'/>",
      area: "<path d='M4 7h16v12H4z'/>",
      db: "<ellipse cx='12' cy='6' rx='7' ry='3'/><path d='M5 6v8c0 1.7 3.1 3 7 3s7-1.3 7-3V6'/>",
      alert: "<path d='M12 4 21 19H3L12 4z'/><path d='M12 10v4M12 16v.5'/>",
      in: "<path d='M12 4v12'/><path d='m7 11 5 5 5-5'/>"
    };
    return (
      "<li><svg viewBox='0 0 24 24'>" + (icons[kind] || icons.tag) + "</svg>" +
      "<div>" + esc(value) + "<small>" + esc(label) + "</small></div></li>"
    );
  }

  function closePlace() {
    selected = null;
    if (resultsEl) {
      resultsEl.hidden = false;
    }
    if (placeEl) {
      placeEl.hidden = true;
    }
    paintSelection();
    renderList();
  }

  function syncShell() {
    var shell = document.querySelector(".gm-shell");
    if (shell) {
      shell.classList.toggle("is-panel-closed", !!(panel && panel.classList.contains("is-closed")));
    }
  }

  function openPanel() {
    if (panel) {
      panel.classList.remove("is-closed");
    }
    var toggle = document.getElementById("gm-rail-toggle");
    if (toggle) {
      toggle.classList.add("is-on");
      toggle.setAttribute("aria-expanded", "true");
    }
    syncShell();
  }

  function togglePanel() {
    if (!panel) {
      return;
    }
    panel.classList.toggle("is-closed");
    var closed = panel.classList.contains("is-closed");
    var toggle = document.getElementById("gm-rail-toggle");
    if (toggle) {
      toggle.classList.toggle("is-on", !closed);
      toggle.setAttribute("aria-expanded", closed ? "false" : "true");
    }
    syncShell();
  }

  function jumpToLayer(code) {
    var match = iupkLayers.find(function (entry) {
      var layer = String((entry.feature.properties || {}).Layer || "");
      return layer === code || layer.indexOf(code) === 0;
    });
    if (match) {
      openPlace(itemFromIupk(match.feature, match.layer));
    }
  }

  function setBasemap(kind) {
    Object.keys(basemaps).forEach(function (key) {
      if (key === kind) {
        if (!map.hasLayer(basemaps[key])) {
          basemaps[key].addTo(map);
        }
      } else if (map.hasLayer(basemaps[key])) {
        map.removeLayer(basemaps[key]);
      }
    });
    document.querySelectorAll("[data-basemap]").forEach(function (el) {
      el.classList.toggle("is-on", el.getAttribute("data-basemap") === kind);
    });
    if (layersThumb) {
      layersThumb.setAttribute("data-kind", kind);
    }
  }

  function setTab(name) {
    document.querySelectorAll("[data-tab]").forEach(function (el) {
      el.classList.toggle("is-on", el.getAttribute("data-tab") === name);
    });
    if (placeFacts) {
      placeFacts.hidden = name !== "overview";
    }
    if (placeData) {
      placeData.hidden = name !== "about";
    }
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
        if (liveStatus && payload && payload.connected) {
          liveStatus.textContent = "Besigma terhubung";
        }
      })
      .catch(function () {});
  }

  addLabels();
  fitOps();
  renderList();
  loadBesigma();
  setTimeout(hideLoading, 1200);

  document.getElementById("gm-search-form").addEventListener("submit", function (event) {
    event.preventDefault();
    query = searchInput.value || "";
    listMode = "all";
    openPanel();
    closePlace();
    renderList();
    var first = visibleItems()[0];
    if (first) {
      openPlace(first);
    }
  });

  searchInput.addEventListener("focus", function () {
    if (!selected) {
      openPanel();
    }
  });

  searchInput.addEventListener("input", function () {
    query = searchInput.value || "";
    if (searchClear) {
      searchClear.hidden = query === "";
    }
    listMode = "all";
    openPanel();
    if (placeEl && !placeEl.hidden && query === "") {
      closePlace();
    }
    renderList();
  });

  if (searchClear) {
    searchClear.addEventListener("click", function () {
      searchInput.value = "";
      query = "";
      searchClear.hidden = true;
      closePlace();
      renderList();
    });
  }

  document.querySelectorAll("[data-scope]").forEach(function (btn) {
    btn.addEventListener("click", function () {
      document.querySelectorAll("[data-scope]").forEach(function (el) { el.classList.remove("is-on"); });
      btn.classList.add("is-on");
      scope = btn.getAttribute("data-scope") || "semua";
      listMode = "all";
      applyScope();
    });
  });

  document.querySelectorAll("[data-jump]").forEach(function (btn) {
    btn.addEventListener("click", function () {
      jumpToLayer(btn.getAttribute("data-jump") || "");
    });
  });

  document.querySelectorAll("[data-basemap]").forEach(function (btn) {
    btn.addEventListener("click", function () {
      setBasemap(btn.getAttribute("data-basemap") || "sat");
    });
  });

  document.querySelectorAll("[data-layer]").forEach(function (input) {
    input.addEventListener("change", function () {
      var layer = input.getAttribute("data-layer");
      if (layer === "ops") {
        showOps = input.checked;
      }
      if (layer === "besigma") {
        showBesigma = input.checked;
      }
      applyScope();
    });
  });

  var labelsToggle = document.getElementById("gm-toggle-labels");
  if (labelsToggle) {
    labelsToggle.addEventListener("change", function () {
      showLabels = labelsToggle.checked;
      applyScope();
    });
  }

  document.querySelectorAll("[data-tab]").forEach(function (btn) {
    btn.addEventListener("click", function () {
      setTab(btn.getAttribute("data-tab") || "overview");
    });
  });

  document.getElementById("gm-rail-toggle").addEventListener("click", togglePanel);
  document.getElementById("gm-place-back").addEventListener("click", function () {
    if (searchInput) {
      searchInput.value = query;
    }
    closePlace();
  });

  document.getElementById("gm-saved-btn").addEventListener("click", function () {
    listMode = "saved";
    openPanel();
    closePlace();
    renderList();
  });

  document.getElementById("gm-recents-btn").addEventListener("click", function () {
    listMode = "recents";
    openPanel();
    closePlace();
    renderList();
  });

  document.getElementById("gm-act-zoom").addEventListener("click", function () {
    flyToItem(selected);
  });

  document.getElementById("gm-act-nearby").addEventListener("click", fitOps);

  document.getElementById("gm-act-save").addEventListener("click", function () {
    if (!selected) {
      return;
    }
    var key = itemKey(selected);
    if (saved.indexOf(key) === -1) {
      saved.unshift(key);
      toast("Disimpan ke daftar.");
    } else {
      saved = saved.filter(function (item) { return item !== key; });
      toast("Dihapus dari daftar.");
    }
    persistSaved();
    syncSaveButton();
  });

  document.getElementById("gm-act-share").addEventListener("click", function () {
    if (!selected) {
      return;
    }
    var text = selected.title + " — " + selected.meta;
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text);
      toast("Nama zona disalin.");
    }
  });

  if (layersBtn && layersPop) {
    layersBtn.addEventListener("click", function () {
      layersPop.hidden = !layersPop.hidden;
      layersBtn.setAttribute("aria-expanded", layersPop.hidden ? "false" : "true");
    });
    document.addEventListener("click", function (event) {
      if (!layersPop.hidden && !event.target.closest(".gm-layers")) {
        layersPop.hidden = true;
        layersBtn.setAttribute("aria-expanded", "false");
      }
    });
  }

  var zoomIn = document.getElementById("zoom-in");
  var zoomOut = document.getElementById("zoom-out");
  var zoomFit = document.getElementById("zoom-fit");
  var zoomHome = document.getElementById("zoom-home");
  if (zoomIn) {
    zoomIn.addEventListener("click", function () { map.zoomIn(); });
  }
  if (zoomOut) {
    zoomOut.addEventListener("click", function () { map.zoomOut(); });
  }
  if (zoomFit) {
    zoomFit.addEventListener("click", fitOps);
  }
  if (zoomHome) {
    zoomHome.addEventListener("click", fitOps);
  }

  var refreshBtn = document.getElementById("btn-refresh");
  if (refreshBtn) {
    refreshBtn.addEventListener("click", function () {
      if (loadingEl) {
        loadingEl.hidden = false;
      }
      loadBesigma();
      toast("Memuat data Besigma…");
    });
  }

  window.addEventListener("resize", function () {
    map.invalidateSize();
  });
})();
