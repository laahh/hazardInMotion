(function () {
  var stage = document.getElementById("moi-stage");
  if (!stage) {
    return;
  }

  var reduce = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  var pages = Array.prototype.slice.call(stage.querySelectorAll(".moi-page"));
  var dots = Array.prototype.slice.call(document.querySelectorAll("[data-moi-dot]"));
  var links = Array.prototype.slice.call(document.querySelectorAll("[data-moi-link]"));
  var count = document.getElementById("moi-count");
  var progress = document.getElementById("moi-progress");
  var intro = document.getElementById("moi-intro");
  var total = pages.length;
  var current = Math.max(0, Math.min(total - 1, (Number(stage.getAttribute("data-start-page")) || 1) - 1));
  var locked = false;
  var touchY = 0;
  var baseUrl = (stage.getAttribute("data-base-url") || "/isc/moi").replace(/\/$/, "");

  function pageUrl(index) {
    return baseUrl + "/" + (index + 1);
  }

  function setActive(index, push) {
    if (index < 0 || index >= total || locked) {
      return;
    }

    locked = true;
    var prev = current;
    current = index;

    pages.forEach(function (page, i) {
      page.classList.toggle("is-exit", i === prev && i !== index);
      page.classList.toggle("is-active", i === index);
      if (i !== index && i !== prev) {
        page.classList.remove("is-exit");
      }
    });

    dots.forEach(function (dot, i) {
      dot.classList.toggle("is-on", i === index);
    });
    links.forEach(function (link, i) {
      link.classList.toggle("is-on", i === index);
    });

    if (count) {
      count.textContent = String(index + 1).padStart(2, "0") + " / " + String(total).padStart(2, "0");
    }
    if (progress) {
      progress.style.width = ((index + 1) / total * 100).toFixed(2) + "%";
    }

    if (push !== false) {
      window.history.replaceState({ page: index + 1 }, "", pageUrl(index));
    }

    window.setTimeout(function () {
      pages.forEach(function (page, i) {
        if (i !== current) {
          page.classList.remove("is-exit");
        }
      });
      locked = false;
    }, reduce ? 50 : 720);
  }

  function next() { setActive(current + 1); }
  function prev() { setActive(current - 1); }

  document.querySelectorAll("[data-moi-next]").forEach(function (btn) {
    btn.addEventListener("click", next);
  });
  document.querySelectorAll("[data-moi-prev]").forEach(function (btn) {
    btn.addEventListener("click", prev);
  });
  dots.forEach(function (dot, i) {
    dot.addEventListener("click", function () { setActive(i); });
  });
  links.forEach(function (link, i) {
    link.addEventListener("click", function () { setActive(i); });
  });
  document.querySelectorAll("[data-moi-goto]").forEach(function (btn) {
    btn.addEventListener("click", function () {
      setActive(Number(btn.getAttribute("data-moi-goto")) - 1);
    });
  });

  window.addEventListener("keydown", function (event) {
    if (event.key === "ArrowRight" || event.key === "ArrowDown" || event.key === "PageDown" || event.key === " ") {
      event.preventDefault();
      next();
    }
    if (event.key === "ArrowLeft" || event.key === "ArrowUp" || event.key === "PageUp") {
      event.preventDefault();
      prev();
    }
    if (event.key === "Home") {
      event.preventDefault();
      setActive(0);
    }
    if (event.key === "End") {
      event.preventDefault();
      setActive(total - 1);
    }
  });

  var wheelLock = 0;
  window.addEventListener("wheel", function (event) {
    var scrollable = event.target.closest(".tbl-wrap, .def-wrap, .loc-wrap, .proc-detail, .proc-rail, .moi-inner");
    if (scrollable && scrollable.scrollHeight > scrollable.clientHeight + 8) {
      var atTop = scrollable.scrollTop <= 0;
      var atBottom = scrollable.scrollTop + scrollable.clientHeight >= scrollable.scrollHeight - 4;
      if ((event.deltaY < 0 && !atTop) || (event.deltaY > 0 && !atBottom)) {
        return;
      }
    }
    if (Math.abs(event.deltaY) < 18) {
      return;
    }
    var now = Date.now();
    if (now - wheelLock < 900) {
      return;
    }
    wheelLock = now;
    if (event.deltaY > 0) {
      next();
    } else {
      prev();
    }
  }, { passive: true });

  window.addEventListener("touchstart", function (event) {
    touchY = event.changedTouches[0].clientY;
  }, { passive: true });

  window.addEventListener("touchend", function (event) {
    var dy = event.changedTouches[0].clientY - touchY;
    if (Math.abs(dy) < 50) {
      return;
    }
    if (dy < 0) {
      next();
    } else {
      prev();
    }
  }, { passive: true });

  function bindTabs(rootSelector, btnSelector, panelSelector) {
    var root = document.querySelector(rootSelector);
    if (!root) {
      return;
    }
    var buttons = Array.prototype.slice.call(root.querySelectorAll(btnSelector));
    var panels = Array.prototype.slice.call(root.querySelectorAll(panelSelector));
    buttons.forEach(function (btn) {
      btn.addEventListener("click", function () {
        var id = btn.getAttribute("data-tab");
        buttons.forEach(function (item) {
          item.classList.toggle("is-on", item === btn);
        });
        panels.forEach(function (panel) {
          panel.classList.toggle("is-on", panel.getAttribute("data-panel") === id);
        });
      });
    });
  }

  bindTabs("#lingkup-root", "[data-tab]", "[data-panel]");

  function bindProcess(rootId) {
    var root = document.getElementById(rootId);
    if (!root) {
      return;
    }
    var buttons = Array.prototype.slice.call(root.querySelectorAll("[data-step]"));
    var title = root.querySelector("[data-proc-title]");
    var owner = root.querySelector("[data-proc-owner]");
    var list = root.querySelector("[data-proc-list]");

    function show(btn) {
      buttons.forEach(function (item) {
        item.classList.toggle("is-on", item === btn);
      });
      if (title) {
        title.textContent = btn.getAttribute("data-title") || "";
      }
      if (owner) {
        owner.textContent = btn.getAttribute("data-owner") || "";
      }
      if (list) {
        var items = JSON.parse(btn.getAttribute("data-items") || "[]");
        list.replaceChildren();
        items.forEach(function (item) {
          var li = document.createElement("li");
          li.textContent = item;
          list.appendChild(li);
        });
      }
    }

    buttons.forEach(function (btn) {
      btn.addEventListener("click", function () { show(btn); });
    });
    if (buttons[0]) {
      show(buttons[0]);
    }
  }

  bindProcess("live-root");
  bindProcess("post-root");

  var params = new URLSearchParams(window.location.search);
  var skipIntro = params.has("skipintro") || window.sessionStorage.getItem("moi-intro");
  if (intro && !reduce && !skipIntro) {
    window.setTimeout(function () {
      intro.classList.add("is-done");
      window.sessionStorage.setItem("moi-intro", "1");
    }, 2100);
  } else if (intro) {
    intro.classList.add("is-done");
  }

  setActive(current, false);
})();
