/* ============================================================
   Pillango Productions — site engine.

   One continuous flight through the site. Every page in the menu is
   a stretch of the same journey, in menu order:
     Home → About → Services → Partners → Post-production → Blog →
     Projects → Book → (back to Home)
   Inside a page, native scroll drives a camera along the z-axis
   through its chapters; a push past the last chapter flies on into
   the next page, a push back past the first returns to the previous
   one. Page to page, nothing reloads: the next page is fetched ahead
   of time and its chapters are swapped into the stage while the title
   card is up, so the light, the menu and the cursor never blink. Every
   page is still a real page at its own address (search engines, links,
   the Back button); legal pages and reduced-motion visitors simply
   load normally.

   The chapter spine is read from the markup: every [data-chapter]
   layer inside #stage carries
     data-gap   distance from the previous chapter (1 = one unit)
     data-sky   the colour laid over the background while on screen
     data-veil  how much of that colour covers the bokeh, 0–1
   and #stage itself carries data-next / data-prev (+ -label).

   Also here: the bokeh background, the orange light under the cursor,
   the water ripple on click, the menu, and the Held Still password box.
   ============================================================ */
(function () {
  "use strict";

  var root = document.documentElement;
  var body = document.body;
  var stage = document.getElementById("stage");
  var reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  var finePointer = window.matchMedia("(hover: hover) and (pointer: fine)").matches;
  var isSafari = /^((?!chrome|android|crios|fxios|edg).)*safari/i.test(navigator.userAgent);

  document.querySelectorAll("[data-year]").forEach(function (el) {
    el.textContent = new Date().getFullYear();
  });

  function store(key, val) {
    try {
      if (val === undefined) {
        var v = sessionStorage.getItem(key);
        sessionStorage.removeItem(key);
        return v;
      }
      sessionStorage.setItem(key, val);
    } catch (e) { /* private mode: arrive at the top instead */ }
    return null;
  }

  /* ============================================================
     1. Moving between pages
     ============================================================ */
  var arrive = store("pillango-arrive");
  store("pillango-transit");
  var TITLE_HOLD = 90;      // the title card stays a beat after the swap, then fades
  var LEAVE_MS = 800;       // the old page dissolving before the new one comes in
  var leaving = false;

  /* Arrival after a normal page load: the head script already put the
     title card up; let it fade as the page pulls into focus. */
  function settleArrival() {
    requestAnimationFrame(function () {
      requestAnimationFrame(function () {
        if (!root.classList.contains("transit-in")) {
          root.classList.remove("arriving", "arriving-back");
          return;
        }
        /* the title goes first, the page follows: no two headings on
           screen at once */
        setTimeout(function () { root.classList.remove("transit-in"); }, TITLE_HOLD);
        setTimeout(function () { root.classList.remove("arriving", "arriving-back"); }, TITLE_HOLD + 240);
        setTimeout(function () { if (!leaving) root.removeAttribute("data-transit"); }, TITLE_HOLD + 800);
      });
    });
  }
  settleArrival();

  /* --- fetching pages ahead of time --- */
  var pageCache = {};
  function pageKey(url) { return new URL(url, window.location.href).href.split("#")[0]; }
  function fetchPage(url) {
    var key = pageKey(url);
    if (!pageCache[key]) {
      pageCache[key] = fetch(key, { credentials: "same-origin" }).then(function (r) {
        if (!r.ok) throw new Error("status " + r.status);
        return r.text();
      });
      pageCache[key].catch(function () { delete pageCache[key]; });
    }
    return pageCache[key];
  }
  function prefetch(url) { if (url && canSwap()) fetchPage(url).catch(function () {}); }
  function canSwap() {
    return !!(stage && !root.classList.contains("flat") && window.fetch && window.DOMParser &&
              window.history && history.pushState);
  }

  /* --- leaving: the one way out of a page ---
     Flying to another page is the same move as flying to the next
     chapter: the next page's first chapter (or, going back, the previous
     page's last one) is placed one step ahead in the same space, and the
     camera glides to it exactly like any chapter change. Then the rest of
     that page is filled in around it, unseen. Legal pages, reduced motion
     and failures fall back to a normal page load. */
  function leave(url, dir, label, landLast, fromHistory) {
    if (leaving) return;
    leaving = true;
    var hardLoad = function () {
      if (landLast) store("pillango-arrive", "back");
      root.classList.add(dir === "back" ? "leaving-back" : "leaving");
      setTimeout(function () { window.location.href = url; }, reduceMotion ? 0 : 450);
    };
    if (!canSwap() || reduceMotion || !crossReady) { hardLoad(); return; }
    fetchPage(url).then(function (html) {
      var doc = new DOMParser().parseFromString(html, "text/html");
      doc.querySelectorAll("[src]").forEach(function (el) { el.setAttribute("src", new URL(el.getAttribute("src"), new URL(url, window.location.href)).href); });
      var next = doc.getElementById("stage");
      if (!next || !next.querySelector("[data-chapter]")) throw new Error("not a flight page");
      crossTo(doc, next, url, dir === "back" || !!landLast, fromHistory);
    }).catch(hardLoad);
  }
  var crossReady = false;

  /* after the glide: the page around the new chapter becomes the page */
  function adoptPage(doc, next, url, fromHistory) {
    document.title = doc.title;   /* before the URL changes, so analytics records the new title */
    if (!fromHistory) history.pushState({ pillango: true }, "", url);
    var dA = document.querySelector('meta[name="description"]'), dB = doc.querySelector('meta[name="description"]');
    if (dA && dB) dA.setAttribute("content", dB.getAttribute("content"));
    var canA = document.querySelector('link[rel="canonical"]'), canB = doc.querySelector('link[rel="canonical"]');
    if (canA && canB) canA.setAttribute("href", canB.getAttribute("href"));
    fromPage = stage.getAttribute("data-page");
    Array.prototype.slice.call(stage.attributes).forEach(function (at) {
      if (at.name !== "id" && at.name !== "class" && at.name !== "style") stage.removeAttribute(at.name);
    });
    Array.prototype.slice.call(next.attributes).forEach(function (at) {
      if (at.name !== "id" && at.name !== "class" && at.name !== "style") stage.setAttribute(at.name, at.value);
    });
    var railA = document.getElementById("rail"), railB = doc.getElementById("rail");
    if (railA && railB) railA.replaceWith(document.importNode(railB, true));
    ["overlay-links", "overlay-legal"].forEach(function (cls) {
      var a = document.querySelector("." + cls), b = doc.querySelector("." + cls);
      if (a && b) a.innerHTML = b.innerHTML;
    });
    document.querySelectorAll(".gate, .gate-light").forEach(function (el) { el.remove(); });
    doc.querySelectorAll(".gate, .gate-light").forEach(function (el) {
      stage.parentNode.insertBefore(document.importNode(el, true), stage.nextSibling);
    });
    initGate();
    initContact();
  }

  window.addEventListener("popstate", function () {
    if (!canSwap()) { window.location.reload(); return; }
    var path = window.location.pathname;
    var link = document.querySelector('.rail-pages a[href="' + path + '"], .overlay-links a[href="' + path + '"]');
    leave(window.location.href, "back", "", false, true);
  });
  window.addEventListener("pageshow", function (e) {
    if (e.persisted) {
      leaving = false;
      root.classList.remove("leaving", "leaving-back", "arriving", "arriving-back", "transit-in");
      root.removeAttribute("data-transit");
    }
  });

  function linkLabel(a) {
    if (a.getAttribute("data-label")) return a.getAttribute("data-label");
    var h = a.querySelector("h3, b");
    return (h || a).textContent.replace(/\s+/g, " ").trim().slice(0, 40);
  }

  /* Every internal link leaves through the same transition. */
  document.addEventListener("click", function (e) {
    if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
    var a = e.target.closest && e.target.closest("a[href]");
    if (!a || a.target || a.hasAttribute("download") || a.hasAttribute("data-goto")) return;
    var url = new URL(a.href, window.location.href);
    if (url.origin !== window.location.origin) return;
    if (url.pathname === window.location.pathname && url.hash) return;
    e.preventDefault();
    closeOverlay();
    leave(url.href, a.getAttribute("data-dir") || "forward", linkLabel(a));
  });
  /* hovering a page name starts fetching it */
  document.addEventListener("pointerover", function (e) {
    var a = e.target.closest && e.target.closest(".rail-pages a, .overlay-links a, .next-hint, a.box");
    if (a) prefetch(a.href);
  }, { passive: true });

  /* ============================================================
     2. The menu — the one way around the site
     ============================================================ */
  var burger = document.getElementById("burger");
  var overlay = document.getElementById("overlay-menu");
  function closeOverlay() {
    if (!overlay || overlay.hidden) return;
    overlay.hidden = true;
    body.classList.remove("menu-open");
    if (burger) {
      burger.setAttribute("aria-expanded", "false");
      burger.setAttribute("aria-label", "Open menu");
    }
  }
  if (burger && overlay) {
    burger.addEventListener("click", function () {
      var open = overlay.hidden;
      overlay.hidden = !open;
      body.classList.toggle("menu-open", open);
      burger.setAttribute("aria-expanded", String(open));
      burger.setAttribute("aria-label", open ? "Close menu" : "Open menu");
    });
  }

  /* ============================================================
     3. The bokeh — out-of-focus lights after the hero of the current
     site: rose and red to the left, magenta and violet across the top,
     falling away to blue-black at the lower right, with a few warm
     sparks. Each light is its own element, drifting on a CSS animation
     that the graphics chip runs by itself: the lights cost the page
     nothing while it flies. Three depths slide apart as you move.
     ============================================================ */
  /* The depths slide with the whole journey, not with one page: z is the
     camera's travel since the site opened (it carries on across page
     changes), and the slide is a slow wave, so it never jumps and never
     runs out of lights. */
  var bokehLayers = [];
  var bkBase = 0, bkZ = 0;
  function setBokehZ(z) {
    bkZ = z;
    var w = Math.sin(z / 2600);
    for (var i = 0; i < bokehLayers.length; i++) {
      bokehLayers[i].el.style.transform = "translate3d(0," + (-w * bokehLayers[i].depth * 16).toFixed(2) + "vh,0)";
    }
  }
  (function bokeh() {
    var host = document.getElementById("bokeh");
    if (!host) return;
    var seed = 11;
    function rnd() { seed = (seed * 16807) % 2147483647; return (seed - 1) / 2147483646; }
    var WARM = [[232, 96, 124], [214, 80, 104], [240, 128, 150], [200, 72, 98]];
    var COOL = [[182, 88, 158], [156, 82, 170], [128, 72, 160], [104, 64, 146], [205, 106, 168]];
    var LAYERS = [
      { n: 14, r: [3.5, 6],  a: [0.22, 0.40], soft: 0.30, depth: 0.25, dur: [12, 20] },
      { n: 32, r: [7, 12],   a: [0.24, 0.40], soft: 0.42, depth: 0.55, dur: [15, 25] },
      { n: 16, r: [13, 21],  a: [0.09, 0.17], soft: 0.65, depth: 1.0,  dur: [20, 32] }
    ];
    function rgba(c, a) { return "rgba(" + c[0] + "," + c[1] + "," + c[2] + "," + a.toFixed(3) + ")"; }
    var frag = document.createDocumentFragment();
    LAYERS.forEach(function (L) {
      var layer = document.createElement("div");
      layer.className = "bk-layer";
      for (var i = 0; i < L.n; i++) {
        var x = Math.pow(rnd(), 1.35) * 1.12 - 0.08;
        var y = Math.pow(rnd(), 1.9) * 1.05 - 0.08;                    // massed along the top
        var fall = 1 - 0.75 * Math.min(1, (x * 0.55 + y * 1.0) / 1.15); // dimmer toward lower right
        var col = (x < 0.3 && rnd() < 0.75 ? WARM : COOL);
        col = col[Math.floor(rnd() * col.length)];
        var a = (L.a[0] + rnd() * (L.a[1] - L.a[0])) * fall;
        var r = L.r[0] + rnd() * (L.r[1] - L.r[0]);                    // radius in vmin
        var rim = Math.max(66, 100 - L.soft * 30), edge = Math.max(80, 100 - L.soft * 8);
        var d = document.createElement("i");
        d.className = "bk";
        d.style.cssText =
          "left:" + (x * 100).toFixed(2) + "%;top:" + (y * 100).toFixed(2) + "%;" +
          "width:" + (r * 2).toFixed(2) + "vmin;height:" + (r * 2).toFixed(2) + "vmin;" +
          "margin:" + (-r).toFixed(2) + "vmin 0 0 " + (-r).toFixed(2) + "vmin;" +
          "background:radial-gradient(circle closest-side," + rgba(col, a * 0.7) + " 0%," + rgba(col, a * 0.8) + " 60%," +
            rgba(col, a * 0.95) + " " + rim + "%," + rgba(col, a * 0.45) + " " + edge + "%,rgba(0,0,0,0) 100%);" +
          "--dx:" + ((rnd() - 0.5) * 22).toFixed(2) + "vw;--dy:" + ((rnd() - 0.6) * 16).toFixed(2) + "vh;--ds:" + (0.9 + rnd() * 0.25).toFixed(3) + ";" +
          "animation-duration:" + (L.dur[0] + rnd() * (L.dur[1] - L.dur[0])).toFixed(1) + "s," + (5 + rnd() * 6).toFixed(1) + "s;" +
          "animation-delay:-" + (rnd() * 40).toFixed(1) + "s,-" + (rnd() * 10).toFixed(1) + "s;";
        layer.appendChild(d);
      }
      bokehLayers.push({ el: layer, depth: L.depth });
      frag.appendChild(layer);
    });
    var sparkLayer = document.createElement("div");
    sparkLayer.className = "bk-layer";
    for (var s = 0; s < 8; s++) {
      var sp = document.createElement("i");
      sp.className = "bk spark";
      var sr = 0.5 + rnd() * 0.6;
      sp.style.cssText =
        "left:" + (rnd() * 100).toFixed(2) + "%;top:" + (25 + rnd() * 72).toFixed(2) + "%;" +
        "width:" + (sr * 6).toFixed(2) + "vmin;height:" + (sr * 6).toFixed(2) + "vmin;margin:" + (-sr * 3).toFixed(2) + "vmin 0 0 " + (-sr * 3).toFixed(2) + "vmin;" +
        "--dx:" + ((rnd() - 0.5) * 10).toFixed(2) + "vw;--dy:" + (-(2 + rnd() * 8)).toFixed(2) + "vh;" +
        "animation-duration:" + (10 + rnd() * 10).toFixed(1) + "s," + (2 + rnd() * 4).toFixed(1) + "s;" +
        "animation-delay:-" + (rnd() * 30).toFixed(1) + "s,-" + (rnd() * 6).toFixed(1) + "s;";
      sparkLayer.appendChild(sp);
    }
    bokehLayers.push({ el: sparkLayer, depth: 0.3 });
    frag.appendChild(sparkLayer);
    host.appendChild(frag);
  })();

  /* ============================================================
     4. The cursor: a tiny ⅃L (a CSS cursor image) with a pool of
     orange light underneath that follows it and swells over anything
     you can click.
     ============================================================ */
  var HOT = "a[href], button, [role='button'], label, summary, input[type='submit']";
  if (finePointer) {
    var glowEl = document.createElement("div");
    glowEl.className = "cursor-light";
    glowEl.setAttribute("aria-hidden", "true");
    body.appendChild(glowEl);
    var gx = -500, gy = -500, tx = -500, ty = -500, glowOn = false;
    document.addEventListener("pointermove", function (e) {
      if (e.pointerType !== "mouse") return;
      tx = e.clientX; ty = e.clientY;
      if (!glowOn) { gx = tx; gy = ty; glowOn = true; glowEl.classList.add("is-on"); }
      var hot = e.target.closest && e.target.closest(HOT);
      glowEl.classList.toggle("is-hot", !!hot);
    }, { passive: true });
    document.addEventListener("pointerleave", function () { glowOn = false; glowEl.classList.remove("is-on"); });
    var following = false;
    var follow = function () {
      gx += (tx - gx) * (reduceMotion ? 1 : 0.22);
      gy += (ty - gy) * (reduceMotion ? 1 : 0.22);
      if (Math.abs(tx - gx) < 0.3 && Math.abs(ty - gy) < 0.3) { gx = tx; gy = ty; following = false; }
      glowEl.style.transform = "translate3d(" + gx.toFixed(1) + "px," + gy.toFixed(1) + "px,0)";
      if (following) requestAnimationFrame(follow);
    };
    document.addEventListener("pointermove", function () {
      if (!following) { following = true; requestAnimationFrame(follow); }
    }, { passive: true });
  }

  /* ============================================================
     5. Ripples: a click sends rings out from the point, and the page
     itself ripples like water (an SVG displacement filter over the
     stage). Skipped for reduced motion; Safari gets the rings only.
     ============================================================ */
  var rippleDisplace = null;
  if (!reduceMotion) {
    if (!isSafari) rippleDisplace = makeWater();
    document.addEventListener("pointerdown", function (e) {
      if (e.button !== 0) return;
      var r = document.createElement("span");
      r.className = "ripple";
      r.setAttribute("aria-hidden", "true");
      r.style.left = e.clientX + "px";
      r.style.top = e.clientY + "px";
      r.innerHTML = "<i></i><i></i><i></i>";
      body.appendChild(r);
      setTimeout(function () { r.remove(); }, 1600);
      if (rippleDisplace) rippleDisplace(e.clientX, e.clientY);
    }, { passive: true });
  }

  function makeWater() {
    var NS = "http://www.w3.org/2000/svg";
    /* The displacement map: one expanding ring of waves. Red pushes
       along x, green along y, mid-grey means "no movement". */
    var M = 256, c = document.createElement("canvas");
    c.width = c.height = M;
    var g = c.getContext("2d"), img = g.createImageData(M, M);
    for (var yy = 0; yy < M; yy++) {
      for (var xx = 0; xx < M; xx++) {
        var dx = (xx + 0.5) / M * 2 - 1, dy = (yy + 0.5) / M * 2 - 1;
        var r = Math.sqrt(dx * dx + dy * dy), o = (yy * M + xx) * 4;
        var env = r < 1 ? Math.exp(-Math.pow((r - 0.72) / 0.2, 2)) : 0;
        var w = Math.sin(r * 34) * env;
        img.data[o] = 128 + (r > 0 ? 127 * w * dx / r : 0);
        img.data[o + 1] = 128 + (r > 0 ? 127 * w * dy / r : 0);
        img.data[o + 2] = 128;
        img.data[o + 3] = 255;
      }
    }
    g.putImageData(img, 0, 0);
    var svg = document.createElementNS(NS, "svg");
    svg.setAttribute("aria-hidden", "true");
    svg.setAttribute("class", "fx-defs");
    body.appendChild(svg);
    var href = c.toDataURL();
    /* One filter per rippling layer, since each measures from its own
       top-left corner: the stage (the page) and the bokeh behind it. */
    var targets = [];
    [stage, document.getElementById("bokeh")].forEach(function (el, i) {
      if (!el) return;
      var id = "fx-water-" + i;
      var f = document.createElementNS(NS, "filter");
      f.setAttribute("id", id);
      f.setAttribute("filterUnits", "userSpaceOnUse");
      f.setAttribute("primitiveUnits", "userSpaceOnUse");
      f.setAttribute("color-interpolation-filters", "sRGB");
      f.innerHTML =
        '<feFlood flood-color="rgb(128,128,128)" result="flat"/>' +
        '<feImage preserveAspectRatio="none" x="0" y="0" width="1" height="1" result="ring"/>' +
        '<feComposite in="ring" in2="flat" operator="over" result="map"/>' +
        '<feDisplacementMap in="SourceGraphic" in2="map" scale="0" xChannelSelector="R" yChannelSelector="G"/>';
      svg.appendChild(f);
      var img = f.querySelector("feImage");
      img.setAttribute("href", href);
      targets.push({ el: el, id: id, f: f, img: img, disp: f.querySelector("feDisplacementMap") });
    });
    var anim = null;
    return function (cx, cy) {
      if (!targets.length) return;
      targets.forEach(function (t) {
        var r = t.el.getBoundingClientRect();
        t.ox = r.left; t.oy = r.top;
        t.f.setAttribute("x", 0); t.f.setAttribute("y", 0);
        t.f.setAttribute("width", Math.ceil(r.width)); t.f.setAttribute("height", Math.ceil(r.height));
        t.el.style.filter = "url(#" + t.id + ")";
      });
      var start = performance.now(), DUR = 1300;
      if (anim) cancelAnimationFrame(anim);
      (function step(now) {
        var t = Math.min(1, (now - start) / DUR);
        var e = 1 - Math.pow(1 - t, 3);
        var size = 60 + e * Math.max(window.innerWidth, window.innerHeight) * 1.1;
        var scale = (34 * Math.pow(1 - t, 1.6)).toFixed(2);
        targets.forEach(function (tg) {
          tg.img.setAttribute("x", cx - tg.ox - size / 2);
          tg.img.setAttribute("y", cy - tg.oy - size / 2);
          tg.img.setAttribute("width", size);
          tg.img.setAttribute("height", size);
          tg.disp.setAttribute("scale", scale);
        });
        if (t < 1) { anim = requestAnimationFrame(step); }
        else { anim = null; targets.forEach(function (tg) { tg.el.style.filter = ""; }); }
      })(start);
    };
  }

  /* ============================================================
     6. Held Still: a small password box. The password is checked by
     the server (/projects/held-still/gate.php); a right answer
     dissolves the page into light and opens the Held Still site.
     (Set up again whenever the Projects page is swapped in.)
     ============================================================ */
  var gate = null, gateOpener = null;
  function gateOpen() { return !!(gate && !gate.hidden); }
  function closeGate() {
    if (!gate) return;
    var g = gate;
    g.classList.remove("is-open");
    setTimeout(function () { g.hidden = true; }, 250);
    if (gateOpener) gateOpener.focus();
  }
  document.addEventListener("keydown", function (e) { if (e.key === "Escape" && gateOpen()) closeGate(); });
  function initGate() {
    gate = document.getElementById("gate");
    if (!gate) return;
    var form = document.getElementById("gate-form");
    var input = document.getElementById("gate-pw");
    var msg = document.getElementById("gate-msg");
    var boxEl = gate.querySelector(".gate-box");
    var endpoint = gate.getAttribute("data-endpoint");
    var openGate = function (opener) {
      gateOpener = opener || null;
      gate.hidden = false;
      msg.textContent = "";
      boxEl.classList.remove("is-wrong", "is-granted");
      requestAnimationFrame(function () { gate.classList.add("is-open"); input.focus(); });
    };
    document.querySelectorAll("[data-gate]").forEach(function (b) {
      b.addEventListener("click", function () { openGate(b); });
    });
    gate.querySelectorAll("[data-gate-close]").forEach(function (b) { b.addEventListener("click", closeGate); });
    gate.addEventListener("click", function (e) { if (e.target === gate) closeGate(); });

    var granted = function (url) {
      boxEl.classList.add("is-granted");
      msg.textContent = "Welcome.";
      setTimeout(function () { root.classList.add("gate-through"); }, 350);
      setTimeout(function () { window.location.href = url; }, reduceMotion ? 300 : 1500);
    };
    var wrong = function (text) {
      msg.textContent = text;
      boxEl.classList.remove("is-wrong");
      void boxEl.offsetWidth;
      boxEl.classList.add("is-wrong");
      input.select();
    };
    form.addEventListener("submit", function (e) {
      e.preventDefault();
      var pw = input.value;
      if (!pw) return;
      msg.textContent = "Checking…";
      var fd = new FormData();
      fd.append("password", pw);
      fetch(endpoint, { method: "POST", body: fd, credentials: "same-origin" })
        .then(function (res) {
          return res.json().catch(function () { return { ok: false, error: "offline", status: res.status }; })
            .then(function (j) { j.status = res.status; return j; });
        })
        .catch(function () { return { ok: false, error: "offline" }; })
        .then(function (j) {
          if (j.ok) return granted(j.url || "/projects/held-still/");
          if (j.error === "offline" && window.PILLANGO_DEMO_PASSWORD) {   // static preview only
            return pw === window.PILLANGO_DEMO_PASSWORD
              ? granted(gate.getAttribute("data-demo-url") || "/projects/held-still/")
              : wrong("That password isn’t right.");
          }
          if (j.error === "wrong") return wrong("That password isn’t right.");
          if (j.error === "too-many") return wrong("Too many tries. Wait a few minutes and try again.");
          if (j.error === "not-configured") return wrong("Access isn’t set up yet.");
          wrong("Couldn’t check the password. Try again.");
        });
    });
    if (window.location.hash === "#held-still") {
      var hs = document.querySelector("[data-gate]");
      setTimeout(function () { openGate(hs); }, 900);
    }
  }
  initGate();

  /* service pages (the ones with the ×) always open at the top, even on Back/Forward */
  if (document.querySelector(".close-x")) {
    if ("scrollRestoration" in history) history.scrollRestoration = "manual";
    var toTop = function () { if (!window.location.hash) window.scrollTo(0, 0); };
    toTop();
    window.addEventListener("pageshow", toTop);
  }

  /* ---------- the contact form: topic preselected from the page you came from ---------- */
  var fromPage = null;
  function initContact() {
    var form = document.getElementById("contact-form");
    if (!form || form.getAttribute("data-ready")) return;
    form.setAttribute("data-ready", "1");
    var sel = form.querySelector('select[name="topic"]'), msg = document.getElementById("contact-msg");
    var q = (window.location.search.match(/[?&]topic=([\w-]+)/) || [])[1];
    var from = fromPage;
    if (!from && document.referrer) {
      try {
        var r = new URL(document.referrer);
        if (r.origin === window.location.origin) from = r.pathname.replace(/\/(index\.html)?$/, "") || "/";
      } catch (e) {}
    }
    var pick = q ? sel.querySelector('option[value="' + q + '"]') : null;
    if (!pick && from) {
      Array.prototype.forEach.call(sel.options, function (o) {
        if (!pick && (" " + o.getAttribute("data-from") + " ").indexOf(" " + from + " ") >= 0) pick = o;
      });
    }
    if (pick) sel.value = pick.value;
    var pre = (window.location.search.match(/[?&]msg=([^&]*)/) || [])[1];
    var ta = form.querySelector("textarea");
    if (pre && ta && !ta.value) {
      try { ta.value = decodeURIComponent(pre.replace(/\+/g, " ")); } catch (e) {}
    }
    var done = function () {
      form.classList.add("is-sent");
      form.innerHTML = '<h2 class="display">Thank you</h2><p class="lede">Your message is on its way. We’ll write back soon.</p>';
    };
    if (/[?&]sent=1/.test(window.location.search)) return done();
    form.addEventListener("submit", function (e) {
      e.preventDefault();
      var btn = form.querySelector('button[type="submit"]');
      btn.disabled = true;
      msg.textContent = "Sending…";
      fetch(form.getAttribute("data-endpoint"), { method: "POST", body: new FormData(form), headers: { Accept: "application/json" }, credentials: "same-origin" })
        .then(function (res) { return res.json(); })
        .catch(function () { return { ok: false, error: "offline" }; })
        .then(function (j) {
          btn.disabled = false;
          if (j.ok || (j.error === "offline" && window.PILLANGO_DEMO_PASSWORD)) return done();   // the static preview has no PHP
          msg.textContent = {
            invalid: "Please fill in your name, a valid e-mail address and a message.",
            consent: "Please tick the box so we may reply to you.",
            "too-many": "Too many messages from here. Please try again later.",
            "not-configured": "The form isn’t switched on yet. Please try again later."
          }[j.error] || "Couldn’t send your message. Please try again.";
        });
    });
  }
  initContact();

  /* ============================================================
     7. The flight
     The listeners are set up once; initFlight() reads whichever page is
     in the stage (on load, and after every swap).
     ============================================================ */
  if (!stage) return;

  var UNIT_DEPTH = 1150;
  var FADE_OUT_START = 140, FADE_OUT_END = 620, FADE_IN_START = -2400, FADE_IN_END = -650;
  /* The stops. One push moves one chapter and no further. Leaving the
     page takes a fresh push after coming to rest on the last (or first)
     chapter. Right after arriving on a page, input waits until the new
     page has pulled into focus. */
  var SNAP_MS = 1100;          // chapter-to-chapter glide
  var WHEEL_TRIGGER = 24;      // wheel distance that makes one push (one notch of any mouse)
  var GESTURE_GAP = 280;       // quiet time that ends a gesture
  var REST_MS = 260;           // pause on arriving at a chapter
  var ARRIVAL_QUIET = 1100;    // after a full page load, the old gesture dies away first
  var SWIPE_TRIGGER = 50, SETTLE_MS = 160;

  var scrollSpace = document.getElementById("scroll-space");
  var grade = document.getElementById("grade");
  var nav = document.getElementById("nav");
  var supports3d = window.CSS && CSS.supports && CSS.supports("transform", "translateZ(1px)");
  var flat = reduceMotion || !supports3d;
  if (flat) root.classList.add("flat");
  else root.classList.add("snap");

  /* per-page state, filled by initFlight() */
  var CHAPTERS = [], TOTAL_DEPTH = 0, single = true, layers = [];
  var railStops = null, hint = null, isHome = false;
  var nextUrl = null, prevUrl = null, nextLabel = "", prevLabel = "";
  var targetP = 0, currentP = 0, lastFrame = 0, renderedP = -1;
  var snapIndex = 0, tween = null, loadedAt = 0, restAt = 0;
  var wheelAccum = 0, lastWheel = 0, armed = false, fresh = false, recent = [];
  var lastSig = 0, prevAd = 0, decaying = false;
  var touching = false, touchY = 0, touchDy = 0, settleTimer = null;

  function hexToRgb(hex) {
    var n = parseInt(hex.slice(1), 16);
    return [(n >> 16) & 255, (n >> 8) & 255, n & 255];
  }
  function lerpAt(p, pick) {
    if (p <= CHAPTERS[0].p) return pick(CHAPTERS[0]);
    for (var i = 1; i < CHAPTERS.length; i++) {
      if (p <= CHAPTERS[i].p) {
        var a = CHAPTERS[i - 1], b = CHAPTERS[i];
        var t = b.p > a.p ? (p - a.p) / (b.p - a.p) : 1;
        var va = pick(a), vb = pick(b);
        if (typeof va === "number") return va + (vb - va) * t;
        return [va[0] + (vb[0] - va[0]) * t, va[1] + (vb[1] - va[1]) * t, va[2] + (vb[2] - va[2]) * t];
      }
    }
    return pick(CHAPTERS[CHAPTERS.length - 1]);
  }
  function paintGrade(p) {
    if (!grade || !CHAPTERS.length) return;
    var c = lerpAt(p, function (ch) { return ch.rgb; });
    var v = lerpAt(p, function (ch) { return ch.veil; });
    grade.style.background = "rgba(" + Math.round(c[0]) + "," + Math.round(c[1]) + "," + Math.round(c[2]) + "," + v.toFixed(3) + ")";
  }

  function teardownFlight() {
    tween = null;
    clearTimeout(settleTimer);
    touching = false;
  }

  function initFlight(land, keepInput) {
    CHAPTERS = [];
    var els = stage.querySelectorAll("[data-chapter]");
    var total = 0, gaps = [];
    els.forEach(function (el, i) {
      var g = i === 0 ? 0 : parseFloat(el.getAttribute("data-gap") || "1.25");
      gaps.push(g); total += g;
    });
    if (total <= 0) total = 1;
    var cum = 0;
    els.forEach(function (el, i) {
      cum += gaps[i];
      var sky = el.getAttribute("data-sky") || "#0B0B0B";
      CHAPTERS.push({
        id: el.getAttribute("data-chapter"), el: el,
        p: Math.round((cum / total) * 1e5) / 1e5,
        rgb: hexToRgb(sky),
        veil: parseFloat(el.getAttribute("data-veil") || "0.7")
      });
    });
    TOTAL_DEPTH = Math.round(total * UNIT_DEPTH);
    single = CHAPTERS.length <= 1;
    isHome = stage.hasAttribute("data-home");
    nextUrl = stage.getAttribute("data-next");
    prevUrl = stage.getAttribute("data-prev");
    nextLabel = stage.getAttribute("data-next-label") || "";
    prevLabel = stage.getAttribute("data-prev-label") || "";
    hint = stage.querySelector(".next-hint");
    railStops = document.getElementById("rail-stops");
    if (nav) nav.classList.toggle("at-hero", isHome);
    if (!CHAPTERS.length) return;

    if (flat) {
      paintGrade(0);
      return;
    }
    if (scrollSpace) scrollSpace.style.height = single ? "0px" : TOTAL_DEPTH + "px";

    layers = CHAPTERS.map(function (ch) { return { el: ch.el, depth: ch.p * TOTAL_DEPTH, id: ch.id }; });
    layers.forEach(function (layer, i) {
      var gapPrev = i === 0 ? TOTAL_DEPTH : layer.depth - layers[i - 1].depth;
      layer.fadeInStart = -Math.min(-FADE_IN_START, gapPrev * 0.88);
      layer.fadeInEnd = -Math.min(-FADE_IN_END, gapPrev * 0.28);
    });
    if (railStops && !single) {
      railStops.innerHTML = "";
      CHAPTERS.forEach(function (ch) {
        var li = document.createElement("li");
        var b = document.createElement("button");
        b.type = "button";
        b.setAttribute("data-goto", ch.id);
        b.setAttribute("tabindex", "-1");
        b.setAttribute("aria-hidden", "true");
        li.appendChild(b);
        railStops.appendChild(li);
      });
    }

    var now = performance.now();
    restAt = now;
    if (keepInput) {
      loadedAt = now - ARRIVAL_QUIET;          // no extra wait: this was just another glide
      armed = false; wheelAccum = 0;           // the gesture that brought us here is spent
    } else {
      loadedAt = now; lastWheel = now; lastSig = now;
      armed = false; fresh = false; recent = []; prevAd = 0; decaying = false; wheelAccum = 0;
    }
    tween = null;

    snapIndex = 0;
    if (land === "last") travelTo(CHAPTERS.length - 1, true);
    else if (land) {
      for (var i = 0; i < CHAPTERS.length; i++) if (CHAPTERS[i].id === land) { travelTo(i, true); break; }
    } else {
      window.scrollTo(0, 0);
    }
    targetP = single ? 0 : Math.min(1, Math.max(0, window.scrollY / maxScroll()));
    currentP = targetP;
    renderedP = -1;
    render();

    /* fetch the neighbours now, so flying on never waits for the network */
    setTimeout(function () { prefetch(nextUrl); prefetch(prevUrl); }, 1200);
  }

  /* ---- crossing to another page: one chapter glide ---- */
  var crossing = null;
  crossReady = true;
  function layerOpacity(dz, fadeInStart, fadeInEnd) {
    if (!(dz > fadeInStart && dz < FADE_OUT_END)) return 0;
    if (dz < fadeInEnd) { var o = (dz - fadeInStart) / (fadeInEnd - fadeInStart); return o * o * o; }
    if (dz > FADE_OUT_START) return 1 - (dz - FADE_OUT_START) / (FADE_OUT_END - FADE_OUT_START);
    return 1;
  }
  function placeLayer(el, dz, fadeInStart, fadeInEnd) {
    var o = layerOpacity(dz, fadeInStart, fadeInEnd);
    el.style.visibility = o > 0 ? "visible" : "hidden";
    el.style.opacity = o.toFixed(3);
    if (o > 0) el.style.transform = "translateZ(" + dz.toFixed(1) + "px)";
  }
  function crossTo(doc, next, url, back, fromHistory) {
    var from = layers[snapIndex];
    var fromCh = CHAPTERS[snapIndex];
    var src = next.querySelectorAll("[data-chapter]");
    var idx = back ? src.length - 1 : 0;
    var target = document.importNode(src[idx], true);
    target.style.opacity = "0";
    target.style.visibility = "hidden";
    if (back) stage.insertBefore(target, from.el); else stage.appendChild(target);
    var gap = 1.3 * UNIT_DEPTH;
    crossing = {
      start: performance.now(), dist: back ? -gap : gap, from: from, fromCh: fromCh, target: target,
      fin: { start: -Math.min(-FADE_IN_START, gap * 0.88), end: -Math.min(-FADE_IN_END, gap * 0.28) },
      toRgb: hexToRgb(target.getAttribute("data-sky") || "#0B0B0B"),
      toVeil: parseFloat(target.getAttribute("data-veil") || "0.7"),
      done: function () {
        /* the new page's other chapters go in around the one on screen */
        var i;
        for (i = 0; i < idx; i++) stage.insertBefore(document.importNode(src[i], true), target);
        var after = target.nextSibling;
        for (i = idx + 1; i < src.length; i++) stage.insertBefore(document.importNode(src[i], true), after);
        layers.forEach(function (l) { l.el.remove(); });
        adoptPage(doc, next, url, fromHistory);
        crossing = null;
        leaving = false;
        var zEnd = bkZ;
        initFlight(back ? "last" : null, true);
        bkBase = zEnd - currentP * TOTAL_DEPTH;
        setBokehZ(zEnd);
      }
    };
  }
  function stepCrossing(now) {
    var c = crossing;
    var t = Math.min(1, (now - c.start) / SNAP_MS);
    var e = easeInOut(t);
    var travelled = c.dist * e;
    if (c.z0 == null) c.z0 = bkZ;
    setBokehZ(c.z0 + travelled);
    /* the chapter we are leaving moves exactly as it would in a glide */
    layers.forEach(function (layer) {
      if (layer === c.from) placeLayer(layer.el, travelled, layer.fadeInStart, layer.fadeInEnd);
      else { layer.el.style.opacity = "0"; layer.el.style.visibility = "hidden"; }
    });
    placeLayer(c.target, travelled - c.dist, c.fin.start, c.fin.end);
    var nearNew = Math.abs(travelled - c.dist) < Math.abs(travelled);
    c.from.el.classList.toggle("is-active", !nearNew);
    c.target.classList.toggle("is-active", nearNew);
    if (grade) {
      var fr = c.fromCh.rgb, fv = c.fromCh.veil;
      grade.style.background = "rgba(" + Math.round(fr[0] + (c.toRgb[0] - fr[0]) * e) + "," +
        Math.round(fr[1] + (c.toRgb[1] - fr[1]) * e) + "," + Math.round(fr[2] + (c.toRgb[2] - fr[2]) * e) + "," +
        (fv + (c.toVeil - fv) * e).toFixed(3) + ")";
    }
    if (t >= 1) c.done();
  }

  function maxScroll() { return Math.max(1, root.scrollHeight - window.innerHeight); }
  function readScroll() {
    targetP = single ? 0 : Math.min(1, Math.max(0, window.scrollY / maxScroll()));
    if (document.hidden || performance.now() - lastFrame > 250) { currentP = targetP; render(); }
  }

  function render() {
    if (!layers.length) return;
    renderedP = currentP;
    var camZ = currentP * TOTAL_DEPTH;
    paintGrade(currentP);
    setBokehZ(bkBase + camZ);
    var activeIdx = -1, bestDist = Infinity;
    layers.forEach(function (layer, idx) {
      var dz = camZ - layer.depth;
      if (!(dz > layer.fadeInStart && dz < FADE_OUT_END)) {
        layer.el.style.opacity = "0";
        layer.el.style.visibility = "hidden";
        return;
      }
      var o;
      if (dz < layer.fadeInEnd) {
        o = (dz - layer.fadeInStart) / (layer.fadeInEnd - layer.fadeInStart);
        o = o * o * o;
      } else if (dz > FADE_OUT_START) {
        o = 1 - (dz - FADE_OUT_START) / (FADE_OUT_END - FADE_OUT_START);
      } else {
        o = 1;
      }
      layer.el.style.visibility = "visible";
      layer.el.style.opacity = o.toFixed(3);
      layer.el.style.transform = "translateZ(" + dz.toFixed(1) + "px)";
      var dist = Math.abs(dz);
      if (dist < bestDist) { bestDist = dist; activeIdx = idx; }
    });
    layers.forEach(function (layer, idx) { layer.el.classList.toggle("is-active", idx === activeIdx); });
    if (nav) nav.classList.toggle("at-hero", isHome && activeIdx === 0 && currentP < 0.02);
    if (railStops && activeIdx >= 0) {
      var id = layers[activeIdx].id;
      railStops.querySelectorAll("button").forEach(function (b) {
        b.classList.toggle("is-current", b.getAttribute("data-goto") === id);
      });
    }
  }

  function frame(now) {
    lastFrame = now || performance.now();
    if (typeof window.__freezeP === "number") targetP = currentP = window.__freezeP;   // debug
    if (crossing) { stepCrossing(lastFrame); requestAnimationFrame(frame); return; }
    if (tween) currentP = targetP;             // the glide already eases
    else currentP += (targetP - currentP) * 0.12;
    if (Math.abs(targetP - currentP) < 0.00004) currentP = targetP;
    if (currentP !== renderedP) render();      // a resting page costs nothing
    requestAnimationFrame(frame);
  }

  function chapterTop(i) { return Math.round(CHAPTERS[i].p * maxScroll()); }
  function nearestChapter(y) {
    var best = 0, bd = Infinity;
    for (var i = 0; i < CHAPTERS.length; i++) {
      var d = Math.abs(chapterTop(i) - y);
      if (d < bd) { bd = d; best = i; }
    }
    return best;
  }
  function busy() {
    if (overlay && !overlay.hidden) return true;
    return gateOpen() || leaving;
  }
  /* a gentle sine ease: no lurch at the start, no snap at the end */
  function easeInOut(t) { return -(Math.cos(Math.PI * t) - 1) / 2; }

  function stepTween(now) {
    if (!tween) return;
    var t = Math.min(1, (now - tween.start) / SNAP_MS);
    window.scrollTo(0, Math.round(tween.from + (tween.to - tween.from) * easeInOut(t)));
    if (t < 1) requestAnimationFrame(stepTween);
    else { tween = null; restAt = now; wheelAccum = 0; }
  }
  function travelTo(i, instant) {
    if (!CHAPTERS.length) return;
    i = Math.max(0, Math.min(CHAPTERS.length - 1, i));
    snapIndex = i;
    var to = chapterTop(i);
    if (instant) { tween = null; window.scrollTo(0, to); restAt = performance.now(); return; }
    if (to === Math.round(window.scrollY)) return;
    tween = { from: window.scrollY, to: to, start: performance.now() };
    requestAnimationFrame(stepTween);
  }
  function nudgeHint() {
    if (!hint) return;
    hint.classList.remove("is-nudged");
    void hint.offsetWidth;
    hint.classList.add("is-nudged");
  }
  function push(dir) {
    if (tween || crossing || busy() || !CHAPTERS.length) return;
    var now = performance.now();
    if (now - loadedAt < ARRIVAL_QUIET) return;
    if (now - restAt < REST_MS) return;
    var next = snapIndex + dir;
    if (next >= CHAPTERS.length || next < 0) {
      var url = next < 0 ? prevUrl : nextUrl;
      if (!url) return;
      leave(url, next < 0 ? "back" : "forward", "", next < 0);
      return;
    }
    travelTo(next);
  }

  if (flat) {
    initFlight(null);
    return;
  }

  window.addEventListener("scroll", readScroll, { passive: true });
  window.addEventListener("resize", function () {
    renderedP = -1;
    readScroll();
    if (tween || touching || busy()) return;
    travelTo(snapIndex, true);
  });

  window.addEventListener("wheel", function (e) {
    if (busy() && !crossing) { if (leaving) e.preventDefault(); return; }
    e.preventDefault();
    var now = performance.now();
    var dy = e.deltaY * (e.deltaMode === 1 ? 16 : 1);
    var ad = Math.abs(dy);
    /* How a trackpad really scrolls: a swipe rises for a few events, then
       its momentum dies away in a long tail of tiny events that can go on
       for seconds. So:
       - while a new page settles, everything waits (and resets);
       - the faint tail (under 4 px) never counts as scrolling, and never
         hides the start of the next swipe;
       - a new swipe is either one that follows a real pause, or one that
         rises again out of a dying stream (fingers back on the pad);
       - a mouse wheel kept rolling is a steady stream: it carries on
         chapter by chapter, but can't leave the page on its own. */
    if (now - loadedAt < ARRIVAL_QUIET) {
      lastSig = now; prevAd = ad; armed = false; decaying = false; wheelAccum = 0; recent = [];
      return;
    }
    var sig = ad >= 4;
    var gap = now - lastSig > GESTURE_GAP;
    var rising = decaying && ad >= 5 && ad > prevAd * 1.5 + 1;
    if (sig && (gap || rising)) { armed = true; fresh = true; wheelAccum = 0; recent = []; decaying = false; }
    if (ad < prevAd) decaying = true;
    prevAd = ad;
    if (sig) {
      lastSig = now;
      recent.push(ad);
      if (recent.length > 8) recent.shift();
    }
    lastWheel = now;
    if (tween || crossing || !sig) return;
    if (!armed && now - restAt > 380 && recent.length >= 6 && ad >= 8 &&
        recent[recent.length - 1] >= recent[0] * 0.95 && !decaying) {
      armed = true; fresh = false; wheelAccum = 0;
    }
    if (!armed) return;
    wheelAccum += dy;
    if (Math.abs(wheelAccum) >= WHEEL_TRIGGER) {
      var dir = wheelAccum > 0 ? 1 : -1;
      wheelAccum = 0; armed = false; recent = []; decaying = false;
      push(dir);
    }
  }, { passive: false });

  window.addEventListener("touchstart", function (e) {
    if (busy() || e.touches.length !== 1) { touching = false; return; }
    touching = true; touchY = e.touches[0].clientY; touchDy = 0;
  }, { passive: true });
  window.addEventListener("touchmove", function (e) {
    if (!touching) return;
    touchDy = touchY - e.touches[0].clientY;
    e.preventDefault();
  }, { passive: false });
  window.addEventListener("touchend", function () {
    if (!touching) return;
    touching = false;
    if (Math.abs(touchDy) >= SWIPE_TRIGGER) push(touchDy > 0 ? 1 : -1);
  }, { passive: true });
  window.addEventListener("touchcancel", function () { touching = false; }, { passive: true });

  document.addEventListener("keydown", function (e) {
    if (busy()) return;
    var t = e.target;
    if (t && (t.tagName === "INPUT" || t.tagName === "TEXTAREA" || t.tagName === "SELECT" || t.isContentEditable)) return;
    var space = e.key === " " || e.key === "Spacebar";
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

  window.addEventListener("scroll", function () {
    if (tween || touching) return;
    clearTimeout(settleTimer);
    settleTimer = setTimeout(function () {
      if (tween || touching || busy() || !CHAPTERS.length) return;
      var i = nearestChapter(window.scrollY);
      if (Math.abs(chapterTop(i) - window.scrollY) > 2) travelTo(i); else snapIndex = i;
    }, SETTLE_MS);
  }, { passive: true });

  /* a chapter dot on the rail */
  document.addEventListener("click", function (e) {
    var link = e.target.closest && e.target.closest("[data-goto]");
    if (!link) return;
    e.preventDefault();
    closeOverlay();
    for (var i = 0; i < CHAPTERS.length; i++) {
      if (CHAPTERS[i].id === link.getAttribute("data-goto")) { travelTo(i); break; }
    }
  });

  var landOn = null;
  if (/^#ch-/.test(window.location.hash)) landOn = window.location.hash.replace("#ch-", "");
  else if (arrive === "back") landOn = "last";
  history.replaceState({ pillango: true }, "", window.location.href);
  initFlight(landOn);
  requestAnimationFrame(frame);
})();
