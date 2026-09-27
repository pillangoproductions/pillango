<?php
/**
 * Események — the dated evenings.
 *
 * Past evenings fall off the public site on their own (the query only
 * asks for today onwards), so nothing has to be tidied away by hand.
 * They stay listed here as the venue's own history.
 */

require_once __DIR__ . '/../includes/auth.php';
require_login();

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = (string) ($_POST['action'] ?? '');
    $id     = (int) ($_POST['id'] ?? 0);

    if ($action === 'save') {
        $title   = trim((string) ($_POST['title'] ?? ''));
        $dateRaw = trim((string) ($_POST['event_date'] ?? ''));
        $timeRaw = trim((string) ($_POST['start_time'] ?? ''));
        $ticket  = trim((string) ($_POST['ticket_url'] ?? ''));

        if ($title === '') {
            flash_set('Az eseménynek kell cím.', 'err');
            redirect('events.php');
        }
        $d = DateTime::createFromFormat('Y-m-d', $dateRaw);
        if ($d === false || $d->format('Y-m-d') !== $dateRaw) {
            flash_set('Adj meg érvényes dátumot.', 'err');
            redirect('events.php');
        }
        if ($ticket !== '' && !filter_var($ticket, FILTER_VALIDATE_URL)) {
            flash_set('A jegyvásárlási link nem érvényes URL.', 'err');
            redirect('events.php');
        }

        $seriesId = (int) ($_POST['series_id'] ?? 0);
        $params = [
            'series_id'     => $seriesId > 0 ? $seriesId : null,
            'title'         => mb_substr($title, 0, 190),
            'event_date'    => $dateRaw,
            'start_time'    => preg_match('/^\d{1,2}:\d{2}$/', $timeRaw) ? $timeRaw : null,
            'lead_text'     => trim((string) ($_POST['lead'] ?? '')) ?: null,
            'body'          => trim((string) ($_POST['body'] ?? '')) ?: null,
            'price_note'    => trim((string) ($_POST['price_note'] ?? '')) ?: null,
            'capacity_note' => trim((string) ($_POST['capacity_note'] ?? '')) ?: null,
            'ticket_url'    => $ticket !== '' ? $ticket : null,
            'is_sold_out'   => isset($_POST['is_sold_out']) ? 1 : 0,
        ];

        if ($id > 0) {
            $pdo->prepare(
                'UPDATE events SET series_id = :series_id, title = :title, event_date = :event_date,
                        start_time = :start_time, lead_text = :lead_text, body = :body,
                        price_note = :price_note, capacity_note = :capacity_note,
                        ticket_url = :ticket_url, is_sold_out = :is_sold_out
                  WHERE id = :id'
            )->execute($params + ['id' => $id]);
            flash_set('Esemény mentve.');
        } else {
            $pdo->prepare(
                'INSERT INTO events (series_id, title, event_date, start_time, lead_text, body,
                                     price_note, capacity_note, ticket_url, is_sold_out)
                 VALUES (:series_id, :title, :event_date, :start_time, :lead_text, :body,
                         :price_note, :capacity_note, :ticket_url, :is_sold_out)'
            )->execute($params);
            flash_set('Esemény létrehozva.');
        }
        redirect('events.php');
    }

    if ($action === 'toggle' && $id > 0) {
        $pdo->prepare('UPDATE events SET is_active = 1 - is_active WHERE id = :id')->execute([':id' => $id]);
        redirect('events.php');
    }

    if ($action === 'soldout' && $id > 0) {
        $pdo->prepare('UPDATE events SET is_sold_out = 1 - is_sold_out WHERE id = :id')->execute([':id' => $id]);
        redirect('events.php');
    }

    if ($action === 'delete' && $id > 0) {
        $pdo->prepare('DELETE FROM events WHERE id = :id')->execute([':id' => $id]);
        flash_set('Esemény törölve.');
        redirect('events.php');
    }
}

$seriesList = $pdo->query('SELECT id, name FROM event_series ORDER BY sort_order, id')->fetchAll();

$events = $pdo->query(
    'SELECT e.*, s.name AS series_name
       FROM events e LEFT JOIN event_series s ON s.id = e.series_id
      ORDER BY e.event_date DESC, e.start_time DESC, e.id DESC'
)->fetchAll();

$editId  = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
$adding  = isset($_GET['add']);
$editRow = null;
foreach ($events as $ev) {
    if ((int) $ev['id'] === $editId) {
        $editRow = $ev;
    }
}

$today    = date('Y-m-d');
$upcoming = 0;
foreach ($events as $ev) {
    if ($ev['event_date'] >= $today && $ev['is_active']) {
        $upcoming++;
    }
}

$pageTitle = 'Események';
$current   = 'events';
require __DIR__ . '/../includes/header.php';
?>

<?php if ($adding || $editRow !== null): $r = $editRow; ?>
<div class="panel">
  <h2 style="margin-top:0"><?= $r ? 'Esemény szerkesztése' : 'Új esemény' ?></h2>
  <form method="post" action="events.php">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= $r ? (int) $r['id'] : 0 ?>">
    <div class="grid2">
      <p><label for="e-title">Cím *</label>
        <input type="text" id="e-title" name="title" maxlength="190" required
               value="<?= e($r['title'] ?? '') ?>" placeholder="pl. Évadnyitó kamaraest"></p>
      <p><label for="e-series">Sorozat</label>
        <select id="e-series" name="series_id">
          <option value="0">— egyik sem —</option>
          <?php foreach ($seriesList as $s): ?>
          <option value="<?= (int) $s['id'] ?>"<?= (int) ($r['series_id'] ?? 0) === (int) $s['id'] ? ' selected' : '' ?>><?= e($s['name']) ?></option>
          <?php endforeach; ?>
        </select></p>
      <p><label for="e-date">Dátum *</label>
        <input type="date" id="e-date" name="event_date" required value="<?= e($r['event_date'] ?? '') ?>"></p>
      <p><label for="e-time">Kezdés (óó:pp)</label>
        <input type="time" id="e-time" name="start_time"
               value="<?= e($r !== null && $r['start_time'] !== null ? substr((string) $r['start_time'], 0, 5) : '') ?>"></p>
      <p class="full"><label for="e-lead">Egy soros leírás</label>
        <input type="text" id="e-lead" name="lead" maxlength="255" value="<?= e($r['lead_text'] ?? '') ?>"
               placeholder="Kamarazene a zongoránál, welcome itallal"></p>
      <p class="full"><label for="e-body">Hosszabb leírás</label>
        <textarea id="e-body" name="body" rows="3"><?= e($r['body'] ?? '') ?></textarea></p>
      <p><label for="e-price">Ár-megjegyzés</label>
        <input type="text" id="e-price" name="price_note" maxlength="120"
               value="<?= e($r['price_note'] ?? '') ?>" placeholder="12 000 Ft / jegy"></p>
      <p><label for="e-cap">Létszám-megjegyzés</label>
        <input type="text" id="e-cap" name="capacity_note" maxlength="120"
               value="<?= e($r['capacity_note'] ?? '') ?>" placeholder="Mindössze 50 hely"></p>
      <p class="full"><label for="e-ticket">Jegyvásárlási link</label>
        <input type="url" id="e-ticket" name="ticket_url" maxlength="255"
               value="<?= e($r['ticket_url'] ?? '') ?>" placeholder="https://…">
        <span class="hint">Ide jöhet a meglévő jegyértékesítő oldal linkje. Üresen hagyva
          nem jelenik meg „Jegyet váltok" gomb.</span></p>
      <p class="full"><label class="check"><input type="checkbox" name="is_sold_out" value="1"
          <?= !empty($r['is_sold_out']) ? 'checked' : '' ?>> Elkelt (a jegylink helyett „Elkelt" felirat)</label></p>
    </div>
    <p style="margin-bottom:0">
      <button class="btn red" type="submit"><?= $r ? 'Mentés' : 'Létrehozás' ?></button>
      <a class="btn ghost" href="events.php">Mégsem</a>
    </p>
  </form>
</div>
<?php else: ?>
<p>
  <a class="btn red" href="events.php?add=1">+ Új esemény</a>
  <span class="muted" style="margin-left:0.6rem"><?= $upcoming ?> közelgő est van kitűzve.</span>
</p>
<?php endif; ?>

<?php if (!$seriesList): ?>
<div class="flash err">Még nincs egyetlen sorozat sem — a <a href="series.php">Sorozatok</a>
  oldalon érdemes felvinni a formátumokat (pl. Salotto Musicale), hogy az esték
  a weboldalon a sorozatuk nevével jelenjenek meg.</div>
<?php endif; ?>

<table>
  <thead>
    <tr><th style="width:8rem">Dátum</th><th>Esemény</th><th>Sorozat</th><th>Jegy</th><th>Látható</th><th class="actions">Műveletek</th></tr>
  </thead>
  <tbody>
  <?php if (!$events): ?>
    <tr><td colspan="6" class="muted">Még nincs kitűzött est. Amíg nincs, a weboldalon
      a visszatérő formátumok (Sorozatok) jelennek meg.</td></tr>
  <?php endif; ?>
  <?php foreach ($events as $ev):
      $past = $ev['event_date'] < $today; ?>
    <tr class="<?= (!$ev['is_active'] || $past) ? 'item-off' : '' ?>">
      <td>
        <strong><?= e(format_date_hu($ev['event_date'])) ?></strong>
        <?php if ($ev['start_time'] !== null): ?><br><span class="muted"><?= e(substr((string) $ev['start_time'], 0, 5)) ?></span><?php endif; ?>
        <?php if ($past): ?><br><span class="pill">elmúlt</span><?php endif; ?>
      </td>
      <td><strong><?= e($ev['title']) ?></strong>
        <?php if (!empty($ev['lead_text'])): ?><div class="hint"><?= e($ev['lead_text']) ?></div><?php endif; ?>
        <?php if (!empty($ev['price_note']) || !empty($ev['capacity_note'])): ?>
        <div class="hint"><?= e(trim(implode(' · ', array_filter([$ev['price_note'] ?? '', $ev['capacity_note'] ?? ''])))) ?></div>
        <?php endif; ?>
      </td>
      <td><?= e($ev['series_name'] ?? '—') ?></td>
      <td>
        <form method="post" class="inline"><?= csrf_field() ?>
          <input type="hidden" name="action" value="soldout"><input type="hidden" name="id" value="<?= (int) $ev['id'] ?>">
          <button class="btn small <?= $ev['is_sold_out'] ? '' : 'ghost' ?>"><?= $ev['is_sold_out'] ? 'Elkelt' : 'Kapható' ?></button></form>
      </td>
      <td>
        <form method="post" class="inline"><?= csrf_field() ?>
          <input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $ev['id'] ?>">
          <button class="btn small <?= $ev['is_active'] ? '' : 'ghost' ?>"><?= $ev['is_active'] ? 'Látható' : 'Rejtve' ?></button></form>
      </td>
      <td class="actions">
        <a class="btn small ghost" href="events.php?edit=<?= (int) $ev['id'] ?>">Szerkeszt</a>
        <form method="post" class="inline" onsubmit="return confirm('Biztosan törlöd ezt az estet?')"><?= csrf_field() ?>
          <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $ev['id'] ?>">
          <button class="btn small danger">Törlés</button></form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>

<div class="panel" style="margin-top:1.6rem">
  <h2 style="margin-top:0">Hogyan jelenik meg?</h2>
  <p>A főoldal <strong>„Az évad"</strong> fejezetében a három legközelebbi est látszik;
     a gombra megnyílik a teljes évad-lap az összes közelgő estével és a visszatérő
     formátumokkal.</p>
  <p>Ha még nincs egyetlen kitűzött est sem, a fejezet automatikusan a
     <a href="series.php">sorozatokat</a> mutatja — így az oldal sosem néz ki üresen.</p>
  <p><strong>A lejárt esték maguktól lekerülnek a weboldalról</strong>, nem kell velük
     foglalkozni. Itt továbbra is látszanak, „elmúlt" jelzéssel.</p>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
