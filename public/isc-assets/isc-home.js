(function () {
  var home = document.querySelector(".isc-home");
  var nav = document.querySelector(".isc-home-links");
  var track = document.querySelector(".isc-home-stage-track");
  var reduce = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  function setupNav() {
    if (!nav) {
      return;
    }

    var pill = nav.querySelector(".isc-home-link-pill");
    var links = nav.querySelectorAll("a");
    if (!pill || !links.length) {
      return;
    }

    function current() {
      return nav.querySelector("a.is-on") || links[0];
    }

    function move(el, ready) {
      if (!el) {
        return;
      }
      var navBox = nav.getBoundingClientRect();
      var box = el.getBoundingClientRect();
      pill.style.width = box.width + "px";
      pill.style.transform = "translate(" + (box.left - navBox.left) + "px, -50%)";
      if (ready) {
        pill.classList.add("is-ready");
      }
    }

    move(current(), false);
    window.requestAnimationFrame(function () {
      move(current(), true);
    });

    links.forEach(function (link) {
      link.addEventListener("mouseenter", function () {
        move(link);
      });
      link.addEventListener("focus", function () {
        move(link);
      });
    });

    nav.addEventListener("mouseleave", function () {
      move(current());
    });

    window.addEventListener("resize", function () {
      move(current());
    });
  }

  function setupParallax() {
    if (!home || !track || reduce) {
      return;
    }

    var mx = 0;
    var my = 0;
    var tx = 0;
    var ty = 0;
    var running = false;

    function tick() {
      tx += (mx - tx) * 0.08;
      ty += (my - ty) * 0.08;
      track.style.transform = "translate3d(" + tx.toFixed(2) + "px," + ty.toFixed(2) + "px,0)";
      if (Math.abs(mx - tx) > 0.05 || Math.abs(my - ty) > 0.05) {
        window.requestAnimationFrame(tick);
      } else {
        running = false;
      }
    }

    home.addEventListener("mousemove", function (event) {
      var rect = home.getBoundingClientRect();
      mx = ((event.clientX - rect.left) / rect.width - 0.5) * 16;
      my = ((event.clientY - rect.top) / rect.height - 0.5) * 10;
      if (!running) {
        running = true;
        window.requestAnimationFrame(tick);
      }
    });

    home.addEventListener("mouseleave", function () {
      mx = 0;
      my = 0;
      if (!running) {
        running = true;
        window.requestAnimationFrame(tick);
      }
    });
  }

  setupNav();
  setupParallax();
})();
