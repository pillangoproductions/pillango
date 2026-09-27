/* Pillango — the location database (/locations).
   Search, filter by category and setting, open a location's photos,
   keep a favourites list in this browser, share it or send it to us.
   Photos load from the production's Google Drive (shared "anyone with the link"). */
(function () {
  "use strict";
  var DATA = JSON.parse(document.getElementById("loc-data").textContent);
  var LOCS = DATA.locations, CATS = DATA.cats;
  var BY_ID = {};
  LOCS.forEach(function (l) { BY_ID[l.id] = l; });
  var KEY = "pillango-location-favourites";
  var CONTACT = document.getElementById("loc-tool").getAttribute("data-contact") || "/contact";
  var $ = function (id) { return document.getElementById(id); };
  var grid = $("loc-grid"), q = $("loc-q"), count = $("loc-count"), empty = $("loc-empty");
  var modal = $("loc-modal"), mbox = modal.querySelector(".loc-modal-box");
  document.body.appendChild(modal);   // out of #page, so it sits above the menu bar

  function img(id, w) { return "https://drive.google.com/thumbnail?id=" + encodeURIComponent(id) + "&sz=w" + w; }
  function esc(s) {
    return String(s == null ? "" : s).replace(/[&<>"]/g, function (c) {
      return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;" }[c];
    });
  }
  function fold(s) { return String(s).normalize("NFD").replace(/[̀-ͯ]/g, "").toLowerCase(); }
  LOCS.forEach(function (l) {
    l._text = fold([l.name, l.area, l.address, l.type, CATS[l.cat], l.keywords.join(" "), l.setting, l.notes].join(" "));
  });

  /* ---------- favourites (this browser only) ---------- */
  var favs = [];
  try { favs = JSON.parse(localStorage.getItem(KEY) || "[]").filter(function (id) { return BY_ID[id]; }); } catch (e) {}
  function saveFavs() { try { localStorage.setItem(KEY, JSON.stringify(favs)); } catch (e) {} }
  function isFav(id) { return favs.indexOf(id) >= 0; }
  function toggleFav(id) {
    if (isFav(id)) favs.splice(favs.indexOf(id), 1); else favs.push(id);
    saveFavs();
    document.querySelectorAll('[data-fav="' + id + '"]').forEach(paintHeart);
    paintFavCount();
    if (state.favsOnly) render();
  }
  function paintHeart(b) {
    var on = isFav(b.getAttribute("data-fav"));
    b.classList.toggle("is-on", on);
    b.setAttribute("aria-pressed", on ? "true" : "false");
    b.setAttribute("aria-label", on ? "Remove from favourites" : "Add to favourites");
  }
  function paintFavCount() {
    $("loc-fav-count").textContent = favs.length;
    $("fav-panel-n").textContent = favs.length;
  }

  /* ---------- filtering ---------- */
  var shared = (window.location.search.match(/[?&]fav=([^&]+)/) || [])[1];
  shared = shared ? decodeURIComponent(shared).split(",").filter(function (id) { return BY_ID[id]; }) : null;
  var state = { cat: "", set: "", favsOnly: false, shared: shared && shared.length ? shared : null };

  function matches(l) {
    if (state.shared && state.shared.indexOf(l.id) < 0) return false;
    if (state.favsOnly && !isFav(l.id)) return false;
    if (state.cat && l.cat !== state.cat) return false;
    if (state.set && l.setting.indexOf(state.set) < 0) return false;
    var terms = fold(q.value).split(/\s+/).filter(Boolean);
    return terms.every(function (t) { return l._text.indexOf(t) >= 0; });
  }
  function card(l) {
    var cover = l.photos[0];
    return '<li class="loc-card">' +
      '<button type="button" class="loc-open" data-open="' + l.id + '">' +
        '<span class="loc-thumb">' + (cover ? '<img src="' + img(cover, 640) + '" alt="" loading="lazy" referrerpolicy="no-referrer">' : '<span class="loc-nophoto">Photos on request</span>') +
        (l.photos.length > 1 ? '<span class="loc-n">' + l.photos.length + ' photos</span>' : "") + '</span>' +
        '<span class="loc-body"><span class="loc-cat">' + esc(CATS[l.cat]) + '</span>' +
        '<span class="loc-name">' + esc(l.name) + '</span>' +
        '<span class="loc-area">' + esc(l.area) + ' · ' + esc(l.setting) + '</span>' +
        '<span class="loc-tags">' + l.keywords.slice(0, 4).map(function (k) { return "<i>" + esc(k) + "</i>"; }).join("") + '</span></span>' +
      '</button>' +
      '<button type="button" class="heart" data-fav="' + l.id + '" aria-pressed="false"><span aria-hidden="true">♥</span></button>' +
    '</li>';
  }
  function render() {
    var list = LOCS.filter(matches);
    grid.innerHTML = list.map(card).join("");
    grid.querySelectorAll("[data-fav]").forEach(paintHeart);
    empty.hidden = list.length > 0;
    count.textContent = list.length + (list.length === 1 ? " location" : " locations") +
      (state.shared ? " in this shared list" : state.favsOnly ? " in your favourites" : "");
  }

  q.addEventListener("input", render);
  document.querySelectorAll("[data-cat]").forEach(function (b) {
    b.addEventListener("click", function () {
      state.cat = b.getAttribute("data-cat");
      document.querySelectorAll("[data-cat]").forEach(function (x) { x.setAttribute("aria-pressed", x === b ? "true" : "false"); });
      render();
    });
  });
  document.querySelectorAll("[data-set]").forEach(function (b) {
    b.addEventListener("click", function () {
      state.set = b.getAttribute("data-set");
      document.querySelectorAll("[data-set]").forEach(function (x) { x.setAttribute("aria-pressed", x === b ? "true" : "false"); });
      render();
    });
  });
  $("loc-favs").addEventListener("click", function () {
    state.favsOnly = !state.favsOnly;
    this.setAttribute("aria-pressed", state.favsOnly ? "true" : "false");
    $("fav-panel").hidden = !state.favsOnly;
    render();
  });

  /* ---------- sending and sharing the list ---------- */
  function listText(ids) {
    return ids.map(function (id) { var l = BY_ID[id]; return "- " + l.name + " (" + l.area + ")"; }).join("\n");
  }
  function say(t) { $("fav-msg").textContent = t; }
  $("fav-send").addEventListener("click", function () {
    if (!favs.length) return say("Add a few locations first — tap the heart on any card.");
    var msg = "Hello — we’re interested in these locations:\n" + listText(favs) + "\n\nProject, dates and anything else we should know:\n";
    window.location.href = CONTACT + "?topic=consulting&msg=" + encodeURIComponent(msg);
  });
  $("fav-share").addEventListener("click", function () {
    if (!favs.length) return say("Add a few locations first — tap the heart on any card.");
    var url = window.location.origin + window.location.pathname + "?fav=" + favs.join(",");
    var done = function () { say("Link copied — anyone who opens it sees this list."); };
    if (navigator.clipboard && navigator.clipboard.writeText) navigator.clipboard.writeText(url).then(done, function () { window.prompt("Copy this link:", url); });
    else window.prompt("Copy this link:", url);
  });
  $("fav-clear").addEventListener("click", function () {
    if (!favs.length || !window.confirm("Clear your favourites?")) return;
    favs = []; saveFavs(); paintFavCount(); render(); say("");
  });
  if (state.shared) {
    $("shared-banner").hidden = false;
    $("shared-n").textContent = state.shared.length;
    $("shared-save").addEventListener("click", function () {
      state.shared.forEach(function (id) { if (!isFav(id)) favs.push(id); });
      saveFavs(); paintFavCount();
      this.textContent = "Added to your favourites";
      this.disabled = true;
      render();
    });
    $("shared-all").addEventListener("click", function () {
      state.shared = null;
      $("shared-banner").hidden = true;
      history.replaceState(null, "", window.location.pathname);
      render();
    });
  }

  /* ---------- a location's page: photos and details ---------- */
  var cur = null, photo = 0, lastFocus = null;
  function paintPhoto() {
    var l = BY_ID[cur], big = mbox.querySelector(".lm-photo img");
    if (!big) return;
    big.src = img(l.photos[photo], 1600);
    mbox.querySelector(".lm-count").textContent = (photo + 1) + " / " + l.photos.length;
    mbox.querySelectorAll(".lm-thumbs button").forEach(function (b, i) { b.classList.toggle("is-on", i === photo); });
  }
  function openLoc(id) {
    var l = BY_ID[id];
    cur = id; photo = 0; lastFocus = document.activeElement;
    var ask = "Hello — we’re interested in this location:\n- " + l.name + " (" + l.area + ")\n\nProject, dates and anything else we should know:\n";
    mbox.innerHTML =
      '<button type="button" class="m-x" aria-label="Close"></button>' +
      (l.photos.length ?
        '<div class="lm-photo"><img alt="' + esc(l.name) + '" referrerpolicy="no-referrer">' +
          (l.photos.length > 1 ? '<button type="button" class="lm-prev" aria-label="Previous photo">‹</button><button type="button" class="lm-next" aria-label="Next photo">›</button>' : "") +
          '<span class="lm-count"></span></div>' +
        (l.photos.length > 1 ? '<div class="lm-thumbs">' + l.photos.map(function (p, i) {
          return '<button type="button" data-photo="' + i + '" aria-label="Photo ' + (i + 1) + '"><img src="' + img(p, 240) + '" alt="" loading="lazy" referrerpolicy="no-referrer"></button>';
        }).join("") + '</div>' : "")
      : '<div class="lm-photo lm-none"><span>Photos on request</span></div>') +
      '<div class="lm-body">' +
        '<p class="loc-cat">' + esc(CATS[l.cat]) + '</p>' +
        '<h2 id="loc-m-title">' + esc(l.name) + '</h2>' +
        '<dl class="lm-facts"><dt>Area</dt><dd>' + esc(l.area) + '</dd>' +
          (l.address ? '<dt>Address</dt><dd>' + esc(l.address) + '</dd>' : "") +
          '<dt>Type</dt><dd>' + esc(l.type) + '</dd><dt>Setting</dt><dd>' + esc(l.setting) + '</dd>' +
          '<dt>Keywords</dt><dd>' + esc(l.keywords.join(", ")) + '</dd>' +
          (l.notes ? '<dt>Notes</dt><dd>' + esc(l.notes) + '</dd>' : "") + '</dl>' +
        '<div class="btn-row"><button type="button" class="btn heart-btn" data-fav="' + l.id + '">♥ <span>Favourite</span></button>' +
          '<a class="btn" href="' + CONTACT + '?topic=consulting&amp;msg=' + encodeURIComponent(ask) + '">Ask about this location</a></div>' +
      '</div>';
    mbox.querySelectorAll("[data-fav]").forEach(paintHeart);
    paintPhoto();
    modal.hidden = false;
    document.body.classList.add("loc-locked");
    requestAnimationFrame(function () { modal.classList.add("is-open"); mbox.focus({ preventScroll: true }); });
  }
  function closeLoc() {
    if (!cur) return;
    cur = null;
    modal.classList.remove("is-open");
    document.body.classList.remove("loc-locked");
    setTimeout(function () { if (!cur) { modal.hidden = true; mbox.innerHTML = ""; } }, 300);
    if (lastFocus && lastFocus.focus) lastFocus.focus({ preventScroll: true });
  }
  function step(d) {
    if (!cur) return;
    var n = BY_ID[cur].photos.length;
    if (n < 2) return;
    photo = (photo + d + n) % n;
    paintPhoto();
  }

  document.addEventListener("click", function (e) {
    var t;
    if ((t = e.target.closest("[data-fav]"))) { toggleFav(t.getAttribute("data-fav")); return; }
    if ((t = e.target.closest("[data-open]"))) { openLoc(t.getAttribute("data-open")); return; }
    if (!cur) return;
    if ((t = e.target.closest("[data-photo]"))) { photo = +t.getAttribute("data-photo"); paintPhoto(); return; }
    if (e.target.closest(".lm-prev")) step(-1);
    else if (e.target.closest(".lm-next")) step(1);
    else if (e.target.closest(".m-x") || e.target === modal) closeLoc();
  });
  document.addEventListener("keydown", function (e) {
    if (!cur) return;
    if (e.key === "Escape") closeLoc();
    else if (e.key === "ArrowLeft") step(-1);
    else if (e.key === "ArrowRight") step(1);
  });

  paintFavCount();
  render();
})();
