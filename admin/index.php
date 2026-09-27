<?php
/**
 * Admin dashboard — what needs attention, then the shortcuts.
 */

require_once __DIR__ . '/../includes/auth.php';
require_login();

$pdo = db();

$newEnq       = (int) $pdo->query("SELECT COUNT(*) FROM enquiries WHERE status = 'new'")->fetchColumn();
$enqThisMonth = (int) $pdo->query(
    "SELECT COUNT(*) FROM enquiries WHERE created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')"
)->fetchColumn();
$packagesLive = (int) $pdo->query('SELECT COUNT(*) FROM packages WHERE is_active = 1')->fetchColumn();
$packagesAll  = (int) $pdo->query('SELECT COUNT(*) FROM packages')->fetchColumn();
$upcoming     = (int) $pdo->query(
    'SELECT COUNT(*) FROM events WHERE is_active = 1 AND event_date >= CURDATE()'
)->fetchColumn();
$galleryLive  = (int) $pdo->query('SELECT COUNT(*) FROM gallery WHERE is_active = 1')->fetchColumn();

$nextEvent = $pdo->query(
    'SELECT e.*, s.name AS series_name
       FROM events e LEFT JOIN event_series s ON s.id = e.series_id
      WHERE e.is_active = 1 AND e.event_date >= CURDATE()
      ORDER BY e.event_date, e.start_time LIMIT 1'
)->fetch();

$lastUpdated = $pdo->query(
    'SELECT MAX(t) FROM (
        SELECT MAX(updated_at) AS t FROM packages
        UNION ALL SELECT MAX(updated_at) FROM addons
        UNION ALL SELECT MAX(updated_at) FROM events
        UNION ALL SELECT MAX(updated_at) FROM event_series
        UNION ALL SELECT MAX(updated_at) FROM gallery
        UNION ALL SELECT MAX(updated_at) FROM site_settings
     ) AS x'
)->fetchColumn();

/* Things worth nudging about on the first screen. */
$todo = [];
if (setting('enquiry_recipient', '') === '') {
    $todo[] = ['Nincs beállítva értesítési e-mail cím az ajánlatkérésekhez.', 'settings.php', 'Beállítom'];
}
if (setting('phone', '') === '' || setting('address_line1', '') === '') {
    $todo[] = ['A helyszín címe vagy telefonszáma hiányos.', 'settings.php', 'Kitöltöm'];
}
if ($upcoming === 0) {
    $todo[] = ['Nincs kitűzött est — a weboldal most a visszatérő formátumokat mutatja.', 'events.php', 'Estet tűzök ki'];
}
if ($galleryLive === 0) {
    $todo[] = ['Nincs látható galéria fotó a látogatóúton.', 'gallery.php', 'Fotót töltök fel'];
}

$pageTitle = 'Vezérlőpult';
$current   = 'dashboard';
require __DIR__ . '/../includes/header.php';
?>

<div class="cards">
  <div class="card">
    <div class="num"><?= $newEnq ?></div>
    <div class="lbl">Új ajánlatkérés</div>
  </div>
  <div class="card">
    <div class="num"><?= $enqThisMonth ?></div>
    <div class="lbl">Ajánlatkérés ebben a hónapban</div>
  </div>
  <div class="card">
    <div class="num"><?= $packagesLive ?> <span class="muted" style="font-size:0.55em">/ <?= $packagesAll ?></span></div>
    <div class="lbl">Látható csomag / összes</div>
  </div>
  <div class="card">
    <div class="num"><?= $upcoming ?></div>
    <div class="lbl">Közelgő est</div>
  </div>
  <div class="card">
    <div class="num"><?= $galleryLive ?></div>
    <div class="lbl">Galéria fotó a látogatóúton</div>
  </div>
  <div class="card">
    <div class="num" style="font-size:1.1rem"><?= $lastUpdated ? e(date('Y. m. d. H:i', strtotime((string) $lastUpdated))) : '—' ?></div>
    <div class="lbl">Utolsó módosítás</div>
  </div>
</div>

<?php if ($newEnq > 0): ?>
<div class="flash"><strong><?= $newEnq ?></strong> olvasatlan ajánlatkérés vár —
  <a href="enquiries.php">megnézem</a>.</div>
<?php endif; ?>

<?php if ($todo): ?>
<h2>Amire érdemes ránézni</h2>
<div class="panel">
  <?php foreach ($todo as [$text, $link, $label]): ?>
  <p style="display:flex;gap:0.8rem;align-items:baseline;justify-content:space-between;flex-wrap:wrap">
    <span><?= e($text) ?></span>
    <a class="btn small ghost" href="<?= e($link) ?>"><?= e($label) ?></a>
  </p>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($nextEvent): ?>
<h2>A következő est</h2>
<div class="panel">
  <p style="margin:0">
    <strong><?= e(format_date_hu((string) $nextEvent['event_date'])) ?></strong>
    <?php if ($nextEvent['start_time'] !== null): ?> · <?= e(substr((string) $nextEvent['start_time'], 0, 5)) ?><?php endif; ?>
    — <?= e($nextEvent['title']) ?>
    <?php if (!empty($nextEvent['series_name'])): ?><span class="pill"><?= e($nextEvent['series_name']) ?></span><?php endif; ?>
    <?php if ($nextEvent['is_sold_out']): ?><span class="pill new">elkelt</span><?php endif; ?>
  </p>
  <p style="margin:0.6rem 0 0"><a class="btn small ghost" href="events.php?edit=<?= (int) $nextEvent['id'] ?>">Szerkesztem</a></p>
</div>
<?php endif; ?>

<h2>Gyors műveletek</h2>
<p>
  <a class="btn" href="events.php?add=1">+ Új est kitűzése</a>
  <a class="btn ghost" href="enquiries.php">Ajánlatkérések</a>
  <a class="btn ghost" href="packages.php">Csomagok szerkesztése</a>
  <a class="btn ghost" href="gallery.php">+ Galéria fotó</a>
  <a class="btn ghost" href="settings.php">A weboldal szövegei</a>
</p>

<h2>Hogyan épül fel a weboldal?</h2>
<div class="panel">
  <p>A főoldal egy <strong>estén vezeti végig</strong> a látogatót: nyitókép → a szalon →
     fotóoldalak → a tér → <strong>a kínálat</strong> → az élő vászon → <strong>az évad</strong>
     → ajánlatkérés → kapcsolat.</p>
  <p><strong>Csomagok</strong> — a hatféle bérlési mód. A főoldalon kártyaként, a teljes
     ajánlati lapon részletesen jelennek meg, a <strong>Kiegészítőkkel</strong> együtt.</p>
  <p><strong>Események</strong> — a dátumos esték. A lejártak maguktól lekerülnek.
     <strong>Sorozatok</strong> — a visszatérő formátumok, amelyek akkor is látszanak,
     ha épp nincs kitűzött est.</p>
  <p><strong>Galéria</strong> — a feltöltött fotók teljes képernyős oldalakként jelennek
     meg a látogatóúton. Minden új fotó egy fejezettel hosszabbítja az utazást.</p>
  <p><strong>Beállítások</strong> — a helyszín adatai, a nyitókép, és a főoldal
     <em>összes szövege</em>. HTML-hez nyúlni nem kell.</p>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
