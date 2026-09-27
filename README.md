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
| `/post-production` | `post-production/index.html` | flight |
| `/partners` | `partners/index.html` | flight |
| `/blog` | `blog/index.html` | document page — journal index |
| `/held-still` | `held-still/index.html` | **stub** — replace with the Held Still page |
| `/the-book` | `the-book/index.html` | stub |
| `/privacy`, `/impresszum`, `/gdpr` | `*/index.html` | legal document pages |
| — | `404.html` | error page |

Redirects (`.htaccess`): `www` → apex, `http` → `https`, `/about/` and
`/about/index.html` → `/about`, `/book` → `/the-book`, `/project-assessment` →
`/services#ch-consulting`, `/index.php` → `/`. The old template's
`/admin`, `/api`, `/includes`, `/sql`, `/uploads` answer **410 Gone**.

## Deploying to DreamHost

1. Upload the repository contents (everything except `.git/`) to the domain's
   web directory, e.g. `~/pillangoprod.com/`, so `index.html` and `.htaccess`
   sit at the top.
2. In the DreamHost panel, turn on the free Let's Encrypt certificate for
   `pillangoprod.com` (the `.htaccess` forces HTTPS and the apex domain).
3. Check `https://pillangoprod.com/about`, `/about/` (→ 301) and `/book` (→ 301).

Local preview: any static server from the repo root works
(`npx serve .` or `python3 -m http.server`); pages open at `/about/` there,
because the clean-URL rewrite is an Apache rule.

## How the pages work

### Flight pages (the chapter scroll)
Each chapter is a `<section class="layer" data-chapter="…">` inside
`<main id="stage">`. `js/main.js` reads the spine straight from the markup:

```html
<section class="layer ink-light" id="ch-sound" data-chapter="sound"
         data-gap="1.3" data-sky="#24140F" data-rail="Sound">
```

- `data-gap` — distance from the previous chapter (bigger = longer flight)
- `data-sky` — background grade while the chapter is on screen
- `data-rail` — label on the right-hand progress rail (optional)
- `ink-dark` instead of `ink-light` for light backgrounds (`#ECE5D6`)

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
Replace `held-still/index.html` (or just its `<main>`) with the existing page
and put its assets in `held-still/assets/`. Keep the `<head>` block — title,
description, canonical `https://pillangoprod.com/held-still` and og tags —
unless the new page brings its own.

## SEO
- Every page has its own `<title>`, meta description and
  `<link rel="canonical">` on `https://pillangoprod.com/<route>` (no trailing
  slash), plus Open Graph / Twitter tags.
- Share image: `assets/img/og-image.jpg` (1200×630), hosted here. Swap in a
  real still at the same size and name whenever you like.
- `robots.txt` allows everything but `/_templates/` and points to
  `sitemap.xml`, which lists all the routes above. Update `<lastmod>` when a
  page changes.
- The home page carries an `Organization` JSON-LD block.

## Assets
| Path | What |
|---|---|
| `assets/img/favicon.svg`, `favicon-32.png`, `apple-touch-icon.png` | butterfly mark icons |
| `assets/img/logo-mark.png` | 512×512 mark (JSON-LD logo) |
| `assets/img/og-image.jpg` | share image |
| `assets/fonts/*.woff2`, `css/fonts.css` | Instrument Serif + Inter Tight (SIL OFL), Latin + Latin Extended |

**Hero footage:** put a muted loop at `assets/video/hero.mp4` (with a
poster) and follow the comment at the top of the hero section in
`index.html`. It pauses automatically once the hero has scrolled away, and is
hidden for reduced-motion visitors.

**Image placeholders** are `<div class="frame">` boxes. Put an `<img>` inside
(it fills the frame) and delete the `.frame-label`.

## Placeholders still to fill
- Contact e-mail `hello@pillangoprod.com` — confirm or replace (search the repo).
- Impresszum / Privacy: registered address, company reg. no., registry court,
  tax number, EU VAT number, managing director, e-mail provider, log retention
  (dashed amber boxes, class `tbc`). Have the legal texts reviewed before launch.
- Team names and bios (`/about`), partner logos (home + `/partners`),
  stills (home, `/post-production`), Held Still and The Book content.
- Social links (none yet).

## Colours
| Token | Hex | Use |
|---|---|---|
| `--night` | `#0A0B0D` | base black |
| `--steel` | `#17202A` | blue-hour chapters |
| `--teal` | `#0E2629` | deep grade |
| `--ember` | `#24140F` | warm shadow |
| `--paper` | `#ECE5D6` | the one light chapter |
| `--amber` | `#D9A441` | accent / the "practical" |
| `--rust` | `#A5461F` | accent on paper |
