(function () {
  var root = document.getElementById("isc-s2");
  var reduce = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  function reveal() {
    if (!root) {
      return;
    }
    window.requestAnimationFrame(function () {
      root.classList.add("is-ready");
    });
  }

  function setupParallax() {
    var photo = document.querySelector(".isc-s2-photo");
    if (!root || !photo || reduce) {
      return;
    }

    var mx = 0;
    var my = 0;
    var tx = 0;
    var ty = 0;
    var running = false;

    function tick() {
      tx += (mx - tx) * 0.07;
      ty += (my - ty) * 0.07;
      photo.style.translate = tx.toFixed(2) + "px " + ty.toFixed(2) + "px";
      if (Math.abs(mx - tx) > 0.05 || Math.abs(my - ty) > 0.05) {
        window.requestAnimationFrame(tick);
      } else {
        running = false;
      }
    }

    root.addEventListener("mousemove", function (event) {
      var rect = root.getBoundingClientRect();
      mx = ((event.clientX - rect.left) / rect.width - 0.5) * 18;
      my = ((event.clientY - rect.top) / rect.height - 0.5) * 12;
      if (!running) {
        running = true;
        window.requestAnimationFrame(tick);
      }
    });

    root.addEventListener("mouseleave", function () {
      mx = 0;
      my = 0;
      if (!running) {
        running = true;
        window.requestAnimationFrame(tick);
      }
    });
  }

  function setupTilt() {
    if (reduce) {
      return;
    }

    var cards = document.querySelectorAll("[data-tilt]");
    cards.forEach(function (card) {
      card.addEventListener("mousemove", function (event) {
        var box = card.getBoundingClientRect();
        var x = (event.clientX - box.left) / box.width - 0.5;
        var y = (event.clientY - box.top) / box.height - 0.5;
        card.style.transform = "translateY(-5px) rotateX(" + (y * -6).toFixed(2) + "deg) rotateY(" + (x * 8).toFixed(2) + "deg)";
      });
      card.addEventListener("mouseleave", function () {
        card.style.transform = "";
      });
    });
  }

  reveal();
  setupParallax();
  setupTilt();
})();
