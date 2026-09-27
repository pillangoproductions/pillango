# pillangoprod.com — Pillango Productions Kft.

Marketing site for Pillango Productions: film production, post-production and
production consulting in Hungary / Europe.

**Plain static HTML, CSS and vanilla JS.** No build step, no PHP, no database,
no Node, no third-party requests (fonts are self-hosted). Upload the folder to
DreamHost and it works.

---

## Routes

| URL | File | Type |
|---|---|---|
| `/` | `index.html` | flight (chapter scroll) |
| `/about` | `about/index.html` | flight |
| `/services` | `services/index.html` | flight |
| `/post-production` | `post-production/index.html` | document page with a photo header |
| `/partners` | `partners/index.html` | flight |
| `/projects` | `projects/index.html` | document page — project cards |
| `/projects/held-still` | `projects/held-still/index.html` | **password-protected** stub — linked only from `/projects`, not in the sitemap |
| `/blog` | `blog/index.html` | document page — journal index |
| `/the-book` | `the-book/index.html` | stub |
| `/privacy`, `/impresszum`, `/gdpr` | `*/index.html` | legal document pages |
| — | `404.html`, `401.html` | not found / login required |

Redirects (`.htaccess`): `www` → apex, `http` → `https`, `/about/` and
`/about/index.html` → `/about`, `/book` → `/the-book`, `/held-still` →
`/projects/held-still`, `/project-assessment` →
`/services#ch-consulting`, `/index.php` → `/`. The old template's
`/admin`, `/api`, `/includes`, `/sql`, `/uploads` answer **410 Gone**.

## Deploying to DreamHost

1. Upload the repository contents (everything except `.git/`) to the domain's
   web directory, e.g. `~/pillangoprod.com/`, so `index.html` and `.htaccess`
   sit at the top.
2. In the DreamHost panel, turn on the free Let's Encrypt certificate for
   `pillangoprod.com` (the `.htaccess` forces HTTPS and the apex domain).
3. Set up the Held Still password (next section) — until then that one
   folder answers 500, i.e. stays locked.
4. Check `https://pillangoprod.com/about`, `/about/` (→ 301) and `/book` (→ 301).

## Held Still password

`/projects/held-still` (the page **and every file in that folder**) is behind
HTTP Basic authentication, enforced by Apache — nothing is visible without the
login, and the folder sends `noindex` and `no-store` headers. It is linked only
from the `/projects` page and is left out of `sitemap.xml` and `robots.txt`.

One-time setup on DreamHost (SSH):

```sh
mkdir -p ~/.htpasswds
htpasswd -c ~/.htpasswds/held-still heldstill     # asks for the password
```

Then edit `projects/held-still/.htaccess` and replace `DREAMHOST_USER` in the
`AuthUserFile` line with your DreamHost shell user name (the file must be the
absolute path, e.g. `/home/pillango/.htpasswds/held-still`). To add another
login: `htpasswd ~/.htpasswds/held-still otheruser` (no `-c`, which would
overwrite the file). To change a password, run the same command for that user.

No SSH? Generate a line locally with `openssl passwd -apr1` (format
`user:hash`), save it as `~/.htpasswds/held-still` via SFTP.

A wrong password or cancelled login shows `/401.html`.

Local preview: any static server from the repo root works
(`npx serve .` or `python3 -m http.server`); pages open at `/about/` there,
because the clean-URL rewrite is an Apache rule.

## How the pages work

### Flight pages (the chapter scroll)
Each chapter is a `<section class="layer" data-chapter="…">` inside
`<main id="stage">`. `js/main.js` reads the spine straight from the markup:

```html
<section class="layer" id="ch-financing" data-chapter="financing"
         data-gap="1.3" data-sky="#0B0B0B" data-rail="Financing">
```

- `data-gap` — distance from the previous chapter (bigger = longer flight)
- `data-sky` — background colour while the chapter is on screen (kept
  near-black, like the current site; slight warm/cool shifts between chapters)
- `data-veil` — home page only: how much the video is dimmed (see below)
- `data-rail` — label on the right-hand progress rail (optional)

Add, remove or reorder sections; nothing else needs changing. One push of the
wheel / swipe / arrow key moves exactly one chapter. With
`prefers-reduced-motion` or without JS the page falls back to a normal stacked
scroll. **A flight chapter cannot scroll**, so keep each one to a screenful;
long content belongs on a document page.

Deep links: `/#ch-contact` lands on that chapter. Links with
`data-goto="contact"` fly there without reloading.

### Document pages
`<body class="page">` + `<main class="doc-main">` → ordinary scrolling page
with the same nav, a `.doc-head` title block and a `.prose` reading column.

### Shared parts
Nav, menu overlay and footer are repeated in each HTML file. When you add a
page or change the menu, update all files (search for `class="nav-links"` and
`class="overlay-links"`), plus `sitemap.xml`.

## Adding a journal post
1. Copy `_templates/blog-post.html` to `blog/<slug>/index.html`.
2. Replace every `POST-SLUG`, `POST TITLE` and summary placeholder — including
   `<title>`, `description`, `canonical`, `og:url` and the `<h1>`.
3. Add a `<li>` to the list in `blog/index.html` (newest first) and remove the
   "In the edit" stub box once there is at least one post:
   ```html
   <li><a href="/blog/<slug>"><span class="post-date">2026-10-01</span>
     <span><span class="post-title">Title</span><span class="post-excerpt">One line.</span></span>
     <span class="post-go">Read →</span></a></li>
   ```
4. Add the URL to `sitemap.xml`.

`_templates/` is blocked in `robots.txt` and returns 404 on the server.

## Dropping in Held Still
Replace `projects/held-still/index.html` (or just its `<main>`) with the
existing page and put its assets in `projects/held-still/assets/` — they are
behind the same password. Keep `<meta name="robots" content="noindex, nofollow">`,
and don't delete `projects/held-still/.htaccess`.

## SEO
- Every page has its own `<title>`, meta description and
  `<link rel="canonical">` on `https://pillangoprod.com/<route>` (no trailing
  slash), plus Open Graph / Twitter tags.
- Share image: `assets/img/og-image.jpg` (1200×630), hosted here. Swap in a
  real still at the same size and name whenever you like.
- `robots.txt` allows everything but `/_templates/` and points to
  `sitemap.xml`, which lists all the public routes above (not the private Held Still page). Update `<lastmod>` when a
  page changes.
- The home page carries an `Organization` JSON-LD block.

## Background video (home page)
The home page plays a muted, looping film behind every chapter. Each chapter's
`data-veil` (0–1) sets how strongly the dark grade covers the video: the
opening is almost clear (`0.12`) so the footage carries the logo, text
chapters are dimmed (`~0.9`).

The current files are **placeholders**: a generated bokeh loop in the colours
of the current site's hero. Replace:

| File | Spec |
|---|---|
| `assets/video/hero.webm` | VP9, 1920×1080 (or 1280×720), no audio, 10–30 s seamless loop |
| `assets/video/hero.mp4` | H.264 fallback (Safari), same cut, `-movflags +faststart` |
| `assets/img/hero-poster.jpg` | a still from the loop — shown while loading and for reduced-motion visitors |

Aim for under ~8 MB per file. Example encodes with ffmpeg:

```sh
ffmpeg -i master.mov -an -vf scale=1920:-2 -c:v libvpx-vp9 -b:v 0 -crf 36 -row-mt 1 assets/video/hero.webm
ffmpeg -i master.mov -an -vf scale=1920:-2,format=yuv420p -c:v libx264 -preset slow -crf 24 -movflags +faststart assets/video/hero.mp4
ffmpeg -ss 2 -i master.mov -frames:v 1 -q:v 3 -vf scale=1920:-2 assets/img/hero-poster.jpg
```

## Cursor and ripples
On mouse/trackpad devices the cursor is a tiny ⅃L
(`assets/img/cursor-normal.svg`, PNG fallback). Over anything clickable it
switches to `cursor-hover.svg`, which adds a soft amber glow. Every click or
tap sends three rings out from the point (`.ripple` in `css/style.css`,
created in `js/main.js`). Both are native/CSS, so the cursor never lags;
ripples are skipped for visitors with reduced motion turned on.

## Assets
| Path | What |
|---|---|
| `assets/img/favicon.svg`, `favicon-32.png`, `apple-touch-icon.png` | the orange ⅃L pair |
| `assets/img/pillango-logo.png` | 1200×400 logo on white (JSON-LD logo, press) |
| `assets/img/pillango-logo-original.png` | the supplied logo file (200×46) |
| `assets/img/og-image.jpg` | share image |
| `assets/img/cursor-normal.*`, `cursor-hover.*` | the ⅃L cursor |
| `assets/fonts/*.woff2`, `css/fonts.css` | Cormorant Garamond (headings) + Outfit (body), SIL OFL, Latin + Latin Extended — the pairing of the current site; Tinos (Apache 2.0) subset for the wordmark |

**The wordmark** (`PI⅃LANGO / PRODUCTIONS`) is live text, rebuilt from the
logo: Tinos caps, the first L mirrored, both Ls in `#FF4A00`, a rule and a
spaced PRODUCTIONS in Outfit. If you have the logo as SVG, it can replace the
text version in the nav, hero and footer (`class="wm"`).

**Image placeholders** are `<div class="frame">` boxes. Put an `<img>` inside
(it fills the frame) and delete the `.frame-label`.

## Placeholders still to fill
- Contact e-mail `hello@pillangoprod.com` — confirm or replace (search the repo).
- Impresszum / Privacy: registered address, company reg. no., registry court,
  tax number, EU VAT number, managing director, e-mail provider, log retention
  (dashed orange boxes, class `tbc`). Have the legal texts reviewed before launch.
- The real background video + poster (see above).
- `/post-production`: the studio photo (`assets/img/post-studio.jpg`, see the
  comment in the page), the end of the Pécs City Studios paragraph and the
  rest of the equipment list (marked in dashed orange).
- Partner logos, project key art, Held Still and The Book content.
- Social links (none yet).

## Colours
Taken from the current site.

| Token | Hex | Use |
|---|---|---|
| `--night` | `#0B0B0B` | page ground |
| `--panel` | `#141414` | cards |
| `--edge` | `#262626` | card borders |
| `--white` | `#EDEAE3` | headings |
| `--text` | `#A09D93` | body copy |
| `--amber` | `#EE8A2B` | heading rules, icons, links, buttons |
| `--orange` | `#FF4A00` | the logo's LL only |
