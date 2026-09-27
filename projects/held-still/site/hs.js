/* Held Still — rows, the detail window, the soundtrack. No libraries. */
(function () {
  "use strict";
  var DATA = JSON.parse(document.getElementById("hs-data").textContent);
  var body = document.body;

  function esc(s) {
    return String(s == null ? "" : s).replace(/[&<>"]/g, function (c) {
      return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;" }[c];
    });
  }
  var ICON_PLAY = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 4.5v15l12-7.5z" fill="currentColor"/></svg>';
  var ICON_DOWN = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 4v11m-5-5 5 5 5-5M5 20h14" fill="none" stroke="currentColor" stroke-width="1.8"/></svg>';
  var CONF = '<p class="confidential">Confidential · Pillango Productions · Do not distribute</p>';

  /* ---------- soundtrack ---------- */
  var audio = document.getElementById("hs-audio");
  var ask = document.getElementById("sound-ask");
  var toggle = document.getElementById("sound-toggle");
  function setOn(on) {
    toggle.classList.toggle("is-on", on);
    toggle.setAttribute("aria-label", on ? "Mute soundtrack" : "Play soundtrack");
  }
  function play() {
    audio.volume = 0.5;
    var p = audio.play();
    if (p && p.then) p.then(function () { setOn(true); }).catch(function () { setOn(false); });
    else setOn(true);
  }
  function stop() { audio.pause(); setOn(false); }
  ask.addEventListener("click", function (e) {
    var b = e.target.closest("[data-sound]");
    if (!b) return;
    ask.classList.add("is-gone");
    setTimeout(function () { ask.hidden = true; }, 450);
    if (b.getAttribute("data-sound") === "on") play();
  });
  toggle.addEventListener("click", function () { if (audio.paused) play(); else stop(); });

  /* ---------- top bar: solid once past the hero, current section lit ---------- */
  var top = document.getElementById("top");
  var links = Array.prototype.slice.call(document.querySelectorAll(".top-links a"));
  var sections = links.map(function (a) { return document.querySelector(a.getAttribute("href")); });
  function onScroll() {
    top.classList.toggle("is-solid", window.scrollY > 60);
    var cur = 0;
    sections.forEach(function (s, i) { if (s && s.getBoundingClientRect().top < window.innerHeight * 0.4) cur = i; });
    links.forEach(function (a, i) { a.classList.toggle("is-current", i === cur); });
  }
  window.addEventListener("scroll", onScroll, { passive: true });
  onScroll();

  /* ---------- rows: arrows scroll a screenful ---------- */
  document.querySelectorAll(".row-wrap").forEach(function (wrap) {
    var track = wrap.querySelector(".row-track");
    wrap.querySelectorAll(".row-arrow").forEach(function (b) {
      b.addEventListener("click", function () {
        var dir = b.classList.contains("prev") ? -1 : 1;
        track.scrollBy({ left: dir * track.clientWidth * 0.85, behavior: "smooth" });
      });
    });
  });

  /* ---------- the detail window ---------- */
  var modal = document.getElementById("modal");
  var box = modal.querySelector(".modal-box");
  var LISTS = { episode: DATA.episodes, doc: DATA.docs, cast: DATA.cast, about: DATA.about };
  var state = null, lastFocus = null;

  var render = {
    episode: function (e) {
      return '<div class="m-head"><img src="' + esc(e.still) + '" alt="">' +
        '<div class="m-headtext"><p class="eyebrow">Season 1 · Episode ' + e.number + '</p><h3>' + esc(e.title) + '</h3></div></div>' +
        '<div class="m-body"><div class="m-meta"><span>' + esc(e.runtime) + '</span><i></i><span>HDR · 4K</span></div>' +
        '<p class="m-text">' + esc(e.synopsis) + '</p>' +
        (e.outline || []).map(function (p) { return '<p class="m-text">' + esc(p) + '</p>'; }).join("") + CONF + '</div>';
    },
    doc: function (d) {
      return '<div class="m-head"><img src="' + esc(d.src) + '" alt="">' +
        '<div class="m-headtext"><h3>' + esc(d.title) + '</h3></div></div>' +
        '<div class="m-body"><p class="m-text">' + esc(d.body) + '</p>' +
        (d.pages ? '<div class="m-pages">' + d.pages.map(function (p, i) {
          return '<img src="' + esc(p) + '" alt="' + esc(d.title) + ' — page ' + (i + 1) + '" loading="lazy">';
        }).join("") + '</div>' : "") +
        (d.pdfUrl ? '<div class="m-actions"><a class="btn-play" href="' + esc(d.pdfUrl) + '" target="_blank" rel="noopener">' + ICON_PLAY + '<span>Open PDF</span></a>' +
          '<a class="btn-info" href="' + esc(d.pdfUrl) + '" download>' + ICON_DOWN + '<span>Download</span></a></div>' : "") + CONF + '</div>';
    },
    cast: function (m) {
      var alts = m.alternates || [];
      return '<div class="m-head cine"><img src="' + esc(m.image) + '" alt="' + esc(m.actor + " as " + m.character) + '" style="object-position:' + esc(m.imagePos || "50% 15%") + '">' +
        '<div class="m-headtext"><p class="eyebrow">' + esc(m.character) + '</p><h3>' + esc(m.actor) + '</h3></div></div>' +
        '<div class="m-body"><p class="m-text">' + esc(m.description) + '</p>' +
        (alts.length ? '<div class="alts"><h4>Alternates <span>Casting reference · ' + alts.length + ' option' + (alts.length === 1 ? "" : "s") + '</span></h4><div class="alt-grid">' +
          alts.map(function (a) {
            return '<div><div class="alt-photo"><img src="' + esc(a.image) + '" alt="' + esc(a.actor) + '" loading="lazy"><span>' + (a.region === "UK" ? "UK" : "US") + '</span></div>' +
              '<p class="alt-name">' + esc(a.actor) + '</p><p class="alt-note">' + esc(a.note) + '</p></div>';
          }).join("") + '</div></div>' : "") + CONF + '</div>';
    },
    about: function (c) {
      return '<div class="m-body" style="padding-top:clamp(2.4rem,6vw,3.6rem)"><p class="eyebrow">About Held Still · ' + esc(c.eyebrow) + '</p>' +
        '<h3 style="margin-top:.5rem">' + esc(c.title) + '</h3><div class="m-rule"></div>' +
        (c.blurb ? '<p class="m-text m-lede">' + esc(c.blurb) + '</p>' : "") +
        '<p class="m-text">' + esc(c.body) + '</p>' + CONF + '</div>';
    }
  };

  function show(kind, i) {
    var list = LISTS[kind];
    i = ((i % list.length) + list.length) % list.length;
    state = { kind: kind, i: i };
    box.className = "modal-box" + (kind === "cast" ? " wide" : "");
    box.innerHTML = '<button class="m-close" type="button" aria-label="Close"></button>' + render[kind](list[i]);
    modal.scrollTop = 0;
  }
  function open(kind, i) {
    lastFocus = document.activeElement;
    show(kind, i);
    modal.hidden = false;
    body.classList.add("locked");
    requestAnimationFrame(function () { modal.classList.add("is-open"); box.focus({ preventScroll: true }); });
  }
  function close() {
    if (!state) return;
    state = null;
    modal.classList.remove("is-open");
    body.classList.remove("locked");
    setTimeout(function () { if (!state) { modal.hidden = true; box.innerHTML = ""; } }, 350);
    if (lastFocus && lastFocus.focus) lastFocus.focus({ preventScroll: true });
  }
  function step(d) { if (state) show(state.kind, state.i + d); }

  document.addEventListener("click", function (e) {
    var t = e.target.closest("[data-open]");
    if (t) { open(t.getAttribute("data-open"), +t.getAttribute("data-i")); return; }
    if (!state) return;
    if (e.target.closest(".m-close") || e.target === modal) close();
    else if (e.target.closest(".modal-arrow.prev")) step(-1);
    else if (e.target.closest(".modal-arrow.next")) step(1);
  });
  document.addEventListener("keydown", function (e) {
    if (!state) return;
    if (e.key === "Escape") close();
    else if (e.key === "ArrowLeft") step(-1);
    else if (e.key === "ArrowRight") step(1);
  });
})();
