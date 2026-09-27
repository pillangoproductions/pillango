<?php
/**
 * Sorozatok — the recurring evening formats (Salotto Musicale,
 * Degustazione, Supper Club, Ballo in Maschera).
 *
 * These are evergreen: they describe what kind of evenings happen here,
 * and they show on the public season sheet even when no date is pinned.
 * A dated event (admin → Események) can point at one of them.
 */

require_once __DIR__ . '/../includes/auth.php';
require_login();

$pdo = db();

/**
 * A slug nobody else in event_series is using yet.
 */
function unique_series_slug(PDO $pdo, string $base, int $ignoreId = 0): string
{
    $map = ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ö'=>'o','ő'=>'o','ú'=>'u','ü'=>'u','ű'=>'u'];
    $base = preg_replace('/[^a-z0-9]+/', '-', strtr(mb_strtolower(trim($base)), $map)) ?? '';
    $base = trim($base, '-');
    $base = $base !== '' ? $base : 'sorozat';
    $slug = mb_substr($base, 0, 50);
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM event_series WHERE slug = :s AND id <> :id');
    for ($n = 2; $n < 100; $n++) {
        $stmt->execute([':s' => $slug, ':id' => $ignoreId]);
        if ((int) $stmt->fetchColumn() === 0) {
            return $slug;
        }
        $slug = mb_substr($base, 0, 46) . '-' . $n;
    }
    return $base . '-' . bin2hex(random_bytes(3));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = (string) ($_POST['action'] ?? '');
    $id     = (int) ($_POST['id'] ?? 0);

    if ($action === 'save') {
        $name = trim((string) ($_POST['name'] ?? ''));
        if ($name === '') {
            flash_set('A sorozatnak kell név.', 'err');
            redirect('series.php');
        }
        $params = [
            'name'        => mb_substr($name, 0, 120),
            'cadence'     => trim((string) ($_POST['cadence'] ?? '')) ?: null,
            'tagline'     => trim((string) ($_POST['tagline'] ?? '')) ?: null,
            'description' => trim((string) ($_POST['description'] ?? '')) ?: null,
        ];
        if ($id > 0) {
            $pdo->prepare('UPDATE event_series SET name = :name, cadence = :cadence,
                           tagline = :tagline, description = :description WHERE id = :id')
                ->execute($params + ['id' => $id]);
            flash_set('Sorozat mentve.');
        } else {
            $params['slug'] = unique_series_slug($pdo, $name);
            $pdo->prepare('INSERT INTO event_series (slug, name, cadence, tagline, description, sort_order)
                           VALUES (:slug, :name, :cadence, :tagline, :description, 9999)')
                ->execute($params);
            flash_set('Sorozat létrehozva.');
        }
        redirect('series.php');
    }

    if ($action === 'toggle' && $id > 0) {
        $pdo->prepare('UPDATE event_series SET is_active = 1 - is_active WHERE id = :id')->execute([':id' => $id]);
        redirect('series.php');
    }

    if ($action === 'delete' && $id > 0) {
        // The events table keeps its rows; their series link is cleared.
        $pdo->prepare('DELETE FROM event_series WHERE id = :id')->execute([':id' => $id]);
        flash_set('Sorozat törölve. A hozzá tartozó esték megmaradtak, csak a sorozatuk lett üres.');
        redirect('series.php');
    }

    if (($action === 'up' || $action === 'down') && $id > 0) {
        $ids  = array_map('intval', $pdo->query('SELECT id FROM event_series ORDER BY sort_order, id')
            ->fetchAll(PDO::FETCH_COLUMN));
        $pos  = array_search($id, $ids, true);
        $swap = $action === 'up' ? $pos - 1 : $pos + 1;
        if ($pos !== false && isset($ids[$swap])) {
            [$ids[$pos], $ids[$swap]] = [$ids[$swap], $ids[$pos]];
            $stmt = $pdo->prepare('UPDATE event_series SET sort_order = :o WHERE id = :id');
            foreach (array_values($ids) as $index => $sid) {
                $stmt->execute([':o' => ($index + 1) * 10, ':id' => $sid]);
            }
        }
        redirect('series.php');
    }
}

$series = $pdo->query(
    'SELECT s.*, (SELECT COUNT(*) FROM events e WHERE e.series_id = s.id) AS event_count
       FROM event_series s ORDER BY s.sort_order, s.id'
)->fetchAll();

$editId  = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
$adding  = isset($_GET['add']);
$editRow = null;
foreach ($series as $s) {
    if ((int) $s['id'] === $editId) {
        $editRow = $s;
    }
}

$pageTitle = 'Sorozatok';
$current   = 'series';
require __DIR__ . '/../includes/header.php';
?>

<?php if ($adding || $editRow !== null): $r = $editRow; ?>
<div class="panel">
  <h2 style="margin-top:0"><?= $r ? 'Sorozat szerkesztése' : 'Új sorozat' ?></h2>
  <form method="post" action="series.php">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= $r ? (int) $r['id'] : 0 ?>">
    <div class="grid2">
      <p><label for="s-name">Név *</label>
        <input type="text" id="s-name" name="name" maxlength="120" required
               value="<?= e($r['name'] ?? '') ?>" placeholder="pl. Salotto Musicale"></p>
      <p><label for="s-cadence">Gyakoriság</label>
        <input type="text" id="s-cadence" name="cadence" maxlength="60"
               value="<?= e($r['cadence'] ?? '') ?>" placeholder="havonta / évente"></p>
      <p class="full"><label for="s-tagline">Egy soros leírás</label>
        <input type="text" id="s-tagline" name="tagline" maxlength="255"
               value="<?= e($r['tagline'] ?? '') ?>" placeholder="Intim koncertsorozat a zongoránál"></p>
      <p class="full"><label for="s-desc">Leírás</label>
        <textarea id="s-desc" name="description" rows="3"><?= e($r['description'] ?? '') ?></textarea></p>
    </div>
    <p style="margin-bottom:0">
      <button class="btn red" type="submit"><?= $r ? 'Mentés' : 'Létrehozás' ?></button>
      <a class="btn ghost" href="series.php">Mégsem</a>
    </p>
  </form>
</div>
<?php else: ?>
<p><a class="btn red" href="series.php?add=1">+ Új sorozat</a></p>
<?php endif; ?>

<table>
  <thead>
    <tr><th style="width:5.5rem">Sorrend</th><th>Sorozat</th><th>Gyakoriság</th><th class="num">Esték</th><th>Látható</th><th class="actions">Műveletek</th></tr>
  </thead>
  <tbody>
  <?php if (!$series): ?>
    <tr><td colspan="6" class="muted">Még nincs sorozat.</td></tr>
  <?php endif; ?>
  <?php foreach ($series as $s): ?>
    <tr class="<?= $s['is_active'] ? '' : 'item-off' ?>">
      <td>
        <form method="post" class="inline"><?= csrf_field() ?>
          <input type="hidden" name="action" value="up"><input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
          <button class="btn small ghost" title="Feljebb">▲</button></form>
        <form method="post" class="inline"><?= csrf_field() ?>
          <input type="hidden" name="action" value="down"><input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
          <button class="btn small ghost" title="Lejjebb">▼</button></form>
      </td>
      <td><strong><?= e($s['name']) ?></strong>
        <?php if (!empty($s['tagline'])): ?><div class="hint"><?= e($s['tagline']) ?></div><?php endif; ?></td>
      <td><?= e($s['cadence'] ?? '—') ?></td>
      <td class="num"><?= (int) $s['event_count'] ?></td>
      <td>
        <form method="post" class="inline"><?= csrf_field() ?>
          <input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
          <button class="btn small <?= $s['is_active'] ? '' : 'ghost' ?>"><?= $s['is_active'] ? 'Látható' : 'Rejtve' ?></button></form>
      </td>
      <td class="actions">
        <a class="btn small ghost" href="series.php?edit=<?= (int) $s['id'] ?>">Szerkeszt</a>
        <form method="post" class="inline" onsubmit="return confirm('Biztosan törlöd? A hozzá tartozó esték megmaradnak.')"><?= csrf_field() ?>
          <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
          <button class="btn small danger">Törlés</button></form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>

<div class="panel" style="margin-top:1.6rem">
  <h2 style="margin-top:0">Sorozat vagy esemény?</h2>
  <p><strong>Sorozat</strong> = a formátum („Salotto Musicale — intim koncertsorozat, havonta").
     Ez állandó, és akkor is látszik a weboldalon, ha épp nincs kitűzött dátum.</p>
  <p><strong>Esemény</strong> (admin → Események) = egy konkrét, dátumos est, amely egy
     sorozathoz tartozhat. A lejárt esték maguktól lekerülnek a weboldalról.</p>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
