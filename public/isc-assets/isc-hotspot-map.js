(function () {
  var mapEl = document.getElementById("map");
  if (!mapEl || !window.L) {
    return;
  }

  var boundariesUrl = mapEl.getAttribute("data-boundaries-url") || "";
  var overlayUrl = mapEl.getAttribute("data-overlay-url") || "";
  var pobUrl = mapEl.getAttribute("data-pob-url") || "";
  var interventionsUrl = mapEl.getAttribute("data-interventions-url") || "";

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
  var showPeople = true;
  var showHazard = true;
  var showLabels = true;
  var selected = null;
  var recents = [];
  var saved = loadSaved();
  var iupkLayers = [];
  var besigmaGeo = { type: "FeatureCollection", features: [] };
  var besigmaRecords = [];
  var overlay = { violations: [], entries: [] };
  var pobPeople = [];
  var pobSource = "demo";
  var peopleByKey = {};
  var pobCheckins = [];
  var pobSummary = {};
  var hudSite = "";
  var rosterFilter = { type: "", site: "", safety: "", kind: "" };

  var sgiAttribution = mapEl.getAttribute("data-wmts-attribution") || "Drone Imagery © SGI";
  var wmsUrl = mapEl.getAttribute("data-wms-url") || "";
  var wmsLayerName = mapEl.getAttribute("data-wms-layer") || "basemap:basemap_allsite";
  var wmtsProxyUrl = mapEl.getAttribute("data-wmts-proxy-url") || "";
  var sgiOpts = {
    attribution: sgiAttribution,
    maxZoom: 22,
    minZoom: 5,
    opacity: 1
  };
  var sgi = wmsUrl
    ? L.tileLayer.wms(wmsUrl, Object.assign({}, sgiOpts, {
      layers: wmsLayerName,
      format: "image/png",
      transparent: true,
      version: "1.1.1",
      uppercase: true,
      tiled: true
    }))
    : L.tileLayer(wmtsProxyUrl, Object.assign({}, sgiOpts, {
      maxNativeZoom: 22,
      tms: false
    }));
  var sgiTileErrors = 0;
  sgi.on("tileerror", function () {
    sgiTileErrors += 1;
    if (sgiTileErrors === 4) {
      toast("Basemap drone SGI gagal dimuat. Coba jaringan internal atau ganti ke Peta/Gelap.");
    }
  });
  var osm = L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
    maxZoom: 22,
    maxNativeZoom: 19,
    minZoom: 5
  });
  var dark = L.tileLayer("https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png", {
    maxZoom: 22,
    maxNativeZoom: 18,
    minZoom: 5
  });
  var basemaps = { sgi: sgi, map: osm, dark: dark };

  var map = L.map("map", {
    center: [2.08, 117.42],
    zoom: 10,
    minZoom: 5,
    maxZoom: 22,
    layers: [sgi],
    zoomControl: false,
    attributionControl: true
  });

  L.control.scale({ imperial: false, position: "bottomright" }).addTo(map);

  map.createPane("iupk");
  map.getPane("iupk").style.zIndex = 450;
  map.createPane("hazard");
  map.getPane("hazard").style.zIndex = 470;
  map.createPane("people");
  map.getPane("people").style.zIndex = 620;
  map.createPane("labels");
  map.getPane("labels").style.zIndex = 650;
  map.getPane("labels").style.pointerEvents = "none";

  var iupkRenderer = L.canvas({ pane: "iupk", padding: 0.8 });
  var labelsLayer = L.layerGroup();
  var peopleLayer = L.layerGroup();
  var hazardLayer = L.geoJSON(null, {
    pane: "hazard",
    style: function (feature) {
      var kind = ((feature && feature.properties) || {}).hazard_kind || "";
      var color = kind === "employee_competence" ? "#e37400" : (kind === "unit_danger" ? "#7627bb" : "#c5221f");
      return { color: color, weight: 2, fillColor: color, fillOpacity: 0.28 };
    },
    onEachFeature: function (feature, layer) {
      var p = feature.properties || {};
      var title = p.hazard_kind_label || p.name || "Zona berbahaya";
      layer.bindTooltip(title, { sticky: true, className: "iupk-tip" });
      layer.on("click", function () {
        openPlace({
          kind: "hazard",
          title: p.name || "Zona berbahaya",
          meta: [p.aktivitas, p.hazard_kind_label || p.risk_name].filter(Boolean).join(" · ") || "Zona bahaya",
          badge: p.hazard_kind_label || "UNSAFE",
          props: p,
          layer: layer
        });
      });
    }
  });

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

  function besigmaColor(props) {
    return props.risk_color || props.status_color || "#1a73e8";
  }

  function besigmaStyle(feature) {
    var color = besigmaColor((feature && feature.properties) || {});
    return {
      color: color,
      weight: 2.5,
      fillColor: color,
      fillOpacity: 0.22
    };
  }

  var besigmaLayer = L.geoJSON({ type: "FeatureCollection", features: [] }, {
    style: besigmaStyle,
    pointToLayer: function (feature, latlng) {
      var color = besigmaColor((feature && feature.properties) || {});
      return L.circleMarker(latlng, { radius: 8, color: color, fillColor: color, fillOpacity: 0.9, weight: 2 });
    },
    onEachFeature: function (feature, layer) {
      var item = itemFromBesigma(feature.properties || {}, layer);
      layer.bindTooltip(item.title, { sticky: true, className: "iupk-tip" });
      layer.on("click", function () {
        openPlace(item);
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

  function formatDuration(seconds) {
    var n = Number(seconds);
    if (!isFinite(n) || n < 0) {
      return "—";
    }
    var m = Math.floor(n / 60);
    var s = Math.floor(n % 60);
    if (m >= 60) {
      var h = Math.floor(m / 60);
      m = m % 60;
      return h + "j " + m + "m";
    }
    return m + "m " + s + "d";
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
    if (item.kind === "person" && item.props && item.props.key) {
      return "person:" + item.props.key;
    }
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

  function siteCodeOf(record) {
    return String((record && (record.site_code || record.iupk_site_code)) || "");
  }

  function matchesHudSite(record) {
    return !hudSite || siteCodeOf(record) === hudSite;
  }

  function itemFromCheckin(row) {
    var p = row || {};
    return {
      kind: "person",
      title: p.name || p.sid || "Check-in",
      meta: [p.company, p.site_label || p.gate, "RFID"].filter(Boolean).join(" · "),
      badge: "IN",
      props: Object.assign({ presence: "in", safety: null }, p),
      layer: peopleByKey[p.key] || peopleByKey["sid:" + (p.sid || "")] || null
    };
  }

  function itemFromPerson(person, layer) {
    var p = person || {};
    return {
      kind: "person",
      title: p.name || p.sid || "Personel",
      meta: [p.company, p.job_title, p.iupk_site || p.presence].filter(Boolean).join(" · "),
      badge: p.safety === "unsafe" ? (p.hazard_kind_label || "UNSAFE") : (p.presence === "in" ? "IN" : (p.stale ? "STALE" : "OUT")),
      props: p,
      layer: layer || peopleByKey[p.key] || null
    };
  }

  function itemFromBesigma(props, layer) {
    var p = props || {};
    return {
      kind: "besigma",
      title: p.name || p.title || p.code || p.label || ("Boundary #" + (p.id || "")),
      meta: [p.hazard_kind_label, p.status_name, p.type, p.site_label || p.site_code || p.site_name, p.pit_name].filter(Boolean).join(" · ") || "Besigma",
      badge: p.hazard_kind_label || p.status_name || p.type || "DB",
      props: p,
      layer: layer || layerForBesigmaId(p.id)
    };
  }

  function layerForBesigmaId(id) {
    if (id == null) {
      return null;
    }
    var found = null;
    besigmaLayer.eachLayer(function (layer) {
      if (found) {
        return;
      }
      var props = (layer.feature && layer.feature.properties) || {};
      if (String(props.id) === String(id)) {
        found = layer;
      }
    });
    return found;
  }

  function allItems() {
    var items = [];
    iupkLayers.forEach(function (entry) {
      items.push(itemFromIupk(entry.feature, entry.layer));
    });
    var source = besigmaRecords.length ? besigmaRecords : (besigmaGeo.features || []).map(function (feature) {
      return feature.properties || {};
    });
    source.forEach(function (props) {
      items.push(itemFromBesigma(props, layerForBesigmaId(props.id)));
    });
    pobPeople.forEach(function (person) {
      items.push(itemFromPerson(person, peopleByKey[person.key]));
    });
    return items;
  }

  function visibleItems() {
    var q = query.trim().toLowerCase();
    if (listMode === "roster") {
      return rosterItems().filter(function (item) {
        if (!q) {
          return true;
        }
        return (item.title + " " + item.meta + " " + item.badge).toLowerCase().indexOf(q) !== -1;
      });
    }
    return allItems().filter(function (item) {
      if (scope === "iupk" && item.kind !== "iupk") {
        return false;
      }
      if (scope === "besigma" && item.kind !== "besigma") {
        return false;
      }
      if (scope === "people" && item.kind !== "person") {
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

    if (showPeople && (scope === "semua" || scope === "people")) {
      if (!map.hasLayer(peopleLayer)) {
        peopleLayer.addTo(map);
      }
    } else if (map.hasLayer(peopleLayer)) {
      map.removeLayer(peopleLayer);
    }

    if (showHazard && (scope === "semua" || scope === "people" || scope === "iupk")) {
      if (!map.hasLayer(hazardLayer)) {
        hazardLayer.addTo(map);
      }
    } else if (map.hasLayer(hazardLayer)) {
      map.removeLayer(hazardLayer);
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
      addLabelForLayer(entry.layer, (entry.feature.properties || {}).Layer || (entry.feature.properties || {}).Site || "IUPK");
    });
    besigmaLayer.eachLayer(function (layer) {
      var p = (layer.feature && layer.feature.properties) || {};
      addLabelForLayer(layer, p.name || p.title || p.code || p.Layer || "Boundary");
    });
    if (showLabels) {
      labelsLayer.addTo(map);
    }
  }

  function addLabelForLayer(layer, name) {
    var center = null;
    if (layer.getBounds) {
      center = layer.getBounds().getCenter();
    } else if (layer.getLatLng) {
      center = layer.getLatLng();
    }
    if (!center) {
      return;
    }
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
  }

  function chipsHtml(item) {
    if (item.kind === "person") {
      var person = item.props || {};
      var chips = [];
      if (person.presence) {
        chips.push('<span class="gm-chip">' + esc(person.presence) + "</span>");
      }
      if (person.safety) {
        chips.push('<span class="gm-chip ' + (person.safety === "unsafe" ? "alert" : "") + '">' + esc(person.hazard_kind_label || person.safety) + "</span>");
      }
      return chips.length ? '<span class="gm-chips">' + chips.join("") + "</span>" : "";
    }
    if (item.kind !== "besigma") {
      return "";
    }
    var p = item.props || {};
    var chips = [];
    if (p.status_name) {
      chips.push('<span class="gm-chip">' + esc(p.status_name) + "</span>");
    }
    if (p.risk_name) {
      chips.push('<span class="gm-chip risk">' + esc(p.risk_name) + "</span>");
    }
    if (p.violations_count) {
      chips.push('<span class="gm-chip alert">' + esc(p.violations_count) + " pelanggaran</span>");
    }
    if (p.entries_count) {
      chips.push('<span class="gm-chip">' + esc(p.entries_count) + " entry</span>");
    }
    if (!p.has_geometry) {
      chips.push('<span class="gm-chip muted">tanpa geometri</span>');
    }
    return chips.length ? '<span class="gm-chips">' + chips.join("") + "</span>" : "";
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
      countEl.textContent = String(items.length) + (listMode === "roster" ? " orang" : " tempat");
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
        '<span class="copy"><b>' + esc(item.title) + '</b><span class="meta">' + esc(item.meta) + "</span>" +
        chipsHtml(item) + "</span>";
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
      var kindLabel = item.kind === "iupk"
        ? "Konsesi IUPK · "
        : (item.kind === "person" ? "Personel · " : (item.kind === "hazard" ? "Zona berbahaya · " : "Boundary Besigma · "));
      placeSub.textContent = kindLabel + item.meta;
    }
    if (placeHero) {
      placeHero.textContent = item.badge || (item.kind === "iupk" ? "IUPK" : "DB");
      placeHero.setAttribute("data-site", item.badge || "IUPK");
    }
    if (placeFacts) {
      placeFacts.innerHTML = factsFromProps(item);
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

  function factsFromProps(item) {
    var p = item.props || {};
    var rows = [];
    if (item.kind === "iupk") {
      rows.push(fact("pin", item.title, "Site / nama"));
      rows.push(fact("tag", item.badge, "Layer / status"));
      rows.push(fact("area", formatHa(p.Luas) || "—", "Luas"));
      rows.push(fact("db", "BounderyBC.js", "Sumber"));
      return rows.join("");
    }
    if (item.kind === "person") {
      rows.push(fact("pin", p.name || "—", "Nama"));
      rows.push(fact("tag", p.company || "—", "Perusahaan"));
      rows.push(fact("tag", p.job_title || "—", "Jabatan"));
      rows.push(fact("area", p.iupk_site || p.presence || "—", "Zona IUPK"));
      rows.push(fact("db", p.hazard_kind_label || p.hazard_name || (p.safety === "unsafe" ? "Unsafe" : (p.safety || "—")), "Jenis pelanggaran"));
      rows.push(fact("tag", p.safety || p.presence || "—", "Safe / Unsafe"));
      rows.push(fact("tag", p.entered_at || p.gps_updated_at || "—", "Masuk / GPS"));
      rows.push(fact("tag", formatDuration(p.duration_seconds), "Durasi"));
      rows.push(fact("tag", p.intervention_status || "belum ada", "Status intervensi"));
      return rows.join("");
    }
    if (item.kind === "hazard") {
      rows.push(fact("pin", p.name || item.title, "Nama zona"));
      rows.push(fact("tag", p.aktivitas || "—", "Aktivitas"));
      rows.push(fact("tag", p.risk_name || "tinggi", "Potensi bahaya"));
      return rows.join("");
    }
    var labels = {
      id: "ID",
      name: "Nama",
      title: "Judul",
      code: "Kode",
      type: "Tipe",
      category: "Kategori",
      description: "Deskripsi",
      status_name: "Status",
      risk_name: "Risk",
      site: "Site",
      location: "Lokasi",
      area: "Area",
      department: "Departemen",
      is_active: "Aktif",
      entries_count: "Entry",
      violations_count: "Pelanggaran",
      created_at: "Dibuat",
      updated_at: "Diubah"
    };
    Object.keys(labels).forEach(function (key) {
      if (p[key] == null || p[key] === "") {
        return;
      }
      rows.push(fact("tag", p[key], labels[key]));
    });
    Object.keys(p).forEach(function (key) {
      if (labels[key] || /id$|_id$|color|geometry|has_geometry/.test(key)) {
        return;
      }
      if (p[key] == null || p[key] === "" || typeof p[key] === "object") {
        return;
      }
      rows.push(fact("tag", p[key], key.replace(/_/g, " ")));
    });
    if (!rows.length) {
      rows.push(fact("db", "Besigma", "Sumber"));
    }
    return rows.join("");
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
      .then(function (payload) {
        besigmaGeo = payload && payload.features ? payload : { type: "FeatureCollection", features: [] };
        besigmaRecords = payload && payload.records ? payload.records : [];
        besigmaLayer.clearLayers();
        if (besigmaGeo.features && besigmaGeo.features.length) {
          besigmaLayer.addData(besigmaGeo);
        }
        if (liveStatus) {
          if (payload && payload.error) {
            liveStatus.textContent = "Besigma: " + payload.error;
          } else if (besigmaRecords.length) {
            liveStatus.textContent = "Besigma " + besigmaRecords.length + " boundary";
          }
        }
        addLabels();
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

  function setText(id, value) {
    var el = document.getElementById(id);
    if (el) {
      el.textContent = value == null ? "–" : String(value);
    }
  }

  function personIcon(markerKind) {
    var kind = markerKind === "safe" || markerKind === "unsafe" || markerKind === "employee_danger" || markerKind === "employee_competence" || markerKind === "unit_danger"
      ? markerKind
      : "stale";
    if (kind === "unsafe") {
      kind = "employee_danger";
    }
    return L.divIcon({
      className: "gm-person-wrap",
      html: '<span class="gm-person-icon ' + kind + '"></span>',
      iconSize: [16, 16],
      iconAnchor: [8, 8]
    });
  }

  function filteredPeople() {
    return pobPeople.filter(matchesHudSite);
  }

  function countsAsPob(person) {
    return person && person.entity !== "unit" && !person.roster_only;
  }

  function filteredSummary() {
    var people = filteredPeople();
    var inCount = 0;
    var outCount = 0;
    var unknownCount = 0;
    var safe = 0;
    var unsafe = 0;
    var kinds = { employee_danger: 0, employee_competence: 0, unit_danger: 0 };
    people.forEach(function (person) {
      var kind = person.hazard_kind || "";
      if (kinds[kind] != null) {
        if (pobSource === "live") {
          if (person.from_violation) {
            kinds[kind] += 1;
          }
        } else if (person.presence === "in" && person.safety === "unsafe") {
          kinds[kind] += 1;
        }
      }
      if (!countsAsPob(person)) {
        return;
      }
      if (person.presence === "in") {
        inCount += 1;
        if (person.safety === "unsafe") {
          unsafe += 1;
        } else {
          safe += 1;
        }
      } else if (person.presence === "out") {
        outCount += 1;
      } else {
        unknownCount += 1;
      }
    });
    var checkins = pobCheckins.filter(matchesHudSite);
    return {
      in: inCount,
      out: outCount,
      unknown: unknownCount,
      safe: safe,
      unsafe: unsafe,
      unsafe_by_kind: kinds,
      checkin_total: checkins.length
    };
  }

  function paintHud(payload) {
    pobSummary = (payload && payload.summary) || {};
    pobCheckins = (payload && payload.checkins) || [];
    pobSource = (payload && payload.source) || "demo";
    var recon = (payload && payload.reconcile) || {};
    var rows = pobSummary.checkin_by_site || [];
    rows.forEach(function (row) {
      setText("hud-site-" + row.code, row.count);
    });
    paintHudCounts();
    setText("hud-ever", recon.ever_count);
    setText("hud-current", recon.current_count);
    setText("hud-rfid", recon.rfid_count);
    setText("hud-gap-br", recon.gap_besigma_minus_rfid_count);
    setText("hud-gap-rb", recon.gap_rfid_minus_besigma_count);
    setText("hud-both", recon.both_count);
    var sourceEl = document.getElementById("gm-hud-source");
    if (sourceEl) {
      if (payload.source === "live") {
        sourceEl.textContent = payload.besigma_error ? ("Besigma live — " + payload.besigma_error) : "Data live Besigma";
      } else {
        sourceEl.textContent = payload.besigma_error || "Data dummy preview";
      }
    }
  }

  function paintHudCounts() {
    var summary = filteredSummary();
    setText("hud-checkin-total", summary.checkin_total);
    setText("hud-pob-in", summary.in);
    setText("hud-pob-out", summary.out);
    setText("hud-pob-unknown", summary.unknown);
    setText("hud-safe", summary.safe);
    setText("hud-unsafe", summary.unsafe);
    setText("hud-kind-employee_danger", summary.unsafe_by_kind.employee_danger);
    setText("hud-kind-employee_competence", summary.unsafe_by_kind.employee_competence);
    setText("hud-kind-unit_danger", summary.unsafe_by_kind.unit_danger);
  }

  function rosterItems() {
    if (rosterFilter.type === "checkin") {
      return pobCheckins.filter(matchesHudSite).map(itemFromCheckin);
    }
    return filteredPeople().filter(function (person) {
      if (rosterFilter.safety === "safe") {
        return countsAsPob(person) && person.presence === "in" && person.safety === "safe";
      }
      if (rosterFilter.safety === "unsafe") {
        return countsAsPob(person) && person.presence === "in" && person.safety === "unsafe";
      }
      if (rosterFilter.kind) {
        return person.hazard_kind === rosterFilter.kind && (person.safety === "unsafe" || person.from_violation);
      }
      if (rosterFilter.type === "in") {
        return countsAsPob(person) && person.presence === "in";
      }
      return countsAsPob(person) && person.presence === "in";
    }).map(function (person) {
      return itemFromPerson(person, peopleByKey[person.key]);
    });
  }

  function rosterTitle() {
    if (rosterFilter.type === "checkin") {
      return hudSite ? ("Check-in " + hudSite) : "Check-in RFID";
    }
    if (rosterFilter.kind === "employee_danger") {
      return "Pelanggaran Batas Bahaya Karyawan";
    }
    if (rosterFilter.kind === "employee_competence") {
      return "Pelanggaran Batas Kompetensi Karyawan";
    }
    if (rosterFilter.kind === "unit_danger") {
      return "Pelanggaran Batas Bahaya Unit";
    }
    if (rosterFilter.safety === "safe") {
      return "Personel Safe";
    }
    if (rosterFilter.safety === "unsafe") {
      return "Personel Unsafe";
    }
    return "Personel In";
  }

  function openRoster(filter) {
    rosterFilter = filter || { type: "in", site: hudSite, safety: "", kind: "" };
    listMode = "roster";
    scope = "people";
    document.querySelectorAll("[data-scope]").forEach(function (el) {
      el.classList.toggle("is-on", el.getAttribute("data-scope") === "people");
    });
    openPanel();
    closePlace();
    if (liveStatus) {
      liveStatus.textContent = rosterTitle() + (hudSite ? " · " + hudSite : "");
    }
    var kicker = document.querySelector(".gm-results-head .gm-kicker");
    if (kicker) {
      kicker.textContent = rosterTitle();
    }
    applyScope();
  }

  function jumpToSite(code) {
    if (!code) {
      fitOps();
      return;
    }
    var match = iupkLayers.find(function (entry) {
      var props = entry.feature.properties || {};
      var layer = String(props.Layer || "");
      var site = String(props.Site || "");
      return layer.indexOf(code) === 0 || site.toUpperCase().indexOf(code) !== -1 || (code === "BMO" && site.indexOf("Binungan") !== -1);
    });
    if (match && match.layer.getBounds) {
      map.fitBounds(match.layer.getBounds(), { padding: [56, 56], maxZoom: 12 });
    }
  }

  function setHudSite(code) {
    hudSite = code || "";
    document.querySelectorAll("[data-hud-site]").forEach(function (el) {
      el.classList.toggle("is-on", (el.getAttribute("data-hud-site") || "") === hudSite);
    });
    paintHudCounts();
    renderPeople(pobPeople);
    if (listMode === "roster") {
      openRoster(rosterFilter);
    } else {
      applyScope();
    }
    jumpToSite(hudSite);
  }

  function renderPeople(people) {
    pobPeople = people || [];
    peopleByKey = {};
    peopleLayer.clearLayers();
    pobPeople.forEach(function (person) {
      if (person.lat == null || person.lng == null || Number(person.lat) === 0 || Number(person.lng) === 0) {
        return;
      }
      if (!matchesHudSite(person)) {
        return;
      }
      var marker = L.marker([person.lat, person.lng], {
        pane: "people",
        icon: personIcon(person.marker || person.safety || "stale"),
        title: person.name || person.sid || "Personel"
      });
      marker.on("click", function () {
        openPlace(itemFromPerson(person, marker));
      });
      marker.addTo(peopleLayer);
      peopleByKey[person.key] = marker;
    });
    if (showPeople && !map.hasLayer(peopleLayer)) {
      peopleLayer.addTo(map);
    }
  }

  function renderHazards(features) {
    hazardLayer.clearLayers();
    if (features && features.length) {
      hazardLayer.addData({ type: "FeatureCollection", features: features });
    }
    if (showHazard && !map.hasLayer(hazardLayer)) {
      hazardLayer.addTo(map);
    }
  }

  function loadPob() {
    if (!pobUrl) {
      return;
    }
    fetch(pobUrl, { headers: { Accept: "application/json" } })
      .then(function (res) { return res.json(); })
      .then(function (payload) {
        paintHud(payload || {});
        renderPeople((payload && payload.people) || []);
        renderHazards((payload && payload.hazard_features) || []);
        applyScope();
      })
      .catch(function () {});
  }

  addLabels();
  fitOps();
  renderList();
  loadBesigma();
  loadPob();

  document.querySelectorAll("[data-hud-site]").forEach(function (btn) {
    btn.addEventListener("click", function () {
      setHudSite(btn.getAttribute("data-hud-site") || "");
    });
  });
  document.querySelectorAll("[data-roster]").forEach(function (btn) {
    btn.addEventListener("click", function () {
      var type = btn.getAttribute("data-roster") || "";
      if (type === "checkin") {
        openRoster({ type: "checkin", safety: "", kind: "" });
        return;
      }
      if (type === "safe") {
        openRoster({ type: "in", safety: "safe", kind: "" });
        return;
      }
      if (type === "unsafe") {
        openRoster({ type: "in", safety: "unsafe", kind: "" });
        return;
      }
      if (type === "kind") {
        openRoster({ type: "in", safety: "unsafe", kind: btn.getAttribute("data-kind") || "" });
      }
    });
  });
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
      if (scope === "besigma" || scope === "people") {
        openPanel();
      }
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
      setBasemap(btn.getAttribute("data-basemap") || "sgi");
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
      if (layer === "people") {
        showPeople = input.checked;
      }
      if (layer === "hazard") {
        showHazard = input.checked;
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

  var interveneBtn = document.getElementById("gm-act-intervene");
  if (interveneBtn) {
    interveneBtn.addEventListener("click", function () {
      if (!interventionsUrl) {
        return;
      }
      var eventId = selected && selected.props && selected.props.open_event_id;
      var url = interventionsUrl + (eventId ? ("?event=" + encodeURIComponent(eventId)) : "");
      window.location.href = url;
    });
  }

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
      loadPob();
      toast("Memuat Besigma dan POB…");
    });
  }

  window.addEventListener("resize", function () {
    map.invalidateSize();
  });
})();
