<?php
/**
 * Salone Medici — publikus oldal.
 *
 * Egy este a szalonban: a látogató végigrepül az érkezéstől a téren,
 * a kínálaton, az élő vásznon és az évadon az ajánlatkérésig.
 *
 * Minden tartalom az adatbázisból jön — az /admin felületen
 * szerkeszthető, HTML-hez nyúlni nem kell.
 */

require_once __DIR__ . '/includes/site-render.php';
require_once __DIR__ . '/includes/enquiry.php';

// ---- Enquiry POST — handled before a single byte of output ----------------
$formError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'enquiry') {
    $result = enquiry_handle($_POST);
    if ($result['ok']) {
        redirect(BASE_URL . '/index.php?sent=1#viaggio-richiesta');
    }
    $formError = $result['error'] ?? 'Ismeretlen hiba.';
}
$sent = isset($_GET['sent']);

// ---- Content pulled once per request --------------------------------------
$venueName = setting('venue_name', 'Salone Medici');
$city      = setting('venue_city', '');
$addr1     = setting('address_line1', '');
$addr2     = setting('address_line2', '');
$phone     = setting('phone', '');
$email     = setting('email', '');

/* Logo: an uploaded file wins, then an optional assets/logo.png.
   With neither, the venue name is drawn as a text wordmark. */
$logoFile = setting('logo', '');
$logoSrc  = '';
if ($logoFile !== '' && is_file(UPLOAD_BRANDING_DIR . '/' . $logoFile)) {
    $logoSrc = upload_url('branding', $logoFile);
} elseif (is_file(BASE_PATH . '/assets/logo.png')) {
    $logoSrc = BASE_URL . '/assets/logo.png';
}

/* The opening screen takes a still photo, a looping video, or neither —
   without one it simply renders on the night-dark background. */
$heroImageFile = setting('hero_image', '');
$heroImageSrc  = $heroImageFile !== '' && is_file(UPLOAD_BRANDING_DIR . '/' . $heroImageFile)
    ? upload_url('branding', $heroImageFile) : '';
$heroVideoFile = setting('hero_video', '');
$heroVideoSrc  = '';
if ($heroVideoFile !== '' && is_file(UPLOAD_BRANDING_DIR . '/' . $heroVideoFile)) {
    $heroVideoSrc = upload_url('branding', $heroVideoFile);
} elseif (is_file(BASE_PATH . '/assets/hero-video.mp4')) {
    $heroVideoSrc = BASE_URL . '/assets/hero-video.mp4';
}

$packages   = active_packages();
$nextEvents = upcoming_events(3);
$series     = active_series();

/* The flight's spine. Each chapter names the gap that precedes it and
   the colour the room takes there, so adding a chapter lengthens the
   journey instead of crowding what is already there. */
$journey = build_journey([
    ['id' => 'hero',      'gap' => 0.0,  'sky' => '#14110F'],
    ['id' => 'storia',    'gap' => 1.3,  'sky' => '#1B2A22'],
    ['id' => 'spazio',    'gap' => 1.3,  'sky' => '#2C4438', 'rail' => 'A tér'],
    ['id' => 'offerta',   'gap' => 1.3,  'sky' => '#EDDCBB', 'rail' => 'A kínálat'],
    ['id' => 'schermo',   'gap' => 1.2,  'sky' => '#3A2A4A', 'rail' => 'A vászon'],
    ['id' => 'eventi',    'gap' => 1.25, 'sky' => '#1B2A22', 'rail' => 'Az évad'],
    ['id' => 'richiesta', 'gap' => 1.25, 'sky' => '#14110F', 'rail' => 'Ajánlat'],
    ['id' => 'contatto',  'gap' => 1.1,  'sky' => '#14110F', 'rail' => 'Kapcsolat'],
]);

$pageTitle = $venueName . ($city !== '' ? ' · ' . $city : '');
$metaDesc  = trim(setting('hero_tagline', '') . ' Rendezvényhelyszín, exkluzív bérlés és jegyes esték'
    . ($city !== '' ? ' ' . $city . 'en' : '') . '.');
?>
<!DOCTYPE html>
<html lang="hu">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title><?= e($pageTitle) ?></title>
<meta name="description" content="<?= e($metaDesc) ?>">
<meta property="og:type" content="website">
<meta property="og:title" content="<?= e($pageTitle) ?>">
<meta property="og:description" content="<?= e(setting('hero_tagline', '')) ?>">
<?php if ($heroImageSrc !== ''): ?>
<meta property="og:image" content="<?= e($heroImageSrc) ?>">
<?php endif; ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,500;1,300;1,400&family=Jost:wght@300;400;500&display=swap">
<link rel="stylesheet" href="css/style.css?v=<?= e((string) @filemtime(__DIR__ . '/css/style.css')) ?>">
<noscript><style>
  .stage{position:static!important;height:auto!important;background:var(--notte)!important}
  .layer{position:relative!important;transform:none!important;opacity:1!important;visibility:visible!important;min-height:100svh;pointer-events:auto!important}
  .scroll-space,.dust,.rail{display:none!important}
  .sheet{display:block!important;position:static!important}
</style></noscript>
</head>
<body>

<!-- Fixed navigation -->
<header class="nav" id="nav">
  <a class="nav-brand" href="#viaggio-hero" data-goto="hero"><?= e($venueName) ?><?php if ($city !== ''): ?><span class="nav-brand-sub"><?= e(mb_strtoupper($city)) ?></span><?php endif; ?></a>
  <nav class="nav-links" aria-label="Fő navigáció">
    <a href="#viaggio-spazio" data-goto="spazio">A tér</a>
    <a href="#viaggio-offerta" data-goto="offerta">A kínálat</a>
    <a href="#viaggio-eventi" data-goto="eventi">Az évad</a>
    <a href="#viaggio-contatto" data-goto="contatto">Kapcsolat</a>
  </nav>
  <div class="nav-right">
    <a class="btn-book" href="#viaggio-richiesta" data-goto="richiesta">Ajánlatkérés</a>
    <button class="nav-burger" id="burger" aria-label="Menü megnyitása" aria-expanded="false" aria-controls="overlay-menu">
      <span></span><span></span>
    </button>
  </div>
</header>

<!-- Full-screen menu overlay (mobile) -->
<div class="overlay-menu" id="overlay-menu" hidden>
  <p class="overlay-eyebrow"><?= e(setting('overlay_eyebrow', '')) ?></p>
  <nav class="overlay-links" aria-label="Fejezetek">
    <a href="#viaggio-hero" data-goto="hero"><span>I</span>Kezdőlap</a>
    <a href="#viaggio-storia" data-goto="storia"><span>II</span>A szalon</a>
    <a href="#viaggio-spazio" data-goto="spazio"><span>III</span>A tér</a>
    <a href="#viaggio-offerta" data-goto="offerta"><span>IV</span>A kínálat</a>
    <a href="#viaggio-schermo" data-goto="schermo"><span>V</span>Az élő vászon</a>
    <a href="#viaggio-eventi" data-goto="eventi"><span>VI</span>Az évad</a>
    <a href="#viaggio-richiesta" data-goto="richiesta"><span>—</span>Ajánlatkérés</a>
    <a href="#viaggio-contatto" data-goto="contatto"><span>—</span>Kapcsolat</a>
  </nav>
</div>

<!-- The full offer sheet, opened from the "A kínálat" chapter -->
<?= render_offer_overlay() ?>

<!-- The season sheet, opened from the "Az évad" chapter -->
<?= render_season_overlay() ?>

<!-- The enquiry sheet, opened from the "Ajánlatkérés" chapter and from
     every package in the offer sheet. It scrolls, so the form fits any
     screen — a flight chapter cannot scroll. -->
<div class="sheet" id="enquiry-sheet" role="dialog" aria-modal="true" aria-label="Ajánlatkérés" hidden>
  <button class="sheet-close" type="button" data-close-sheet aria-label="Bezárás">×</button>
  <div class="sheet-wrap narrow-sheet">
    <header class="sheet-head">
      <p class="eyebrow"><?= e(setting('richiesta_eyebrow', '')) ?></p>
      <h2 class="sheet-heading"><?= e(setting('richiesta_heading', '')) ?></h2>
      <p class="sheet-lede"><?= e(setting('richiesta_text', '')) ?></p>
    </header>

    <?php if ($formError !== ''): ?>
    <p class="form-err" role="alert"><?= e($formError) ?></p>
    <?php endif; ?>

    <form class="enquiry" method="post" action="<?= e(BASE_URL) ?>/index.php#viaggio-richiesta">
      <input type="hidden" name="form" value="enquiry">
      <?= enquiry_stamp_fields() ?>
      <div class="fields">
        <p class="f"><label for="q-name">Név *</label>
          <input type="text" id="q-name" name="name" maxlength="120" required autocomplete="name" value="<?= e((string) ($_POST['name'] ?? '')) ?>"></p>
        <p class="f"><label for="q-email">E-mail *</label>
          <input type="email" id="q-email" name="email" maxlength="190" required autocomplete="email" value="<?= e((string) ($_POST['email'] ?? '')) ?>"></p>
        <p class="f"><label for="q-phone">Telefon</label>
          <input type="tel" id="q-phone" name="phone" maxlength="60" autocomplete="tel" value="<?= e((string) ($_POST['phone'] ?? '')) ?>"></p>
        <p class="f"><label for="q-company">Cég / szervezet</label>
          <input type="text" id="q-company" name="company" maxlength="160" autocomplete="organization" value="<?= e((string) ($_POST['company'] ?? '')) ?>"></p>
        <p class="f full-narrow"><label for="q-package">Mire gondolt?</label>
          <select id="q-package" name="package">
            <option value="">— válasszon, vagy hagyja üresen —</option>
            <?php foreach ($packages as $pkg): ?>
            <option value="<?= e($pkg['slug']) ?>"<?= (($_POST['package'] ?? '') === $pkg['slug']) ? ' selected' : '' ?>><?= e($pkg['name_it']) ?> — <?= e($pkg['name_hu']) ?></option>
            <?php endforeach; ?>
          </select></p>
        <p class="f"><label for="q-date">Tervezett időpont</label>
          <input type="date" id="q-date" name="event_date" min="<?= e(date('Y-m-d')) ?>" value="<?= e((string) ($_POST['event_date'] ?? '')) ?>"></p>
        <p class="f"><label for="q-guests">Létszám</label>
          <input type="number" id="q-guests" name="guests" min="1" max="2000" value="<?= e((string) ($_POST['guests'] ?? '')) ?>"></p>
      </div>
      <p class="f full"><label for="q-message">Az estéről</label>
        <textarea id="q-message" name="message" rows="4" maxlength="4000" placeholder="Milyen alkalom? Hány fő? Van már elképzelése a menetéről?"><?= e((string) ($_POST['message'] ?? '')) ?></textarea></p>
      <p class="f full"><button class="btn-book btn-book-big" type="submit">Ajánlatot kérek</button></p>
      <p class="micro-note">Az adatait kizárólag az ajánlat elkészítéséhez használjuk.<?php if ($phone !== ''): ?> Sürgős esetben hívjon: <a href="tel:<?= e(preg_replace('/\s+/', '', $phone)) ?>"><?= e($phone) ?></a>.<?php endif; ?></p>
    </form>
  </div>
</div>

<!-- The 3D stage: chapters live on the z-axis, native scroll flies the camera through them -->
<main class="stage" id="stage" aria-label="<?= e($venueName) ?> — egy este a szalonban">

  <!-- arrival -->
  <section class="layer ink-light" id="viaggio-hero" data-chapter="hero">
    <?php if ($heroVideoSrc !== ''): ?>
    <video class="hero-media" id="hero-video" autoplay muted loop playsinline preload="auto" aria-hidden="true">
      <source src="<?= e($heroVideoSrc) ?>" type="video/mp4">
    </video>
    <div class="hero-scrim" aria-hidden="true"></div>
    <?php elseif ($heroImageSrc !== ''): ?>
    <img class="hero-media" src="<?= e($heroImageSrc) ?>" alt="" aria-hidden="true" fetchpriority="high">
    <div class="hero-scrim" aria-hidden="true"></div>
    <?php endif; ?>
    <div class="layer-inner hero-inner">
      <p class="eyebrow"><?= e(setting('hero_eyebrow', '')) ?></p>
      <h1 class="hero-logo-wrap">
        <?php if ($logoSrc !== ''): ?>
          <img class="hero-logo" src="<?= e($logoSrc) ?>" alt="<?= e($venueName) ?>">
        <?php else: ?>
          <span class="hero-wordmark"><?= e($venueName) ?></span>
        <?php endif; ?>
      </h1>
      <p class="hero-tag"><?= e(setting('hero_tagline', '')) ?></p>
      <p class="scroll-hint" aria-hidden="true"><?= e(setting('scroll_hint', '')) ?><span class="scroll-line"></span></p>
    </div>
  </section>

  <!-- the salon introduces itself -->
  <section class="layer ink-light" id="viaggio-storia" data-chapter="storia">
    <div class="layer-inner narrow">
      <p class="eyebrow"><?= e($city !== '' ? $city . ' · a szalon' : 'A szalon') ?></p>
      <h2 class="display"><?= e(setting('story_heading', '')) ?><br><em><?= e(setting('story_heading_em', '')) ?></em></h2>
      <p class="lede"><?= e(setting('story_text', '')) ?></p>
      <p class="firma"><em><?= e(setting('story_signature', '')) ?></em></p>
    </div>
  </section>

  <!-- the space: two levels, one mood -->
  <section class="layer ink-light" id="viaggio-spazio" data-chapter="spazio">
    <div class="layer-inner">
      <p class="eyebrow"><?= e(setting('spazio_eyebrow', '')) ?></p>
      <h2 class="display"><?= e(setting('spazio_heading', '')) ?> <em><?= e(setting('spazio_heading_em', '')) ?></em></h2>
      <p class="chapter-note"><?= e(setting('spazio_text', '')) ?></p>
      <ul class="capacity">
        <?php foreach ([1, 2, 3] as $n):
            $num = setting('cap' . $n . '_num', '');
            if ($num === '' || $num === null) { continue; } ?>
        <li>
          <span class="cap-num"><?= e($num) ?></span>
          <span class="cap-label"><?= e(setting('cap' . $n . '_label', '')) ?></span>
          <span class="cap-note"><?= e(setting('cap' . $n . '_note', '')) ?></span>
        </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </section>

  <!-- the offer: six ways to take the salon -->
  <section class="layer ink-dark" id="viaggio-offerta" data-chapter="offerta">
    <div class="layer-inner wide">
      <p class="eyebrow"><?= e(setting('offerta_eyebrow', '')) ?></p>
      <h2 class="display"><?= e(setting('offerta_heading', '')) ?></h2>
      <p class="chapter-note"><?= e(setting('offerta_text', '')) ?></p>
      <?php if ($packages): ?>
      <ul class="pkg-grid">
        <?php foreach ($packages as $i => $pkg): ?><?= render_package_card($pkg, $i + 1) ?><?php endforeach; ?>
      </ul>
      <button class="btn-menu" type="button" data-open-sheet="offer-sheet"><?= e(setting('offerta_cta', 'A teljes kínálat')) ?></button>
      <?php endif; ?>
    </div>
  </section>

  <!-- the living canvas -->
  <section class="layer ink-light" id="viaggio-schermo" data-chapter="schermo">
    <div class="layer-inner narrow">
      <p class="eyebrow"><?= e(setting('schermo_eyebrow', '')) ?></p>
      <h2 class="display"><em><?= e(setting('schermo_heading', '')) ?></em></h2>
      <p class="lede"><?= e(setting('schermo_text', '')) ?></p>
    </div>
  </section>

  <!-- the season -->
  <section class="layer ink-light" id="viaggio-eventi" data-chapter="eventi">
    <div class="layer-inner">
      <p class="eyebrow"><?= e(setting('eventi_eyebrow', '')) ?></p>
      <h2 class="display"><?= e(setting('eventi_heading', '')) ?></h2>
      <p class="chapter-note"><?= e(setting('eventi_text', '')) ?></p>

      <?php if ($nextEvents): ?>
      <ul class="evt-next">
        <?php foreach ($nextEvents as $event): ?>
        <li>
          <span class="evt-next-date"><?= e(format_date_hu($event['event_date'])) ?></span>
          <span class="evt-next-title"><?= e($event['title']) ?></span>
          <?php if (!empty($event['series_name'])): ?><span class="evt-next-series"><?= e($event['series_name']) ?></span><?php endif; ?>
        </li>
        <?php endforeach; ?>
      </ul>
      <?php elseif ($series): ?>
      <ul class="evt-next">
        <?php foreach ($series as $s): ?>
        <li>
          <span class="evt-next-date"><?= e($s['cadence'] ?? '') ?></span>
          <span class="evt-next-title"><?= e($s['name']) ?></span>
          <span class="evt-next-series"><?= e($s['tagline'] ?? '') ?></span>
        </li>
        <?php endforeach; ?>
      </ul>
      <p class="micro-note"><?= e(setting('eventi_empty', '')) ?></p>
      <?php endif; ?>

      <?php if ($nextEvents || $series): ?>
      <button class="btn-menu" type="button" data-open-sheet="season-sheet"><?= e(setting('eventi_cta', 'Az évad estéi')) ?></button>
      <?php endif; ?>
    </div>
  </section>

  <!-- the enquiry: a short invitation; the form itself opens as a sheet -->
  <section class="layer ink-light" id="viaggio-richiesta" data-chapter="richiesta">
    <div class="layer-inner narrow">
      <p class="eyebrow"><?= e(setting('richiesta_eyebrow', '')) ?></p>
      <h2 class="display"><?= e(setting('richiesta_heading', '')) ?></h2>
      <?php if ($sent): ?>
      <p class="form-ok" role="status"><?= e(setting('richiesta_thanks', '')) ?></p>
      <?php else: ?>
      <p class="chapter-note"><?= e(setting('richiesta_text', '')) ?></p>
      <button class="btn-menu" type="button" data-open-sheet="enquiry-sheet">Ajánlatot kérek</button>
      <?php if ($phone !== '' || $email !== ''): ?>
      <p class="micro-note">Vagy keressen minket közvetlenül:
        <?php if ($phone !== ''): ?><a href="tel:<?= e(preg_replace('/\s+/', '', $phone)) ?>"><?= e($phone) ?></a><?php endif; ?>
        <?php if ($phone !== '' && $email !== ''): ?> · <?php endif; ?>
        <?php if ($email !== ''): ?><a href="mailto:<?= e($email) ?>"><?= e($email) ?></a><?php endif; ?>
      </p>
      <?php endif; ?>
      <?php endif; ?>
    </div>
  </section>

  <!-- contact / footer -->
  <section class="layer ink-light" id="viaggio-contatto" data-chapter="contatto">
    <div class="layer-inner">
      <p class="eyebrow">A presto</p>
      <h2 class="display display-small"><?= e(setting('closing_heading', '')) ?> <em><?= e(setting('closing_heading_em', '')) ?></em></h2>
      <?php if (setting('closing_text', '') !== ''): ?>
      <p class="chapter-note"><?= e(setting('closing_text', '')) ?></p>
      <?php endif; ?>
      <div class="contact-grid">
        <div>
          <h3 class="contact-label">Cím</h3>
          <p>
            <?php if (setting('map_url', '') !== ''): ?><a href="<?= e(setting('map_url')) ?>" target="_blank" rel="noopener"><?= e($addr1) ?><br><?= e($addr2) ?></a><?php else: ?><?= e($addr1) ?><br><?= e($addr2) ?><?php endif; ?>
          </p>
        </div>
        <?php if ($phone !== '' || $email !== ''): ?>
        <div>
          <h3 class="contact-label">Elérhetőség</h3>
          <p>
            <?php if ($phone !== ''): ?><a href="tel:<?= e(preg_replace('/\s+/', '', $phone)) ?>"><?= e($phone) ?></a><br><?php endif; ?>
            <?php if ($email !== ''): ?><a href="mailto:<?= e($email) ?>"><?= e($email) ?></a><?php endif; ?>
          </p>
        </div>
        <?php endif; ?>
        <?php if (setting('availability', '') !== ''): ?>
        <div>
          <h3 class="contact-label">Látogatás</h3>
          <p><?= e(setting('availability', '')) ?></p>
        </div>
        <?php endif; ?>
        <?php if (setting('facebook_url', '') !== '' || setting('instagram_url', '') !== '' || setting('tickets_url', '') !== ''): ?>
        <div>
          <h3 class="contact-label">Kövessen minket</h3>
          <p>
            <?php if (setting('facebook_url', '') !== ''): ?><a href="<?= e(setting('facebook_url')) ?>" target="_blank" rel="noopener">Facebook</a><br><?php endif; ?>
            <?php if (setting('instagram_url', '') !== ''): ?><a href="<?= e(setting('instagram_url')) ?>" target="_blank" rel="noopener">Instagram</a><br><?php endif; ?>
            <?php if (setting('tickets_url', '') !== ''): ?><a href="<?= e(setting('tickets_url')) ?>" target="_blank" rel="noopener">Jegyek</a><?php endif; ?>
          </p>
        </div>
        <?php endif; ?>
      </div>
      <p class="footer-line">© <span id="year"><?= e(date('Y')) ?></span> <?= e($venueName) ?><?= setting('footer_extra', '') !== '' ? ' · ' . e(setting('footer_extra', '')) : '' ?></p>
    </div>
  </section>
</main>

<!-- the evening's progress (desktop) -->
<aside class="rail" id="rail" aria-hidden="true">
  <div class="rail-line"><div class="rail-fill" id="rail-fill"></div></div>
  <ol class="rail-stops" id="rail-stops"></ol>
</aside>

<!-- native scroll driver -->
<div class="scroll-space" id="scroll-space" aria-hidden="true"></div>

<div class="grain" aria-hidden="true"></div>

<script>
/* The flight's chapter spine, computed server-side. */
window.SITE_JOURNEY = <?= json_encode($journey, JSON_UNESCAPED_UNICODE) ?>;
window.SITE_FORM_ERROR = <?= $formError !== '' ? 'true' : 'false' ?>;
</script>
<script src="js/main.js?v=<?= e((string) @filemtime(__DIR__ . '/js/main.js')) ?>"></script>
</body>
</html>
