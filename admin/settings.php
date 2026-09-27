<?php
/**
 * Beállítások — the venue's details, and every word on the public site.
 *
 * Nothing on the front page is hard-coded: each chapter's eyebrow,
 * heading and paragraph is a row here, so the owner never edits HTML.
 */

require_once __DIR__ . '/../includes/auth.php';
require_login();

/** key => [label, type] — every editable setting. */
const SETTING_FIELDS = [
    // --- the venue ---
    'venue_name'        => ['A helyszín neve', 'text'],
    'venue_city'        => ['Város', 'text'],
    'address_line1'     => ['Cím — utca, házszám', 'text'],
    'address_line2'     => ['Cím — irányítószám, város', 'text'],
    'phone'             => ['Telefonszám', 'text'],
    'email'             => ['E-mail cím', 'email'],
    'map_url'           => ['Térkép link', 'url'],
    'availability'      => ['Látogatás / egyeztetés szövege', 'textarea'],
    'facebook_url'      => ['Facebook URL', 'url'],
    'instagram_url'     => ['Instagram URL', 'url'],
    'tickets_url'       => ['Jegyértékesítés URL', 'url'],
    // --- enquiries ---
    'enquiry_recipient' => ['Értesítési e-mail cím (ide jönnek az ajánlatkérések)', 'email'],
    // --- the opening ---
    'hero_eyebrow'      => ['Nyitókép — felső kis felirat', 'text'],
    'hero_tagline'      => ['Nyitókép — mondat a név alatt', 'textarea'],
    'scroll_hint'       => ['Nyitókép — görgetésre hívó szöveg', 'text'],
    'overlay_eyebrow'   => ['Mobil menü felirata', 'text'],
    // --- the salon ---
    'story_heading'     => ['A szalon — cím 1. sor', 'text'],
    'story_heading_em'  => ['A szalon — cím 2. sor (dőlt, arany)', 'text'],
    'story_text'        => ['A szalon — bemutatkozó szöveg', 'textarea'],
    'story_signature'   => ['A szalon — záró sor', 'text'],
    // --- the space ---
    'spazio_eyebrow'    => ['A tér — kis felirat', 'text'],
    'spazio_heading'    => ['A tér — cím 1. rész', 'text'],
    'spazio_heading_em' => ['A tér — cím 2. rész (dőlt)', 'text'],
    'spazio_text'       => ['A tér — szöveg', 'textarea'],
    'cap1_num'          => ['1. szám (pl. 70)', 'text'],
    'cap1_label'        => ['1. szám felirata', 'text'],
    'cap1_note'         => ['1. szám megjegyzése', 'text'],
    'cap2_num'          => ['2. szám (pl. 100)', 'text'],
    'cap2_label'        => ['2. szám felirata', 'text'],
    'cap2_note'         => ['2. szám megjegyzése', 'text'],
    'cap3_num'          => ['3. szám (pl. 24)', 'text'],
    'cap3_label'        => ['3. szám felirata', 'text'],
    'cap3_note'         => ['3. szám megjegyzése', 'text'],
    // --- the offer ---
    'offerta_eyebrow'   => ['A kínálat — kis felirat', 'text'],
    'offerta_heading'   => ['A kínálat — cím', 'text'],
    'offerta_text'      => ['A kínálat — szöveg', 'textarea'],
    'offerta_cta'       => ['A kínálat — gomb szövege', 'text'],
    'addons_heading'    => ['Kiegészítők — cím', 'text'],
    'addons_text'       => ['Kiegészítők — szöveg', 'textarea'],
    'price_note'        => ['Ajánlati lap — „Jó tudni" lábjegyzet', 'textarea'],
    // --- the living canvas ---
    'schermo_eyebrow'   => ['Az élő vászon — kis felirat', 'text'],
    'schermo_heading'   => ['Az élő vászon — cím', 'text'],
    'schermo_text'      => ['Az élő vászon — szöveg', 'textarea'],
    // --- the season ---
    'eventi_eyebrow'    => ['Az évad — kis felirat', 'text'],
    'eventi_heading'    => ['Az évad — cím', 'text'],
    'eventi_text'       => ['Az évad — szöveg', 'textarea'],
    'eventi_empty'      => ['Az évad — szöveg, ha nincs kitűzött est', 'textarea'],
    'eventi_cta'        => ['Az évad — gomb szövege', 'text'],
    // --- the enquiry ---
    'richiesta_eyebrow' => ['Ajánlatkérés — kis felirat', 'text'],
    'richiesta_heading' => ['Ajánlatkérés — cím', 'text'],
    'richiesta_text'    => ['Ajánlatkérés — szöveg', 'textarea'],
    'richiesta_thanks'  => ['Ajánlatkérés — köszönő szöveg beküldés után', 'textarea'],
    // --- the goodbye ---
    'closing_heading'   => ['Záró cím — 1. rész', 'text'],
    'closing_heading_em'=> ['Záró cím — 2. rész (dőlt)', 'text'],
    'closing_text'      => ['Záró szöveg', 'textarea'],
    'footer_extra'      => ['Lábléc kiegészítés', 'text'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    // Save every known setting; unknown POST keys are ignored.
    foreach (SETTING_FIELDS as $key => [$label, $type]) {
        if (!isset($_POST[$key])) {
            continue;
        }
        $value = trim((string) $_POST[$key]);
        if ($type === 'url' && $value !== '' && !filter_var($value, FILTER_VALIDATE_URL)) {
            flash_set('Érvénytelen URL: ' . $label, 'err');
            redirect('settings.php');
        }
        if ($type === 'email' && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            flash_set('Érvénytelen e-mail cím: ' . $label, 'err');
            redirect('settings.php');
        }
        set_setting($key, $value);
    }

    // Optional logo (transparent PNG recommended) and opening photo.
    foreach (['logo' => 'logo', 'hero_image' => 'hero_image'] as $field => $settingKey) {
        if (empty($_FILES[$field]['name'])) {
            continue;
        }
        $up = handle_image_upload($_FILES[$field], UPLOAD_BRANDING_DIR);
        if (!$up['ok']) {
            flash_set($up['error'], 'err');
            redirect('settings.php');
        }
        delete_upload(UPLOAD_BRANDING_DIR, setting($settingKey, ''));
        set_setting($settingKey, $up['filename']);
    }

    flash_set('Beállítások mentve.');
    redirect('settings.php');
}

$logoFile = setting('logo', '');
$logoSrc  = $logoFile !== '' ? upload_url('branding', $logoFile) : '';
$heroFile = setting('hero_image', '');
$heroSrc  = $heroFile !== '' ? upload_url('branding', $heroFile, true) : '';

/**
 * Render one field of the form.
 */
function setting_field(string $key, bool $full = false): void
{
    [$label, $type] = SETTING_FIELDS[$key];
    $id = 's-' . $key;
    echo '<p' . ($full || $type === 'textarea' ? ' class="full"' : '') . '>';
    echo '<label for="' . e($id) . '">' . e($label) . '</label>';
    if ($type === 'textarea') {
        echo '<textarea id="' . e($id) . '" name="' . e($key) . '" rows="3">' . e(setting($key, '')) . '</textarea>';
    } else {
        echo '<input type="' . e($type) . '" id="' . e($id) . '" name="' . e($key) . '" value="' . e(setting($key, '')) . '">';
    }
    echo '</p>';
}

$pageTitle = 'Beállítások';
$current   = 'settings';
require __DIR__ . '/../includes/header.php';
?>

<form method="post" action="settings.php" enctype="multipart/form-data">
  <?= csrf_field() ?>

  <div class="panel">
    <h2 style="margin-top:0">A helyszín adatai</h2>
    <div class="grid2">
      <?php foreach (['venue_name', 'venue_city', 'address_line1', 'address_line2',
                      'phone', 'email', 'map_url'] as $k) { setting_field($k); } ?>
      <?php setting_field('availability'); ?>
    </div>
  </div>

  <div class="panel">
    <h2 style="margin-top:0">Közösségi média és jegyek</h2>
    <div class="grid2">
      <?php foreach (['facebook_url', 'instagram_url', 'tickets_url'] as $k) { setting_field($k); } ?>
    </div>
    <p class="hint">A <strong>Jegyértékesítés URL</strong> a lábléc „Jegyek" linkje. Az egyes
       esték saját jegylinkje az <a href="events.php">Események</a> oldalon adható meg.</p>
  </div>

  <div class="panel">
    <h2 style="margin-top:0">Ajánlatkérés</h2>
    <div class="grid2">
      <?php setting_field('enquiry_recipient'); ?>
    </div>
    <p class="hint">Ide megy értesítő levél minden új ajánlatkérésről. Üresen hagyva nem
       megy levél — a kérések akkor is megmaradnak az
       <a href="enquiries.php">Ajánlatkérések</a> oldalon.</p>
  </div>

  <div class="panel">
    <h2 style="margin-top:0">Nyitókép és logó</h2>
    <div class="grid2">
      <div>
        <p><label for="s-hero_image">Nyitókép (a teljes képernyős fotó a név mögött)</label>
          <input type="file" id="s-hero_image" name="hero_image" accept=".jpg,.jpeg,.png,.webp"></p>
        <?php if ($heroSrc !== ''): ?>
        <p><img src="<?= e($heroSrc) ?>" alt="Jelenlegi nyitókép" style="max-width:260px;border-radius:8px;border:1px solid var(--line)"></p>
        <?php else: ?>
        <p class="hint">Nincs nyitókép — a nyitóképernyő éjfekete alapon jelenik meg.</p>
        <?php endif; ?>
      </div>
      <div>
        <p><label for="s-logo">Logó (átlátszó hátterű PNG ajánlott)</label>
          <input type="file" id="s-logo" name="logo" accept=".png,.jpg,.jpeg,.webp"></p>
        <?php if ($logoSrc !== ''): ?>
        <p><img src="<?= e($logoSrc) ?>" alt="Jelenlegi logó" style="max-width:240px;background:#221B14;padding:0.8rem;border-radius:8px"></p>
        <?php else: ?>
        <p class="hint">Nincs logó — a helyszín neve jelenik meg szép szedéssel. Ez rendben
           van, nem kell logót feltölteni.</p>
        <?php endif; ?>
      </div>
    </div>
    <p class="hint">Hero videó: tedd a fájlt <code>assets/hero-video.mp4</code> néven a
       szerverre — ha van, a videó élvez elsőbbséget a nyitóképpel szemben.</p>
  </div>

  <div class="panel">
    <h2 style="margin-top:0">A weboldal szövegei</h2>
    <p class="muted" style="margin-top:-0.4rem">Ezek a mondatok jelennek meg a főoldalon,
       fejezetenként, ugyanabban a sorrendben, ahogy a látogató találkozik velük.</p>

    <h3>Nyitókép</h3>
    <div class="grid2">
      <?php foreach (['hero_eyebrow', 'scroll_hint', 'overlay_eyebrow'] as $k) { setting_field($k); } ?>
      <?php setting_field('hero_tagline'); ?>
    </div>

    <h3>A szalon</h3>
    <div class="grid2">
      <?php foreach (['story_heading', 'story_heading_em', 'story_signature'] as $k) { setting_field($k); } ?>
      <?php setting_field('story_text'); ?>
    </div>

    <h3>A tér</h3>
    <div class="grid2">
      <?php foreach (['spazio_eyebrow', 'spazio_heading', 'spazio_heading_em'] as $k) { setting_field($k); } ?>
      <?php setting_field('spazio_text'); ?>
      <?php foreach (['cap1_num', 'cap1_label', 'cap1_note',
                      'cap2_num', 'cap2_label', 'cap2_note',
                      'cap3_num', 'cap3_label', 'cap3_note'] as $k) { setting_field($k); } ?>
    </div>

    <h3>A kínálat</h3>
    <div class="grid2">
      <?php foreach (['offerta_eyebrow', 'offerta_heading', 'offerta_cta',
                      'addons_heading'] as $k) { setting_field($k); } ?>
      <?php foreach (['offerta_text', 'addons_text', 'price_note'] as $k) { setting_field($k); } ?>
    </div>

    <h3>Az élő vászon</h3>
    <div class="grid2">
      <?php foreach (['schermo_eyebrow', 'schermo_heading'] as $k) { setting_field($k); } ?>
      <?php setting_field('schermo_text'); ?>
    </div>

    <h3>Az évad</h3>
    <div class="grid2">
      <?php foreach (['eventi_eyebrow', 'eventi_heading', 'eventi_cta'] as $k) { setting_field($k); } ?>
      <?php foreach (['eventi_text', 'eventi_empty'] as $k) { setting_field($k); } ?>
    </div>

    <h3>Ajánlatkérés</h3>
    <div class="grid2">
      <?php foreach (['richiesta_eyebrow', 'richiesta_heading'] as $k) { setting_field($k); } ?>
      <?php foreach (['richiesta_text', 'richiesta_thanks'] as $k) { setting_field($k); } ?>
    </div>

    <h3>Búcsú</h3>
    <div class="grid2">
      <?php foreach (['closing_heading', 'closing_heading_em', 'footer_extra'] as $k) { setting_field($k); } ?>
      <?php setting_field('closing_text'); ?>
    </div>
  </div>

  <p><button class="btn red" type="submit">Beállítások mentése</button></p>
</form>

<?php require __DIR__ . '/../includes/footer.php'; ?>
