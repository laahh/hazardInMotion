(function () {
  var nav = document.querySelector(".isc-home-links");
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
    pill.style.transform = "translateX(" + (box.left - navBox.left) + "px)";
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
})();
