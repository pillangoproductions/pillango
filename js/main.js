/* ============================================================
   Pillango Productions — flight engine.
   Native scroll drives a camera along the z-axis; each chapter of a
   page sits at a depth, and the frame's colour grades along with it,
   from the first shot to the end credits.

   The chapter spine is read straight from the markup: every
   [data-chapter] layer inside #stage carries
     data-gap   distance from the previous chapter (1 = one unit)
     data-sky   the background colour while it is on screen
     data-rail  optional label for the progress rail
   so adding a chapter lengthens the flight rather than crowding it.

   Pages without a #stage (the legal pages, the blog) get only the
   menu and the sheets; the flight never starts there.

   The flight is detented: one push of the wheel, one swipe or one
   arrow key carries the camera to the next chapter and stops there.
   ============================================================ */
(function () {
  "use strict";

  var UNIT_DEPTH = 1150;       // px of travel per unit of data-gap

  var stage = document.getElementById("stage");
  var scrollSpace = document.getElementById("scroll-space");
  var railFill = document.getElementById("rail-fill");
  var railStops = document.getElementById("rail-stops");
  var burger = document.getElementById("burger");
  var overlay = document.getElementById("overlay-menu");
  document.querySelectorAll("[data-year]").forEach(function (el) {
    el.textContent = new Date().getFullYear();
  });

  /* ---- the menu overlay: every page has one ---- */
  function closeOverlay() {
    if (!overlay || overlay.hidden) return;
    overlay.hidden = true;
    if (burger) {
      burger.setAttribute("aria-expanded", "false");
      burger.setAttribute("aria-label", "Open menu");
    }
    document.body.style.overflow = "";
  }
  if (burger && overlay) {
    burger.addEventListener("click", function () {
      var open = overlay.hidden;
      overlay.hidden = !open;
      burger.setAttribute("aria-expanded", String(open));
      burger.setAttribute("aria-label", open ? "Close menu" : "Open menu");
      document.body.style.overflow = open ? "hidden" : "";
    });
    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape") closeOverlay();
    });
  }

  /* ---- the chapter spine, read from the markup ---- */
  var CHAPTERS = [];
  var TOTAL_DEPTH = 0;
  if (stage) {
    var els = stage.querySelectorAll("[data-chapter]");
    var total = 0, gaps = [];
    els.forEach(function (el, i) {
      var g = i === 0 ? 0 : parseFloat(el.getAttribute("data-gap") || "1.25");
      gaps.push(g);
      total += g;
    });
    if (total <= 0) total = 1;
    var cum = 0;
    els.forEach(function (el, i) {
      cum += gaps[i];
      CHAPTERS.push({
        id: el.getAttribute("data-chapter"),
        p: Math.round((cum / total) * 1e5) / 1e5,
        sky: el.getAttribute("data-sky") || "#0B0C0E",
        rail: el.getAttribute("data-rail") || null
      });
    });
    TOTAL_DEPTH = Math.round(total * UNIT_DEPTH);
  }

  /* The scroll driver is as tall as the journey is deep, so one
     pixel of scrolling is one pixel of travel. */
  if (scrollSpace && TOTAL_DEPTH) scrollSpace.style.height = TOTAL_DEPTH + "px";

  /* the frame's colour at each chapter, interpolated in between. */
  var SKY = CHAPTERS.map(function (ch) { return [ch.p, ch.sky]; });

  var FADE_OUT_START = 140;     // begins passing the camera
  var FADE_OUT_END = 620;       // gone behind us
  var FADE_IN_START = -2400;    // layer appears this far ahead
  var FADE_IN_END = -650;       // fully visible from here
  var DUST_COUNT = 30;

  var reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  var supports3d = window.CSS && CSS.supports && CSS.supports("transform", "translateZ(1px)");

  /* background video: respect reduced-motion */
  var heroVideo = document.getElementById("hero-video");
  if (heroVideo && reduceMotion) {
    heroVideo.removeAttribute("autoplay");
    heroVideo.pause();
    heroVideo.style.display = "none";
  }

  /* ---- sheets: full-screen scrollable panels over the flight ---- */
  var lastSheetOpener = null;

  function openSheet(id, opener) {
    var sheet = document.getElementById(id);
    if (!sheet) return;
    sheet.hidden = false;
    sheet.scrollTop = 0;
    document.body.style.overflow = "hidden";
    lastSheetOpener = opener || null;
    var close = sheet.querySelector("[data-close-sheet]");
    if (close) close.focus();
  }

  function closeSheets() {
    var closed = false;
    document.querySelectorAll(".sheet").forEach(function (sheet) {
      if (!sheet.hidden) { sheet.hidden = true; closed = true; }
    });
    if (closed) {
      document.body.style.overflow = "";
      if (lastSheetOpener) { lastSheetOpener.focus(); lastSheetOpener = null; }
    }
    return closed;
  }

  document.querySelectorAll("[data-open-sheet]").forEach(function (btn) {
    btn.addEventListener("click", function () {
      openSheet(btn.getAttribute("data-open-sheet"), btn);
    });
  });
  document.querySelectorAll("[data-close-sheet]").forEach(function (btn) {
    btn.addEventListener("click", closeSheets);
  });


  document.addEventListener("keydown", function (e) {
    if (e.key === "Escape") { closeSheets(); return; }
    if (e.key !== "Tab") return;
    /* A sheet is a modal dialog: keep Tab inside it. */
    var sheet = null;
    document.querySelectorAll(".sheet").forEach(function (el) {
      if (!el.hidden) sheet = el;
    });
    if (!sheet) return;
    var focusable = sheet.querySelectorAll(
      'a[href], button:not([disabled]), input:not([type="hidden"]), select, textarea, [tabindex]:not([tabindex="-1"])'
    );
    if (!focusable.length) return;
    var first = focusable[0], last = focusable[focusable.length - 1];
    if (e.shiftKey && document.activeElement === first) {
      e.preventDefault(); last.focus();
    } else if (!e.shiftKey && document.activeElement === last) {
      e.preventDefault(); first.focus();
    }
  });

  if (!stage || !CHAPTERS.length) return;

  /* A deep link (/#ch-contact) lands straight on its chapter. */
  var landOn = null;
  if (/^#ch-/.test(window.location.hash)) landOn = window.location.hash.replace("#ch-", "");

  if (reduceMotion || !supports3d) {
    document.documentElement.classList.add("flat");
    wireNav();
    if (landOn) goTo(landOn);
    return;
  }

  /* ---- layers ---- */
  var layers = [];
  CHAPTERS.forEach(function (ch) {
    var el = document.querySelector('[data-chapter="' + ch.id + '"]');
    if (el) layers.push({ el: el, depth: ch.p * TOTAL_DEPTH, id: ch.id });
  });
  /* each layer emerges within the gap behind it, so close chapters
     don't bleed through each other */
  layers.forEach(function (layer, i) {
    var gapPrev = i === 0 ? TOTAL_DEPTH : layer.depth - layers[i - 1].depth;
    layer.fadeInStart = -Math.min(-FADE_IN_START, gapPrev * 0.88);
    layer.fadeInEnd = -Math.min(-FADE_IN_END, gapPrev * 0.28);
  });

  /* dust in the projector beam */
  var dust = [];
  for (var i = 0; i < DUST_COUNT; i++) {
    var d = document.createElement("div");
    d.className = "dust";
    stage.appendChild(d);
    dust.push({
      el: d,
      x: (Math.random() * 2 - 1) * 46,          // vw offset
      y: (Math.random() * 2 - 1) * 42,          // vh offset
      z: Math.random() * TOTAL_DEPTH,
      s: 0.35 + Math.random() * 0.9
    });
  }

  /* the progress rail — only chapters that named a label */
  if (railStops) {
    CHAPTERS.forEach(function (ch) {
      if (!ch.rail) return;
      var li = document.createElement("li");
      var b = document.createElement("button");
      b.type = "button";
      b.textContent = ch.rail;
      b.setAttribute("data-goto", ch.id);
      b.setAttribute("aria-label", "Go to: " + ch.rail);
      li.appendChild(b);
      railStops.appendChild(li);
    });
  }

  /* ---- colour helpers ---- */
  function hexToRgb(hex) {
    var n = parseInt(hex.slice(1), 16);
    return [(n >> 16) & 255, (n >> 8) & 255, n & 255];
  }
  var SKY_RGB = SKY.map(function (s) { return [s[0], hexToRgb(s[1])]; });

  function skyColor(p) {
    if (p <= SKY_RGB[0][0]) return SKY_RGB[0][1];
    for (var i = 1; i < SKY_RGB.length; i++) {
      if (p <= SKY_RGB[i][0]) {
        var a = SKY_RGB[i - 1], b = SKY_RGB[i];
        var span = b[0] - a[0];
        var t = span > 0 ? (p - a[0]) / span : 1;
        return [
          Math.round(a[1][0] + (b[1][0] - a[1][0]) * t),
          Math.round(a[1][1] + (b[1][1] - a[1][1]) * t),
          Math.round(a[1][2] + (b[1][2] - a[1][2]) * t)
        ];
      }
    }
    return SKY_RGB[SKY_RGB.length - 1][1];
  }

  /* ---- scroll → camera ---- */
  var targetP = 0, currentP = 0;
  var lastFrame = 0;

  function maxScroll() {
    return Math.max(1, document.documentElement.scrollHeight - window.innerHeight);
  }

  function readScroll() {
    targetP = Math.min(1, Math.max(0, window.scrollY / maxScroll()));
    /* When the tab is hidden or rAF is being throttled, the smoothing
       loop stops firing — so drive the scene straight from the scroll
       event. This keeps scrolling responsive in a background tab, an
       embedded preview, or low-power mode. */
    if (document.hidden || performance.now() - lastFrame > 250) {
      currentP = targetP;
      render();
    }
  }
  window.addEventListener("scroll", readScroll, { passive: true });
  window.addEventListener("resize", readScroll);
  document.addEventListener("visibilitychange", function () {
    if (!document.hidden) readScroll();
  });

  function frame(now) {
    lastFrame = now || performance.now();
    /* debug: pin the camera (window.__freezeP = 0..1) */
    if (typeof window.__freezeP === "number") targetP = currentP = window.__freezeP;
    currentP += (targetP - currentP) * 0.085;
    if (Math.abs(targetP - currentP) < 0.00004) currentP = targetP;
    render();
    requestAnimationFrame(frame);
  }

  function render() {
    var camZ = currentP * TOTAL_DEPTH;

    /* the frame's grade */
    var c = skyColor(currentP);
    stage.style.background = "rgb(" + c[0] + "," + c[1] + "," + c[2] + ")";

    /* nav ink follows the frame's brightness */
    var lum = (0.299 * c[0] + 0.587 * c[1] + 0.114 * c[2]) / 255;
    var overlayOpen = overlay && !overlay.hidden;
    var nav = document.getElementById("nav");
    if (nav) nav.classList.toggle("on-light", !overlayOpen && lum > 0.52);
    var rail = document.getElementById("rail");
    if (rail) rail.classList.toggle("on-light", lum > 0.52);

    /* chapters */
    var activeIdx = -1, bestDist = Infinity;
    layers.forEach(function (layer, idx) {
      var dz = camZ - layer.depth; /* <0: ahead of camera, >0: behind */
      var visible = dz > layer.fadeInStart && dz < FADE_OUT_END;
      if (!visible) {
        layer.el.style.opacity = "0";
        layer.el.style.visibility = "hidden";
        layer.el.classList.remove("is-active");
        return;
      }
      var opacity;
      if (dz < layer.fadeInEnd) {
        opacity = (dz - layer.fadeInStart) / (layer.fadeInEnd - layer.fadeInStart);
        opacity = opacity * opacity * opacity; /* ease in from the deep */
      } else if (dz > FADE_OUT_START) {
        opacity = 1 - (dz - FADE_OUT_START) / (FADE_OUT_END - FADE_OUT_START);
      } else {
        opacity = 1;
      }
      layer.el.style.visibility = "visible";
      layer.el.style.opacity = opacity.toFixed(3);
      layer.el.style.transform = "translateZ(" + dz.toFixed(1) + "px)";

      var dist = Math.abs(dz);
      if (dist < bestDist) { bestDist = dist; activeIdx = idx; }
    });
    layers.forEach(function (layer, idx) {
      layer.el.classList.toggle("is-active", idx === activeIdx);
    });

    /* dust */
    dust.forEach(function (p) {
      var dz = camZ - p.z;
      if (dz > 300 || dz < -4200) {
        p.el.style.opacity = "0";
        return;
      }
      var o = dz > 0 ? 1 - dz / 300 : Math.max(0, 1 + dz / 4200);
      p.el.style.opacity = (o * 0.5).toFixed(3);
      p.el.style.transform =
        "translate3d(" + (50 + p.x) + "vw," + (50 + p.y) + "vh," + dz.toFixed(1) + "px) scale(" + p.s + ")";
    });

    /* hero video: only decode while the opening is on screen */
    if (heroVideo && !reduceMotion) {
      var heroVisible = layers.length && layers[0].el.style.visibility !== "hidden";
      if (!heroVisible && !heroVideo.paused) {
        heroVideo.pause();
      } else if (heroVisible && heroVideo.paused) {
        heroVideo.play().catch(function () {});
      }
    }

    /* rail */
    if (railFill) railFill.style.height = (currentP * 100).toFixed(2) + "%";
    if (railStops && activeIdx >= 0) {
      var activeId = layers[activeIdx].id;
      railStops.querySelectorAll("button").forEach(function (b) {
        b.classList.toggle("is-current", b.getAttribute("data-goto") === activeId);
      });
    }
  }

  /* ---- the detent: one push, one chapter -------------------------
     The page is a set of stops, not a slide. The camera rests on a
     chapter and stays there; a wheel gesture, a swipe or an arrow key
     is one "push" that carries it to the next chapter and no further.
     A long flick is still one push — the next one needs a fresh
     gesture, so every stop gets its moment.

     Native scroll stays the source of truth (the scrollbar, deep links
     and the rail all still work); we simply take the wheel and the
     finger, and drive the scroll position ourselves. */
  var SNAP_MS = 520;          // travel time between two chapters
  var WHEEL_TRIGGER = 42;     // deltaY that adds up to one push
  var GESTURE_GAP = 170;      // quiet time that ends a gesture
  var REST_MS = 90;           // pause on arrival before the next push
  var SWIPE_TRIGGER = 42;     // px of finger travel that counts as a push
  var SETTLE_MS = 140;        // quiet time before a stray scroll is tidied

  document.documentElement.classList.add("snap");

  var snapIndex = nearestChapter(window.scrollY);
  var tween = null;                       // {from, to, start} while travelling
  var wheelAccum = 0, lastWheel = 0, armed = true, restAt = 0;
  var touching = false, touchY = 0, touchDy = 0;
  var settleTimer = null;

  function chapterTop(i) {
    return Math.round(CHAPTERS[i].p * maxScroll());
  }

  function nearestChapter(y) {
    var best = 0, bestDist = Infinity;
    for (var i = 0; i < CHAPTERS.length; i++) {
      var d = Math.abs(chapterTop(i) - y);
      if (d < bestDist) { bestDist = d; best = i; }
    }
    return best;
  }

  /* A sheet or the menu owns the scroll while it is open. */
  function sheetOpen() {
    if (overlay && !overlay.hidden) return true;
    var open = false;
    document.querySelectorAll(".sheet").forEach(function (el) {
      if (!el.hidden) open = true;
    });
    return open;
  }

  function easeInOut(t) {
    return t < 0.5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2;
  }

  function stepTween(now) {
    if (!tween) return;
    var t = Math.min(1, (now - tween.start) / SNAP_MS);
    window.scrollTo(0, Math.round(tween.from + (tween.to - tween.from) * easeInOut(t)));
    if (t < 1) {
      requestAnimationFrame(stepTween);
    } else {
      tween = null;
      restAt = now;
      wheelAccum = 0;
    }
  }

  /* Travel to a chapter by index. `instant` lands without the glide —
     used for the arrival jump on a fresh page load. */
  function travelTo(i, instant) {
    i = Math.max(0, Math.min(CHAPTERS.length - 1, i));
    snapIndex = i;
    var to = chapterTop(i);
    if (instant) {
      tween = null;
      window.scrollTo(0, to);
      restAt = performance.now();
      return;
    }
    if (to === Math.round(window.scrollY)) { return; }
    tween = { from: window.scrollY, to: to, start: performance.now() };
    requestAnimationFrame(stepTween);
  }

  /* One push: the next chapter in that direction, never two. */
  function push(dir) {
    if (tween || sheetOpen()) return;
    if (performance.now() - restAt < REST_MS) return;
    var next = snapIndex + dir;
    if (next < 0 || next >= CHAPTERS.length) return;
    travelTo(next);
  }

  window.addEventListener("wheel", function (e) {
    if (sheetOpen()) return;              /* the sheet scrolls itself */
    e.preventDefault();                   /* free scrolling never runs the flight */
    var now = performance.now();
    /* A gap in the events means the hand let go: re-arm for a new push.
       Trackpad momentum arrives as one unbroken stream, so a long flick
       stays a single push. */
    if (now - lastWheel > GESTURE_GAP) { wheelAccum = 0; armed = true; }
    lastWheel = now;
    if (tween || !armed) return;
    wheelAccum += e.deltaY * (e.deltaMode === 1 ? 16 : 1);
    if (Math.abs(wheelAccum) >= WHEEL_TRIGGER) {
      var dir = wheelAccum > 0 ? 1 : -1;
      wheelAccum = 0;
      armed = false;
      push(dir);
    }
  }, { passive: false });

  window.addEventListener("touchstart", function (e) {
    if (sheetOpen() || e.touches.length !== 1) { touching = false; return; }
    touching = true;
    touchY = e.touches[0].clientY;
    touchDy = 0;
  }, { passive: true });

  window.addEventListener("touchmove", function (e) {
    if (!touching) return;
    touchDy = touchY - e.touches[0].clientY;
    e.preventDefault();                   /* the finger nudges, it does not drag */
  }, { passive: false });

  function endTouch() {
    if (!touching) return;
    touching = false;
    if (Math.abs(touchDy) >= SWIPE_TRIGGER) push(touchDy > 0 ? 1 : -1);
  }
  window.addEventListener("touchend", endTouch, { passive: true });
  window.addEventListener("touchcancel", function () { touching = false; }, { passive: true });

  document.addEventListener("keydown", function (e) {
    if (sheetOpen()) return;
    var t = e.target;
    if (t && (t.tagName === "INPUT" || t.tagName === "TEXTAREA"
      || t.tagName === "SELECT" || t.isContentEditable)) return;
    var space = e.key === " " || e.key === "Spacebar";
    /* Space belongs to a focused button or link, not to the flight. */
    if (space && t && (t.tagName === "BUTTON" || t.tagName === "A")) return;

    var dir = 0, jump = -1;
    if (e.key === "ArrowDown" || e.key === "PageDown") dir = 1;
    else if (e.key === "ArrowUp" || e.key === "PageUp") dir = -1;
    else if (space) dir = e.shiftKey ? -1 : 1;
    else if (e.key === "Home") jump = 0;
    else if (e.key === "End") jump = CHAPTERS.length - 1;
    else return;

    e.preventDefault();
    if (jump >= 0) travelTo(jump); else push(dir);
  });

  /* Anything else that moves the scroll — a scrollbar drag, a browser
     find, a restored position — is tidied onto the nearest chapter once
     it goes quiet, so the flight never rests between two stops. */
  function scheduleSettle() {
    if (tween || touching) return;
    if (settleTimer) clearTimeout(settleTimer);
    settleTimer = setTimeout(function () {
      settleTimer = null;
      if (tween || touching || sheetOpen()) return;
      var i = nearestChapter(window.scrollY);
      if (Math.abs(chapterTop(i) - window.scrollY) > 2) travelTo(i);
      else snapIndex = i;
    }, SETTLE_MS);
  }
  window.addEventListener("scroll", scheduleSettle, { passive: true });

  /* A resize moves every chapter's scroll position — hold the stop.
     Not while a sheet is open, though: there the resize is usually the
     phone's keyboard opening under a form, and the flight behind it
     must stay put. */
  window.addEventListener("resize", function () {
    if (tween || touching || sheetOpen()) return;
    travelTo(snapIndex, true);
  });

  /* initial sync + start the smoothing loop */
  targetP = Math.min(1, Math.max(0, window.scrollY / maxScroll()));
  currentP = targetP;
  render();
  requestAnimationFrame(frame);

  wireNav();
  if (landOn) {
    /* jump, don't glide, on a fresh load */
    var landIdx = chapterIndexById(landOn);
    if (landIdx >= 0) travelTo(landIdx, true);
  }

  /* ---- navigation ---- */
  function chapterIndexById(id) {
    for (var i = 0; i < CHAPTERS.length; i++) {
      if (CHAPTERS[i].id === id) return i;
    }
    return -1;
  }

  function goTo(id) {
    if (document.documentElement.classList.contains("flat")) {
      var target = document.getElementById("ch-" + id);
      if (target) target.scrollIntoView({ behavior: "smooth" });
      return;
    }
    /* The rail and the menu skip straight to a stop — the detent only
       governs the wheel, the finger and the arrow keys. */
    var idx = chapterIndexById(id);
    if (idx >= 0) travelTo(idx);
  }

  function wireNav() {
    document.querySelectorAll("[data-goto]").forEach(function (link) {
      link.addEventListener("click", function (e) {
        e.preventDefault();
        closeOverlay();
        closeSheets();
        goTo(link.getAttribute("data-goto"));
      });
    });
  }
})();
