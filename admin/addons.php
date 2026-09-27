<?php
/**
 * Kiegészítők — the extras that can be added to any package
 * (live piano, a bespoke screen artwork, sommelier pairing…).
 */

require_once __DIR__ . '/../includes/auth.php';
require_login();

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = (string) ($_POST['action'] ?? '');
    $id     = (int) ($_POST['id'] ?? 0);

    if ($action === 'save') {
        $name  = trim((string) ($_POST['name'] ?? ''));
        $desc  = trim((string) ($_POST['description'] ?? ''));
        $price = trim((string) ($_POST['price_from'] ?? ''));
        $unit  = trim((string) ($_POST['price_unit'] ?? ''));

        if ($name === '') {
            flash_set('A kiegészítőnek kell név.', 'err');
            redirect('addons.php');
        }
        $params = [
            'name'        => mb_substr($name, 0, 160),
            'description' => $desc !== '' ? $desc : null,
            'price_from'  => $price !== '' ? (int) preg_replace('/\D/', '', $price) : null,
            'price_unit'  => $unit !== '' ? mb_substr($unit, 0, 60) : null,
        ];
        if ($id > 0) {
            $pdo->prepare('UPDATE addons SET name = :name, description = :description,
                           price_from = :price_from, price_unit = :price_unit WHERE id = :id')
                ->execute($params + ['id' => $id]);
            flash_set('Kiegészítő mentve.');
        } else {
            $pdo->prepare('INSERT INTO addons (name, description, price_from, price_unit, sort_order)
                           VALUES (:name, :description, :price_from, :price_unit, 9999)')
                ->execute($params);
            flash_set('Kiegészítő létrehozva.');
        }
        redirect('addons.php');
    }

    if ($action === 'toggle' && $id > 0) {
        $pdo->prepare('UPDATE addons SET is_active = 1 - is_active WHERE id = :id')->execute([':id' => $id]);
        redirect('addons.php');
    }

    if ($action === 'delete' && $id > 0) {
        $pdo->prepare('DELETE FROM addons WHERE id = :id')->execute([':id' => $id]);
        flash_set('Kiegészítő törölve.');
        redirect('addons.php');
    }

    if (($action === 'up' || $action === 'down') && $id > 0) {
        $ids  = array_map('intval', $pdo->query('SELECT id FROM addons ORDER BY sort_order, id')
            ->fetchAll(PDO::FETCH_COLUMN));
        $pos  = array_search($id, $ids, true);
        $swap = $action === 'up' ? $pos - 1 : $pos + 1;
        if ($pos !== false && isset($ids[$swap])) {
            [$ids[$pos], $ids[$swap]] = [$ids[$swap], $ids[$pos]];
            $stmt = $pdo->prepare('UPDATE addons SET sort_order = :o WHERE id = :id');
            foreach (array_values($ids) as $index => $aid) {
                $stmt->execute([':o' => ($index + 1) * 10, ':id' => $aid]);
            }
        }
        redirect('addons.php');
    }
}

$addons  = $pdo->query('SELECT * FROM addons ORDER BY sort_order, id')->fetchAll();
$editId  = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
$adding  = isset($_GET['add']);
$editRow = null;
foreach ($addons as $a) {
    if ((int) $a['id'] === $editId) {
        $editRow = $a;
    }
}

$pageTitle = 'Kiegészítők';
$current   = 'addons';
require __DIR__ . '/../includes/header.php';
?>

<?php if ($adding || $editRow !== null): $r = $editRow; ?>
<div class="panel">
  <h2 style="margin-top:0"><?= $r ? 'Kiegészítő szerkesztése' : 'Új kiegészítő' ?></h2>
  <form method="post" action="addons.php">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= $r ? (int) $r['id'] : 0 ?>">
    <div class="grid2">
      <p><label for="a-name">Megnevezés *</label>
        <input type="text" id="a-name" name="name" maxlength="160" required
               value="<?= e($r['name'] ?? '') ?>" placeholder="pl. Élő zongora és vonósok"></p>
      <p><label for="a-price">Ár -tól (csak szám, Ft)</label>
        <input type="number" id="a-price" name="price_from" min="0" step="1000"
               value="<?= e((string) ($r['price_from'] ?? '')) ?>" placeholder="90000"></p>
      <p><label for="a-unit">Ár egysége</label>
        <input type="text" id="a-unit" name="price_unit" maxlength="60"
               value="<?= e($r['price_unit'] ?? '') ?>" placeholder="/ fő — üresen hagyva fix díj">
        <span class="hint">Üresen: „90 000 Ft-tól". <code>/ fő</code> esetén: „18 000 Ft/fő-től".</span></p>
      <p class="full"><label for="a-desc">Leírás</label>
        <textarea id="a-desc" name="description" rows="3"><?= e($r['description'] ?? '') ?></textarea></p>
    </div>
    <p style="margin-bottom:0">
      <button class="btn red" type="submit"><?= $r ? 'Mentés' : 'Létrehozás' ?></button>
      <a class="btn ghost" href="addons.php">Mégsem</a>
    </p>
  </form>
</div>
<?php else: ?>
<p><a class="btn red" href="addons.php?add=1">+ Új kiegészítő</a></p>
<?php endif; ?>

<table>
  <thead>
    <tr><th style="width:5.5rem">Sorrend</th><th>Kiegészítő</th><th class="num">Ár</th><th>Látható</th><th class="actions">Műveletek</th></tr>
  </thead>
  <tbody>
  <?php if (!$addons): ?>
    <tr><td colspan="5" class="muted">Még nincs kiegészítő.</td></tr>
  <?php endif; ?>
  <?php foreach ($addons as $a):
      $unit  = trim((string) ($a['price_unit'] ?? ''));
      $price = $a['price_from'] !== null
          ? format_huf((int) $a['price_from']) . ($unit !== '' ? '/' . ltrim($unit, '/ ') : '') . '-tól'
          : '—'; ?>
    <tr class="<?= $a['is_active'] ? '' : 'item-off' ?>">
      <td>
        <form method="post" class="inline"><?= csrf_field() ?>
          <input type="hidden" name="action" value="up"><input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
          <button class="btn small ghost" title="Feljebb">▲</button></form>
        <form method="post" class="inline"><?= csrf_field() ?>
          <input type="hidden" name="action" value="down"><input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
          <button class="btn small ghost" title="Lejjebb">▼</button></form>
      </td>
      <td><strong><?= e($a['name']) ?></strong>
        <?php if (!empty($a['description'])): ?><div class="hint"><?= e($a['description']) ?></div><?php endif; ?></td>
      <td class="num"><?= e($price) ?></td>
      <td>
        <form method="post" class="inline"><?= csrf_field() ?>
          <input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
          <button class="btn small <?= $a['is_active'] ? '' : 'ghost' ?>"><?= $a['is_active'] ? 'Látható' : 'Rejtve' ?></button></form>
      </td>
      <td class="actions">
        <a class="btn small ghost" href="addons.php?edit=<?= (int) $a['id'] ?>">Szerkeszt</a>
        <form method="post" class="inline" onsubmit="return confirm('Biztosan törlöd?')"><?= csrf_field() ?>
          <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
          <button class="btn small danger">Törlés</button></form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>

<p class="hint" style="margin-top:1rem">A kiegészítők a teljes ajánlati lap alján,
   a csomagok után jelennek meg — a „Kiegészítők" blokkban.</p>

<?php require __DIR__ . '/../includes/footer.php'; ?>
