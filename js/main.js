/* ============================================================
   Pillango Productions — site engine.

   One continuous flight through the site. Every page in the menu is
   a stretch of the same journey, in menu order:
     Home → About → Services → Partners → Post-production → Blog →
     Projects → Book → (back to Home)
   Inside a page, native scroll drives a camera along the z-axis
   through its chapters; a push past the last chapter flies on into
   the next page (<body data-next>), a push back past the first returns
   to the previous one (<body data-prev>). Each page arrives out of
   focus and pulls into focus.

   The chapter spine is read from the markup: every [data-chapter]
   layer inside #stage carries
     data-gap   distance from the previous chapter (1 = one unit)
     data-sky   the colour laid over the background while on screen
     data-veil  how much of that colour covers the bokeh, 0–1

   Also here: the live bokeh background (every page), the orange light
   under the cursor, the water ripple on click, the menu, and the
   Held Still password box.
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
     1. Arrival and departure: out of focus → into focus
     ============================================================ */
  var arrive = store("pillango-arrive");
  /* The head script already put the name of this page on screen (the
     "title card") if we flew in from another page; let it fade as the
     page pulls into focus. */
  var TRANSIT_OUT = 1500;
  requestAnimationFrame(function () {
    requestAnimationFrame(function () {
      root.classList.remove("arriving");
      if (root.classList.contains("transit-in")) {
        setTimeout(function () { root.classList.remove("transit-in"); }, 350);
        setTimeout(function () { if (!leaving) root.removeAttribute("data-transit"); }, 350 + TRANSIT_OUT);
      }
    });
  });
  store("pillango-transit");

  /* Leaving: the page drifts past the camera and out of focus, the light
     behind it swells, and the name of the next page settles in the middle
     of the screen. The next page picks the same card up and fades it. */
  var LEAVE_MS = 1150;
  var leaving = false;
  /* landLast: scrolling back into the previous page lands on its last
     chapter; clicking a link always lands on the intro. */
  function leave(url, dir, label, landLast) {
    if (leaving) return;
    leaving = true;
    if (landLast) store("pillango-arrive", "back");
    if (label) {
      store("pillango-transit", JSON.stringify({ label: label, dir: dir || "forward" }));
      root.setAttribute("data-transit", label);
    }
    root.classList.remove("transit-in");
    root.classList.add(dir === "back" ? "leaving-back" : "leaving");
    setTimeout(function () { window.location.href = url; }, reduceMotion ? 0 : LEAVE_MS);
  }
  window.addEventListener("pageshow", function (e) {
    if (e.persisted) {
      leaving = false;
      root.classList.remove("leaving", "leaving-back", "arriving", "transit-in");
      root.removeAttribute("data-transit");
    }
  });
  function linkLabel(a) {
    if (a.getAttribute("data-label")) return a.getAttribute("data-label");
    var h = a.querySelector("h3, b");
    return (h || a).textContent.replace(/\s+/g, " ").trim().slice(0, 40);
  }

  /* Every internal link leaves through the same focus-out. */
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
     3. The bokeh — drawn live at screen resolution
     Out-of-focus lights after the hero of the current site: rose and
     red to the left, magenta and violet across the top, falling away
     to blue-black at the lower right, with a few warm sparks. Three
     depths drift at different speeds and slide apart as you fly.
     ============================================================ */
  var bokehShift = 0;
  (function bokeh() {
    var canvas = document.getElementById("bokeh");
    if (!canvas || !canvas.getContext) return;
    var ctx = canvas.getContext("2d");
    var seed = 11;
    function rnd() { seed = (seed * 16807) % 2147483647; return (seed - 1) / 2147483646; }

    var WARM = [[232, 96, 124], [214, 80, 104], [240, 128, 150], [200, 72, 98]];
    var COOL = [[182, 88, 158], [156, 82, 170], [128, 72, 160], [104, 64, 146], [205, 106, 168]];
    var LAYERS = [
      { n: 14, r: [0.035, 0.06], a: [0.22, 0.40], soft: 0.30, speed: 0.35, depth: 0.25 },
      { n: 32, r: [0.07, 0.12],  a: [0.24, 0.40], soft: 0.42, speed: 0.6,  depth: 0.55 },
      { n: 16, r: [0.13, 0.21],  a: [0.09, 0.17], soft: 0.65, speed: 0.9,  depth: 1.0 }
    ];
    var discs = [];
    LAYERS.forEach(function (L) {
      for (var i = 0; i < L.n; i++) {
        var x = Math.pow(rnd(), 1.35) * 1.12 - 0.08;
        var y = Math.pow(rnd(), 1.9) * 1.05 - 0.08;                   // massed along the top
        var fall = 1 - 0.75 * Math.min(1, (x * 0.55 + y * 1.0) / 1.15); // dimmer toward lower right
        var pal = x < 0.3 && rnd() < 0.75 ? WARM : COOL;
        discs.push({
          x: x, y: y,
          rn: L.r[0] + rnd() * (L.r[1] - L.r[0]),
          a: (L.a[0] + rnd() * (L.a[1] - L.a[0])) * fall,
          col: pal[Math.floor(rnd() * pal.length)],
          soft: L.soft,
          vx: (rnd() - 0.6) * 0.006 * L.speed,
          vy: -(0.002 + rnd() * 0.006) * L.speed,
          sw: 0.006 + rnd() * 0.016, sf: 0.04 + rnd() * 0.1, ph: rnd() * 6.283,
          depth: L.depth
        });
      }
    });
    var sparks = [];
    for (var s = 0; s < 8; s++) {
      sparks.push({
        x: rnd(), y: 0.25 + rnd() * 0.72, rn: 0.005 + rnd() * 0.006,
        a: 0.55 + rnd() * 0.45, tw: 0.3 + rnd() * 0.7, ph: rnd() * 6.283,
        vx: (rnd() - 0.5) * 0.004, vy: -(0.001 + rnd() * 0.003)
      });
    }

    var W = 0, H = 0, bg = null, sparkSprite = null;
    function sprite(r, col, a, soft) {
      var size = Math.ceil(r * 2 + 4), c = document.createElement("canvas");
      c.width = c.height = size;
      var g = c.getContext("2d"), m = size / 2;
      var gr = g.createRadialGradient(m, m, 0, m, m, r);
      var rgb = col[0] + "," + col[1] + "," + col[2];
      gr.addColorStop(0, "rgba(" + rgb + "," + (a * 0.7) + ")");
      gr.addColorStop(0.6, "rgba(" + rgb + "," + (a * 0.8) + ")");
      gr.addColorStop(Math.max(0.66, 1 - soft * 0.3), "rgba(" + rgb + "," + (a * 0.95) + ")");  // a soft rim
      gr.addColorStop(Math.max(0.8, 1 - soft * 0.08), "rgba(" + rgb + "," + (a * 0.45) + ")");
      gr.addColorStop(1, "rgba(" + rgb + ",0)");
      g.fillStyle = gr;
      g.beginPath(); g.arc(m, m, r, 0, 6.2832); g.fill();
      return c;
    }
    function glow(g, x, y, r, css) {
      var gr = g.createRadialGradient(x, y, 0, x, y, r);
      gr.addColorStop(0, css); gr.addColorStop(1, "rgba(0,0,0,0)");
      g.fillStyle = gr; g.fillRect(0, 0, W, H);
    }
    function build() {
      var dpr = Math.min(window.devicePixelRatio || 1, 2);
      /* the canvas overhangs the screen a little (css/style.css), so the
         water ripple has real light to pull in at the edges */
      var cw = canvas.clientWidth || window.innerWidth, ch = canvas.clientHeight || window.innerHeight;
      if (cw * ch * dpr * dpr > 4.6e6) dpr = Math.sqrt(4.6e6 / (cw * ch));
      W = canvas.width = Math.round(cw * dpr);
      H = canvas.height = Math.round(ch * dpr);
      bg = document.createElement("canvas");
      bg.width = W; bg.height = H;
      var g = bg.getContext("2d");
      var lg = g.createLinearGradient(0, 0, W, H);
      lg.addColorStop(0, "#2d1524");
      lg.addColorStop(0.42, "#1e1423");
      lg.addColorStop(0.75, "#0f1119");
      lg.addColorStop(1, "#05090d");
      g.fillStyle = lg; g.fillRect(0, 0, W, H);
      glow(g, W * 0.0, H * 0.4, Math.max(W, H) * 0.45, "rgba(196,70,98,0.5)");
      glow(g, W * 0.38, H * 0.0, Math.max(W, H) * 0.55, "rgba(128,56,140,0.42)");
      glow(g, W * 0.95, H * 0.95, Math.max(W, H) * 0.5, "rgba(4,12,18,0.6)");
      var unit = Math.min(W, H) * 1.05;
      discs.forEach(function (d) { d.r = d.rn * unit; d.img = sprite(d.r, d.col, d.a, d.soft); });
      var sr = unit * 0.02;
      sparkSprite = document.createElement("canvas");
      sparkSprite.width = sparkSprite.height = Math.ceil(sr * 2);
      var sg = sparkSprite.getContext("2d"), m = sr;
      var gr = sg.createRadialGradient(m, m, 0, m, m, sr);
      gr.addColorStop(0, "rgba(255,238,210,1)");
      gr.addColorStop(0.12, "rgba(255,196,140,0.9)");
      gr.addColorStop(0.35, "rgba(255,150,80,0.28)");
      gr.addColorStop(1, "rgba(255,130,60,0)");
      sg.fillStyle = gr; sg.fillRect(0, 0, sr * 2, sr * 2);
      sparks.forEach(function (p) { p.r = p.rn * unit; });
    }
    function wrap(v) { return ((v + 0.15) % 1.3 + 1.3) % 1.3 - 0.15; }
    function draw(t) {
      ctx.globalCompositeOperation = "source-over";
      ctx.globalAlpha = 1;
      ctx.drawImage(bg, 0, 0);
      ctx.globalCompositeOperation = "lighter";
      for (var i = 0; i < discs.length; i++) {
        var d = discs[i];
        var x = wrap(d.x + d.vx * t + Math.sin(t * d.sf + d.ph) * d.sw) * W;
        var y = wrap(d.y + d.vy * t + Math.cos(t * d.sf * 0.8 + d.ph) * d.sw * 0.6 - bokehShift * d.depth * 0.22) * H;
        ctx.globalAlpha = 0.82 + 0.18 * Math.sin(t * d.sf * 2 + d.ph);
        ctx.drawImage(d.img, x - d.img.width / 2, y - d.img.height / 2);
      }
      for (var j = 0; j < sparks.length; j++) {
        var p = sparks[j];
        var sx = wrap(p.x + p.vx * t) * W, sy = wrap(p.y + p.vy * t - bokehShift * 0.3) * H;
        ctx.globalAlpha = p.a * (0.55 + 0.45 * Math.sin(t * p.tw * 2 + p.ph));
        var k = p.r * 6 / sparkSprite.width;
        ctx.drawImage(sparkSprite, sx - p.r * 3, sy - p.r * 3, sparkSprite.width * k, sparkSprite.height * k);
      }
      ctx.globalAlpha = 1;
      ctx.globalCompositeOperation = "source-over";
    }
    build();
    var t0 = performance.now(), last = 0;
    draw(12);
    if (!reduceMotion) {
      (function loop(now) {
        if (now - last > 32) {       // ~30 fps is plenty for drifting light
          last = now;
          draw(12 + (now - t0) / 1000);
        }
        requestAnimationFrame(loop);
      })(t0);
    }
    var rs;
    window.addEventListener("resize", function () {
      clearTimeout(rs);
      rs = setTimeout(function () { build(); draw(12 + (performance.now() - t0) / 1000); }, 150);
    });
  })();

  /* ============================================================
     4. The cursor: a tiny ⅃L (a CSS cursor image) with a pool of
     orange light underneath that follows it and swells over anything
     you can click.
     ============================================================ */
  var HOT = "a[href], button, [role='button'], label, summary, .box, input[type='submit']";
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
    (function follow() {
      gx += (tx - gx) * (reduceMotion ? 1 : 0.22);
      gy += (ty - gy) * (reduceMotion ? 1 : 0.22);
      glowEl.style.transform = "translate3d(" + gx.toFixed(1) + "px," + gy.toFixed(1) + "px,0)";
      requestAnimationFrame(follow);
    })();
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
     ============================================================ */
  var gate = document.getElementById("gate");
  var gateOpener = null;
  function gateOpen() { return gate && !gate.hidden; }
  if (gate) {
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
    var closeGate = function () {
      gate.classList.remove("is-open");
      setTimeout(function () { gate.hidden = true; }, 250);
      if (gateOpener) gateOpener.focus();
    };
    document.querySelectorAll("[data-gate]").forEach(function (b) {
      b.addEventListener("click", function () { openGate(b); });
    });
    gate.querySelectorAll("[data-gate-close]").forEach(function (b) { b.addEventListener("click", closeGate); });
    gate.addEventListener("click", function (e) { if (e.target === gate) closeGate(); });
    document.addEventListener("keydown", function (e) { if (e.key === "Escape" && gateOpen()) closeGate(); });

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
      setTimeout(function () { openGate(hs); }, 700);
    }
  }

  /* ============================================================
     7. The flight
     ============================================================ */
  if (!stage) return;

  var UNIT_DEPTH = 1150;
  var CHAPTERS = [], TOTAL_DEPTH = 0;
  (function spine() {
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
      CHAPTERS.push({
        id: el.getAttribute("data-chapter"),
        el: el,
        p: Math.round((cum / total) * 1e5) / 1e5,
        sky: el.getAttribute("data-sky") || "#0B0B0B",
        veil: parseFloat(el.getAttribute("data-veil") || "0.7")
      });
    });
    TOTAL_DEPTH = Math.round(total * UNIT_DEPTH);
  })();
  if (!CHAPTERS.length) return;
  var single = CHAPTERS.length === 1;

  var scrollSpace = document.getElementById("scroll-space");
  var grade = document.getElementById("grade");
  var railStops = document.getElementById("rail-stops");
  var nav = document.getElementById("nav");
  var nextUrl = body.getAttribute("data-next");
  var prevUrl = body.getAttribute("data-prev");
  var nextLabel = body.getAttribute("data-next-label") || "";
  var prevLabel = body.getAttribute("data-prev-label") || "";
  var hint = stage.querySelector(".next-hint");

  if (scrollSpace) scrollSpace.style.height = single ? "0px" : TOTAL_DEPTH + "px";

  function hexToRgb(hex) {
    var n = parseInt(hex.slice(1), 16);
    return [(n >> 16) & 255, (n >> 8) & 255, n & 255];
  }
  CHAPTERS.forEach(function (ch) { ch.rgb = hexToRgb(ch.sky); });
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
    if (!grade) return;
    var c = lerpAt(p, function (ch) { return ch.rgb; });
    var v = lerpAt(p, function (ch) { return ch.veil; });
    grade.style.background = "rgba(" + Math.round(c[0]) + "," + Math.round(c[1]) + "," + Math.round(c[2]) + "," + v.toFixed(3) + ")";
  }

  /* A deep link (/#ch-name) lands on its chapter; arriving backwards
     from the next page lands on the last one. */
  var landOn = null;
  if (/^#ch-/.test(window.location.hash)) landOn = window.location.hash.replace("#ch-", "");
  else if (arrive === "back") landOn = CHAPTERS[CHAPTERS.length - 1].id;

  var supports3d = window.CSS && CSS.supports && CSS.supports("transform", "translateZ(1px)");
  if (reduceMotion || !supports3d) {
    root.classList.add("flat");
    paintGrade(0);
    if (landOn) {
      var el = document.getElementById("ch-" + landOn);
      if (el) el.scrollIntoView();
    }
    return;
  }

  /* ---- layers ---- */
  var FADE_OUT_START = 140, FADE_OUT_END = 620, FADE_IN_START = -2400, FADE_IN_END = -650;
  var layers = CHAPTERS.map(function (ch) { return { el: ch.el, depth: ch.p * TOTAL_DEPTH, id: ch.id }; });
  layers.forEach(function (layer, i) {
    var gapPrev = i === 0 ? TOTAL_DEPTH : layer.depth - layers[i - 1].depth;
    layer.fadeInStart = -Math.min(-FADE_IN_START, gapPrev * 0.88);
    layer.fadeInEnd = -Math.min(-FADE_IN_END, gapPrev * 0.28);
  });

  /* the rail: every page by name, this page's chapters as dots beneath it */
  if (railStops && !single) {
    CHAPTERS.forEach(function (ch) {
      var li = document.createElement("li");
      var b = document.createElement("button");
      b.type = "button";
      b.setAttribute("data-goto", ch.id);
      b.setAttribute("tabindex", "-1");
      li.appendChild(b);
      railStops.appendChild(li);
    });
  }

  var targetP = 0, currentP = 0, lastFrame = 0;
  function maxScroll() { return Math.max(1, root.scrollHeight - window.innerHeight); }
  function readScroll() {
    targetP = single ? 0 : Math.min(1, Math.max(0, window.scrollY / maxScroll()));
    if (document.hidden || performance.now() - lastFrame > 250) { currentP = targetP; render(); }
  }
  window.addEventListener("scroll", readScroll, { passive: true });
  window.addEventListener("resize", readScroll);

  function frame(now) {
    lastFrame = now || performance.now();
    if (typeof window.__freezeP === "number") targetP = currentP = window.__freezeP;   // debug
    currentP += (targetP - currentP) * 0.085;
    if (Math.abs(targetP - currentP) < 0.00004) currentP = targetP;
    render();
    requestAnimationFrame(frame);
  }

  function render() {
    var camZ = currentP * TOTAL_DEPTH;
    paintGrade(currentP);
    bokehShift = currentP;

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
      /* rack focus: soft in the distance, sharp at the camera */
      var blur = dz < 0 ? Math.min(10, Math.max(0, (-dz - 60) / 85)) : Math.min(8, Math.max(0, dz - 40) / 60);
      layer.el.style.visibility = "visible";
      layer.el.style.opacity = o.toFixed(3);
      layer.el.style.transform = "translateZ(" + dz.toFixed(1) + "px)";
      layer.el.style.filter = blur > 0.25 ? "blur(" + blur.toFixed(1) + "px)" : "";
      var dist = Math.abs(dz);
      if (dist < bestDist) { bestDist = dist; activeIdx = idx; }
    });
    layers.forEach(function (layer, idx) { layer.el.classList.toggle("is-active", idx === activeIdx); });
    if (nav) nav.classList.toggle("at-hero", body.classList.contains("home") && activeIdx === 0 && currentP < 0.02);

    if (railStops && activeIdx >= 0) {
      var id = layers[activeIdx].id;
      railStops.querySelectorAll("button").forEach(function (b) {
        b.classList.toggle("is-current", b.getAttribute("data-goto") === id);
      });
    }
  }

  /* ---- the detent: one push, one chapter; past the ends, the next page ---- */
  /* The stops. One push moves one chapter and no further: a push is one
     gesture, and the next needs a fresh one (a pause in the wheel or a
     new swipe). Leaving the page takes more: you must have come to rest
     on the last (or first) chapter first, and then push again. Right
     after arriving on a page all input waits until the old gesture —
     trackpad momentum included — has died away, so one long flick can
     never carry you past more than one page. */
  var SNAP_MS = 1000;          // chapter-to-chapter glide
  var WHEEL_TRIGGER = 60;      // wheel distance that makes one push
  var GESTURE_GAP = 280;       // quiet time that ends a gesture
  var REST_MS = 260;           // pause on arriving at a chapter
  var EDGE_HOLD = 650;         // rest on the last chapter before the page can be left
  var ARRIVAL_QUIET = 1300;    // nothing moves in the first moment on a new page
  var SWIPE_TRIGGER = 50, SETTLE_MS = 160;
  var loadedAt = performance.now();
  root.classList.add("snap");

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
    return gateOpen();
  }
  function easeInOut(t) { return t < 0.5 ? 16 * t * t * t * t * t : 1 - Math.pow(-2 * t + 2, 5) / 2; }

  var snapIndex = nearestChapter(window.scrollY), tween = null;
  /* start disarmed: a gesture still running from the previous page is ignored */
  var wheelAccum = 0, lastWheel = performance.now(), armed = false, restAt = performance.now();
  var touching = false, touchY = 0, touchDy = 0, settleTimer = null;

  function stepTween(now) {
    if (!tween) return;
    var t = Math.min(1, (now - tween.start) / SNAP_MS);
    window.scrollTo(0, Math.round(tween.from + (tween.to - tween.from) * easeInOut(t)));
    if (t < 1) requestAnimationFrame(stepTween);
    else { tween = null; restAt = now; wheelAccum = 0; }
  }
  function travelTo(i, instant) {
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
    if (tween || busy() || leaving) return;
    var now = performance.now();
    if (now - loadedAt < ARRIVAL_QUIET) return;
    if (now - restAt < REST_MS) return;
    var next = snapIndex + dir;
    if (next >= CHAPTERS.length || next < 0) {
      var url = next < 0 ? prevUrl : nextUrl;
      if (!url) return;
      if (now - restAt < EDGE_HOLD) { if (next > 0) nudgeHint(); return; }
      leave(url, next < 0 ? "back" : "forward", next < 0 ? prevLabel : nextLabel, next < 0);
      return;
    }
    travelTo(next);
  }

  window.addEventListener("wheel", function (e) {
    if (busy()) return;
    e.preventDefault();
    var now = performance.now();
    var dy = e.deltaY * (e.deltaMode === 1 ? 16 : 1);
    /* Anything that arrives in the first moment on a page is the tail of
       the gesture that brought us here: it keeps the wheel locked, so
       only a fresh gesture after a pause can move on. */
    if (now - loadedAt < ARRIVAL_QUIET) { lastWheel = now; armed = false; wheelAccum = 0; return; }
    if (now - lastWheel > GESTURE_GAP) { wheelAccum = 0; armed = true; }
    lastWheel = now;
    if (tween || !armed || leaving) return;
    if (Math.abs(dy) < 4) return;            // the faint tail of momentum never counts
    wheelAccum += dy;
    if (Math.abs(wheelAccum) >= WHEEL_TRIGGER) {
      var dir = wheelAccum > 0 ? 1 : -1;
      wheelAccum = 0; armed = false;
      push(dir);
    }
  }, { passive: false });

  window.addEventListener("touchstart", function (e) {
    if (busy() || e.touches.length !== 1) { touching = false; return; }
    armed = true;
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
      if (tween || touching || busy()) return;
      var i = nearestChapter(window.scrollY);
      if (Math.abs(chapterTop(i) - window.scrollY) > 2) travelTo(i); else snapIndex = i;
    }, SETTLE_MS);
  }, { passive: true });
  window.addEventListener("resize", function () {
    if (tween || touching || busy()) return;
    travelTo(snapIndex, true);
  });

  document.querySelectorAll("[data-goto]").forEach(function (link) {
    link.addEventListener("click", function (e) {
      e.preventDefault();
      closeOverlay();
      for (var i = 0; i < CHAPTERS.length; i++) {
        if (CHAPTERS[i].id === link.getAttribute("data-goto")) { travelTo(i); break; }
      }
    });
  });

  if (landOn) {
    for (var li = 0; li < CHAPTERS.length; li++) {
      if (CHAPTERS[li].id === landOn) { travelTo(li, true); break; }
    }
  }
  targetP = single ? 0 : Math.min(1, Math.max(0, window.scrollY / maxScroll()));
  currentP = targetP;
  render();
  requestAnimationFrame(frame);
})();
