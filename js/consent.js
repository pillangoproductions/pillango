/* Cookie consent, Google Analytics and YouTube previews.
   Nothing from Google loads until the visitor presses "Accept all".
   To switch analytics on, put the GA4 Measurement ID below (e.g. "G-ABC123XYZ"). */
(function () {
  "use strict";
  var GA_ID = "G-SB41MDCY2D";

  var KEY = "pillango-consent";
  function read() {
    try { return localStorage.getItem(KEY); } catch (e) { return null; }
  }
  function write(v) {
    try { localStorage.setItem(KEY, v); } catch (e) { /* private mode: ask again next visit */ }
  }
  var choice = read();                       // "all" | "necessary" | null
  window.pillangoConsent = {
    all: function () { return choice === "all"; },
    open: showBanner
  };

  /* ---------- Google Analytics ---------- */
  var gaLoaded = false;
  function loadGA() {
    if (gaLoaded || !GA_ID) return;
    gaLoaded = true;
    window["ga-disable-" + GA_ID] = false;
    window.dataLayer = window.dataLayer || [];
    window.gtag = function () { window.dataLayer.push(arguments); };
    window.gtag("js", new Date());
    window.gtag("config", GA_ID);
    var s = document.createElement("script");
    s.async = true;
    s.src = "https://www.googletagmanager.com/gtag/js?id=" + encodeURIComponent(GA_ID);
    document.head.appendChild(s);
  }
  function stopGA() {
    if (!GA_ID) return;
    window["ga-disable-" + GA_ID] = true;
    var host = location.hostname, parts = host.split(".");
    var domains = ["", host, "." + host, "." + parts.slice(-2).join(".")];
    document.cookie.split(";").forEach(function (c) {
      var name = c.split("=")[0].trim();
      if (name === "_ga" || name.indexOf("_ga_") === 0 || name === "_gid") {
        domains.forEach(function (d) {
          document.cookie = name + "=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/" + (d ? "; domain=" + d : "");
        });
      }
    });
  }

  function apply() {
    if (choice === "all") loadGA(); else stopGA();
    document.dispatchEvent(new CustomEvent("pillango:consent", { detail: { all: choice === "all" } }));
  }

  /* ---------- the banner ---------- */
  var banner = null;
  function showBanner() {
    if (banner) { banner.hidden = false; return; }
    banner = document.createElement("div");
    banner.className = "cookie-bar";
    banner.setAttribute("role", "dialog");
    banner.setAttribute("aria-label", "Cookie settings");
    banner.innerHTML =
      '<p>We use cookies for anonymous visitor statistics (Google Analytics) and to show YouTube previews — only if you agree. ' +
      'Necessary storage is always on. <a href="/cookies">Cookie policy</a></p>' +
      '<div class="cookie-bar-btns">' +
      '<button type="button" class="btn" data-consent="all">Accept all</button>' +
      '<button type="button" class="btn" data-consent="necessary">Necessary only</button>' +
      '</div>';
    banner.addEventListener("click", function (e) {
      var b = e.target.closest("[data-consent]");
      if (!b) return;
      choice = b.getAttribute("data-consent");
      write(choice);
      banner.hidden = true;
      apply();
    });
    document.body.appendChild(banner);
  }

  /* any element with data-consent-open (e.g. on the cookie policy page) reopens the banner */
  document.addEventListener("click", function (e) {
    if (e.target.closest && e.target.closest("[data-consent-open]")) { e.preventDefault(); showBanner(); }
  });

  if (choice !== "all" && choice !== "necessary") {
    choice = null;
    if (document.body) showBanner(); else document.addEventListener("DOMContentLoaded", showBanner);
  }
  apply();
})();
