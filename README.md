# pillangoprod.com — Pillango Productions Kft.

Website for Pillango Productions: film production, post-production and
production consulting in Hungary / Europe.

**Static HTML, CSS and vanilla JS**, plus one small PHP file for the Held Still
password. No build step, no database, no Node, no third-party requests (fonts
are self-hosted). Upload the folder to DreamHost and it works.

---

## One menu, one flight

The menu (top right, on every page) is the only navigation:

**Home · About · Services · Partners · Post-production · Blog · Projects · Book**
and, smaller, **Privacy · Impresszum · GDPR**.

The flight follows the same order. Scrolling (wheel, trackpad, swipe, arrow
keys) moves exactly one chapter per gesture, with a one-second glide. Moving
from one page to the next is the very same glide: from a page's last chapter,
one more gesture flies on to the next page's intro. Pushing back at the top of
a page returns to the previous one, landing on its last chapter.
Book loops back to Home. Trackpad swipes are read the way a trackpad sends them: the faint momentum
tail never counts, and a new swipe is recognised even while the last one's
momentum is still dying away. Keeping a wheel rolling carries on chapter by
chapter. Timings are at the top of section 7 in
`js/main.js` (`SNAP_MS`, `ARRIVAL_QUIET`…).

Performance: everything that moves during a glide is a transform or an
opacity; the bokeh lights are animated by the compositor, not redrawn;
the flight only redraws when the camera moves; focus blurs apply only to the content block, never the
whole screen; and nothing blends or blurs live over the moving background.
Keep it that way when adding effects.

Pages change without reloading: the next and previous pages are fetched
ahead of time, and flying on glides the new page's first chapter in exactly
like any other chapter, so the light, menu and cursor never blink. The
address bar, title, canonical link, rail and menu all update, and the
browser's Back/Forward buttons work. Each page is still a complete page at
its own address (search engines, shared links); legal pages and
reduced-motion visitors load normally. The page settings (`data-next`,
`data-prev`, labels) are on `<main id="stage">`.

On the right (wider screens), the rail names every page: the current one in
amber with its chapters as dots beneath it, the next one a little brighter.
Clicking a name flies there. Legal pages and project/blog detail pages are
ordinary scrolling pages.

| URL | File | Kind |
|---|---|---|
| `/` | `index.html` | flight — logo, welcome |
| `/about` | `about/index.html` | flight |
| `/services` | `services/index.html` | flight — four service boxes |
| `/partners` | `partners/index.html` | flight — partner logo boxes |
| `/post-production` | `post-production/index.html` | flight — Pécs City Studios |
| `/blog` | `blog/index.html` | flight — post boxes |
| `/projects` | `projects/index.html` | flight — project boxes; Held Still opens the password box |
| `/book` | `book/index.html` | flight |
| `/privacy`, `/impresszum`, `/gdpr` | `*/index.html` | document pages |
| `/projects/held-still/` | `projects/held-still/site/` | **password-protected**, reached only through the box on `/projects` |
| — | `404.html` | not found |

The order lives in two places: `<body data-prev="…" data-next="…">` on each
flight page, and the menu list in each page. Change both together.

Redirects (`.htaccess`): `www` → apex, `http` → `https`, `/about/` and
`/about/index.html` → `/about`, `/the-book` → `/book`, `/held-still` →
`/projects/held-still/`, `/project-assessment` → `/services#ch-consulting`,
`/index.php` → `/`. The old template's `/admin`, `/api`, `/includes`, `/sql`,
`/uploads` answer **410 Gone**.

## Deploying to DreamHost

1. Upload the repository contents (everything except `.git/`) to the domain's
   web directory, e.g. `~/pillangoprod.com/`, so `index.html` and `.htaccess`
   sit at the top. PHP must be on for the domain (DreamHost default).
2. Turn on the free Let's Encrypt certificate for `pillangoprod.com` in the
   panel (the `.htaccess` forces HTTPS and the apex domain).
3. Set the Held Still password (next section).
4. Check `https://pillangoprod.com/about`, `/about/` (→ 301), `/the-book` (→ 301).

## Held Still — the password box

On `/projects`, the Held Still box opens a small password box. The password is
checked on the server by `projects/held-still/gate.php`; the right one dissolves
the page into light and opens the Held Still site. Every file in
`projects/held-still/` goes through the gate (see its `.htaccess`), so the
pages, images and videos of the Held Still site are all protected. A signed-in
visitor stays in until the browser closes; the site's "Sign out" link ends it.
Wrong passwords are slowed down and limited to 8 per 10 minutes per address.

**Set the password** (once, over SSH on DreamHost):

```sh
cd ~/pillangoprod.com/projects/held-still
cp config.sample.php config.php
php -r 'echo password_hash("the password", PASSWORD_DEFAULT), "\n";'
```

Paste the printed hash between the quotes of `'password_hash' => ''` in
`config.php` (keep the single quotes — the hash contains `$` signs). To change
the password, repeat with a new one. `config.php` is git-ignored, so the hash
never lands in the repository. Until it exists, the box answers "Access isn't
set up yet" and nothing is served.

**Drop in the Held Still site:** replace everything in
`projects/held-still/site/` with the site (`index.html` plus its assets). Use
relative paths (`assets/still.jpg`) or absolute ones starting
`/projects/held-still/`. To send people to a Held Still site hosted somewhere
else instead, set `'redirect'` in `config.php` to its URL (that site is then
not protected by this gate).

## How the pages are built

### Flight pages
Chapters are `<section class="layer" data-chapter="…">` inside `<main id="stage">`:

```html
<section class="layer" id="ch-financing" data-chapter="financing"
         data-gap="1.3" data-sky="#0A0A0B" data-veil="0.7">
```

- `data-gap` — distance from the previous chapter
- `data-sky` — the colour laid over the background
- `data-veil` — how much of that colour covers the bokeh (0 = clear, 1 = hidden);
  intros use ~0.45, content ~0.7

A flight chapter cannot scroll, so keep each to a screenful. Deep links like
`/services#ch-financing` land on that chapter.

### Boxes
Everything listed — services, partners, projects, posts — is a `.box` in a
`.box-grid` (`cols-2/3/4`). Boxes lift on hover with orange light from
underneath. Make one a link with `<a class="box" href="…">`.

- **Partner logos:** put the file in `assets/img/partners/` and use
  `<span class="logo-mark"><img src="/assets/img/partners/name.svg" alt="Name"></span>`
  in its box on `/partners`. Empty boxes are marked "Partner logo".
- **Projects with a full page:** copy `_templates/project.html` to
  `projects/<slug>/index.html` (it has the "Back to Projects" button), then
  link a box to it on `/projects` and add the URL to `sitemap.xml`.
- **Blog posts:** copy `_templates/blog-post.html` to `blog/<slug>/index.html`
  (with "Back to Blog"), add a box on `/blog` (example in the page's comment)
  and the URL to `sitemap.xml`.

`_templates/` is blocked in `robots.txt` and returns 404 on the server.

## The background, the cursor, the ripples

- **Bokeh** (every page): each light is its own element drifting on a CSS
  animation run by the graphics chip (`js/main.js` section 3, `.bk` in the CSS), after the hero of the current site — rose and red
  to the left, magenta and violet across the top, fading to blue-black, with a
  few warm sparks. Three depths drift at different speeds and slide apart as
  you fly. Colours, sizes and counts are at the top of that section
  (`WARM`, `COOL`, `LAYERS`). Reduced-motion visitors get still lights.
- **Cursor:** a tiny ⅃L (`assets/img/cursor-normal.svg`, `cursor-hover.svg`,
  PNG fallbacks) with a pool of orange light beneath it that follows the
  pointer and swells over anything clickable. Mouse and trackpad only.
- **Ripples:** a click sends rings out from the point and the page itself —
  content and background — ripples like water (an SVG displacement filter).
  Safari gets the rings only; reduced-motion visitors get neither.

## SEO

- Every page has its own `<title>`, meta description and canonical
  `https://pillangoprod.com/<route>`, plus Open Graph / Twitter tags and the
  share image `assets/img/og-image.jpg` (1200×630, the logo over the bokeh).
- `robots.txt` allows everything but `/_templates/`; `sitemap.xml` lists the
  eleven menu routes. The Held Still pages send `noindex`.
- The home page carries an `Organization` JSON-LD block.

## Assets

| Path | What |
|---|---|
| `assets/img/favicon.svg`, `favicon-32.png`, `apple-touch-icon.png` | the serif ⅃L |
| `assets/img/cursor-*.svg/png` | the cursor |
| `assets/img/pillango-logo.png` | 1200×400 logo on white (JSON-LD, press) |
| `assets/img/pillango-logo-original.png` | the supplied logo file |
| `assets/img/og-image.jpg` | share image |
| `assets/fonts/*.woff2`, `css/fonts.css` | Cormorant Garamond + Outfit (SIL OFL); Tinos subset for the wordmark (Apache 2.0) |

The wordmark (`PI⅃LANGO / PRODUCTIONS`) is live text rebuilt from the logo. If
you have the logo as SVG it can replace it (`class="wm"` in the nav and hero).

## Placeholders still to fill

- Contact e-mail `hello@pillangoprod.com` — confirm or replace (search the repo).
- Impresszum / Privacy: registered address, company reg. no., registry court,
  tax number, EU VAT, managing director, e-mail provider, log retention
  (dashed orange, class `tbc`). Have the legal texts reviewed before launch.
- `/post-production`: the studio photo (see the comment in the page), the end
  of the Pécs City Studios paragraph and the rest of the equipment list.
- Partner logos, project key art, the Held Still site, The Book (cover,
  synopsis, where to buy).

## Colours

| Token | Value | Use |
|---|---|---|
| `--night` | `#0A0A0B` | page ground |
| `--glass` | `rgba(14,12,16,.55)` | box fill over the bokeh |
| `--hair` | `rgba(237,234,227,.09)` | hairline borders |
| `--white` | `#EFECE6` | headings |
| `--text` | `#ABA7A0` | body copy |
| `--amber` | `#EE8A2B` | rules, icons, links |
| `--glow` | `255,118,36` | the light under boxes and the cursor |
| `--orange` | `#FF4A00` | the logo's LL only |
