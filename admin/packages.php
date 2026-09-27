<?php
/**
 * Csomagok — the six ways to rent the salon ("A kínálat").
 *
 * Each package is one row; the bullet list on the right of the public
 * offer sheet is the `features` textarea, one line per point.
 */

require_once __DIR__ . '/../includes/auth.php';
require_login();

$pdo = db();

/** The editable columns, in form order. */
const PKG_FIELDS = [
    'name_it'       => ['Olasz cím (a kis nagybetűs sor)', 'text',     'La Tavola dei Medici'],
    'name_hu'       => ['Magyar cím (a nagy cím)',         'text',     'A Medici Asztal'],
    'tagline'       => ['Egy soros leírás',                'text',     'Meghitt, ültetett vacsora a tükörlapos asztalnál'],
    'description'   => ['Leírás',                          'textarea', ''],
    'highlight'     => ['Kiemelt mondat (arany, dőlt)',    'textarea', 'Az este a meghatározó pillanatoké.'],
    'price_from'    => ['Ár -tól (csak szám, Ft)',         'number',   '42000'],
    'price_unit'    => ['Ár egysége',                      'text',     '/ fő vagy helyszín'],
    'price_note'    => ['Ár-megjegyzés (a szám alatt)',    'text',     'Teljes bérlés 1 200 000 Ft-tól'],
    'capacity_note' => ['Létszám / forma',                 'text',     '12–24 fő · esti bérlés'],
    'features'      => ['Mit tartalmaz — soronként egy',   'textarea', ''],
];

/**
 * Renumber packages to 10, 20, 30… following a given id order.
 *
 * @param int[] $orderedIds
 */
function renumber_packages(PDO $pdo, array $orderedIds): void
{
    $stmt = $pdo->prepare('UPDATE packages SET sort_order = :o WHERE id = :id');
    foreach (array_values($orderedIds) as $index => $id) {
        $stmt->execute([':o' => ($index + 1) * 10, ':id' => $id]);
    }
}

/**
 * Turn a title into a URL-safe slug. The slug is what the public
 * "Ajánlatot kérek erre" button posts, so it must stay stable.
 */
function slugify(string $text): string
{
    $map = ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ö'=>'o','ő'=>'o','ú'=>'u','ü'=>'u','ű'=>'u',
            'Á'=>'a','É'=>'e','Í'=>'i','Ó'=>'o','Ö'=>'o','Ő'=>'o','Ú'=>'u','Ü'=>'u','Ű'=>'u'];
    $text = strtr(mb_strtolower(trim($text)), $map);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
    return trim($text, '-');
}

/**
 * A slug nobody else is using yet.
 */
function unique_slug(PDO $pdo, string $base, int $ignoreId = 0): string
{
    $base = $base !== '' ? $base : 'csomag';
    $slug = mb_substr($base, 0, 50);
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM packages WHERE slug = :s AND id <> :id');
    for ($n = 2; $n < 100; $n++) {
        $stmt->execute([':s' => $slug, ':id' => $ignoreId]);
        if ((int) $stmt->fetchColumn() === 0) {
            return $slug;
        }
        $slug = mb_substr($base, 0, 46) . '-' . $n;
    }
    return $base . '-' . bin2hex(random_bytes(3));
}

/**
 * Read the posted package fields into query parameters.
 */
function posted_package(array $post): array
{
    $out = [];
    foreach (PKG_FIELDS as $key => [$label, $type]) {
        $value = trim((string) ($post[$key] ?? ''));
        if ($type === 'number') {
            $out[$key] = $value !== '' ? (int) preg_replace('/\D/', '', $value) : null;
        } else {
            $out[$key] = $value !== '' ? $value : null;
        }
    }
    // A package must at least be called something.
    if ($out['name_hu'] === null) {
        $out['name_hu'] = 'Névtelen csomag';
    }
    if ($out['name_it'] === null) {
        $out['name_it'] = $out['name_hu'];
    }
    return $out;
}

// ---------------------------------------------------------------------------
// POST actions
// ---------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = (string) ($_POST['action'] ?? '');
    $id     = (int) ($_POST['id'] ?? 0);

    if ($action === 'save') {
        $data = posted_package($_POST);
        if ($id > 0) {
            $sets = [];
            foreach (array_keys(PKG_FIELDS) as $key) {
                $sets[] = $key . ' = :' . $key;
            }
            $stmt = $pdo->prepare('UPDATE packages SET ' . implode(', ', $sets) . ' WHERE id = :id');
            $stmt->execute($data + ['id' => $id]);   // keys match the :placeholders
            flash_set('Csomag mentve.');
        } else {
            $data['slug'] = unique_slug($pdo, slugify($data['name_it'] ?: $data['name_hu']));
            $cols = array_keys($data);
            $stmt = $pdo->prepare(
                'INSERT INTO packages (' . implode(', ', $cols) . ', sort_order) VALUES (:'
                . implode(', :', $cols) . ', 9999)'
            );
            $stmt->execute($data);
            flash_set('Csomag létrehozva.');
        }
        redirect('packages.php');
    }

    if ($action === 'toggle' && $id > 0) {
        $pdo->prepare('UPDATE packages SET is_active = 1 - is_active WHERE id = :id')->execute([':id' => $id]);
        flash_set('Láthatóság átállítva.');
        redirect('packages.php');
    }

    if ($action === 'delete' && $id > 0) {
        $pdo->prepare('DELETE FROM packages WHERE id = :id')->execute([':id' => $id]);
        flash_set('Csomag törölve.');
        redirect('packages.php');
    }

    if (($action === 'up' || $action === 'down') && $id > 0) {
        $ids  = array_map('intval', $pdo->query('SELECT id FROM packages ORDER BY sort_order, id')
            ->fetchAll(PDO::FETCH_COLUMN));
        $pos  = array_search($id, $ids, true);
        $swap = $action === 'up' ? $pos - 1 : $pos + 1;
        if ($pos !== false && isset($ids[$swap])) {
            [$ids[$pos], $ids[$swap]] = [$ids[$swap], $ids[$pos]];
            renumber_packages($pdo, $ids);
        }
        redirect('packages.php');
    }
}

$packages = $pdo->query('SELECT * FROM packages ORDER BY sort_order, id')->fetchAll();

// Which row is open in the editor: ?edit=<id>, or ?add=1 for a new one.
$editId  = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
$adding  = isset($_GET['add']);
$editRow = null;
foreach ($packages as $p) {
    if ((int) $p['id'] === $editId) {
        $editRow = $p;
    }
}

$pageTitle = 'Csomagok';
$current   = 'packages';
require __DIR__ . '/../includes/header.php';

/**
 * The add/edit form. $row is NULL when creating.
 */
function package_form(?array $row): void
{
    $id = $row !== null ? (int) $row['id'] : 0;
    ?>
    <div class="panel">
      <h2 style="margin-top:0"><?= $id > 0 ? 'Csomag szerkesztése' : 'Új csomag' ?></h2>
      <form method="post" action="packages.php">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?= $id ?>">
        <div class="grid2">
          <?php foreach (PKG_FIELDS as $key => [$label, $type, $placeholder]):
              $value = $row[$key] ?? '';
              $full  = in_array($type, ['textarea'], true); ?>
          <p class="<?= $full ? 'full' : '' ?>">
            <label for="p-<?= e($key) ?>"><?= e($label) ?></label>
            <?php if ($type === 'textarea'): ?>
            <textarea id="p-<?= e($key) ?>" name="<?= e($key) ?>" rows="<?= $key === 'features' ? 6 : 3 ?>" placeholder="<?= e($placeholder) ?>"><?= e((string) $value) ?></textarea>
            <?php else: ?>
            <input type="<?= $type === 'number' ? 'number' : 'text' ?>" id="p-<?= e($key) ?>" name="<?= e($key) ?>"
                   value="<?= e((string) $value) ?>" placeholder="<?= e($placeholder) ?>"
                   <?= $type === 'number' ? 'min="0" step="1000"' : 'maxlength="255"' ?>>
            <?php endif; ?>
            <?php if ($key === 'features'): ?>
            <span class="hint">Soronként egy pont — ezek jelennek meg a jobb oldali listában.</span>
            <?php elseif ($key === 'price_unit'): ?>
            <span class="hint">Az ár után írjuk ki, pl. <code>/ fő</code> vagy <code>helyszín</code>. Hagyd üresen, ha nincs.</span>
            <?php elseif ($key === 'price_from'): ?>
            <span class="hint">Csak a szám. Üresen hagyva csak az ár-megjegyzés jelenik meg.</span>
            <?php endif; ?>
          </p>
          <?php endforeach; ?>
        </div>
        <p style="margin-bottom:0">
          <button class="btn red" type="submit"><?= $id > 0 ? 'Mentés' : 'Létrehozás' ?></button>
          <a class="btn ghost" href="packages.php">Mégsem</a>
        </p>
      </form>
    </div>
    <?php
}

if ($adding) {
    package_form(null);
} elseif ($editRow !== null) {
    package_form($editRow);
}
?>

<?php if (!$adding && $editRow === null): ?>
<p><a class="btn red" href="packages.php?add=1">+ Új csomag</a></p>
<?php endif; ?>

<table>
  <thead>
    <tr>
      <th style="width:5.5rem">Sorrend</th>
      <th>Csomag</th>
      <th class="num">Ár -tól</th>
      <th>Létszám</th>
      <th>Látható</th>
      <th class="actions">Műveletek</th>
    </tr>
  </thead>
  <tbody>
  <?php if (!$packages): ?>
    <tr><td colspan="6" class="muted">Még nincs csomag. Hozz létre egyet a fenti gombbal.</td></tr>
  <?php endif; ?>
  <?php foreach ($packages as $i => $p): ?>
    <tr class="<?= $p['is_active'] ? '' : 'item-off' ?>">
      <td>
        <span class="pill"><?= e(roman($i + 1)) ?></span>
        <form method="post" class="inline"><?= csrf_field() ?>
          <input type="hidden" name="action" value="up"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
          <button class="btn small ghost" title="Feljebb">▲</button></form>
        <form method="post" class="inline"><?= csrf_field() ?>
          <input type="hidden" name="action" value="down"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
          <button class="btn small ghost" title="Lejjebb">▼</button></form>
      </td>
      <td>
        <strong><?= e($p['name_hu']) ?></strong><br>
        <span class="muted"><?= e($p['name_it']) ?></span>
        <?php if (!empty($p['tagline'])): ?><div class="hint"><?= e($p['tagline']) ?></div><?php endif; ?>
      </td>
      <td class="num">
        <?= $p['price_from'] !== null ? e(format_huf((int) $p['price_from'])) : '<span class="muted">—</span>' ?>
        <?php if (!empty($p['price_unit'])): ?><br><span class="muted"><?= e($p['price_unit']) ?></span><?php endif; ?>
      </td>
      <td><?= e($p['capacity_note'] ?? '—') ?></td>
      <td>
        <form method="post" class="inline"><?= csrf_field() ?>
          <input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
          <button class="btn small <?= $p['is_active'] ? '' : 'ghost' ?>"><?= $p['is_active'] ? 'Látható' : 'Rejtve' ?></button></form>
      </td>
      <td class="actions">
        <a class="btn small ghost" href="packages.php?edit=<?= (int) $p['id'] ?>">Szerkeszt</a>
        <form method="post" class="inline" onsubmit="return confirm('Biztosan törlöd ezt a csomagot?')"><?= csrf_field() ?>
          <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
          <button class="btn small danger">Törlés</button></form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>

<div class="panel" style="margin-top:1.6rem">
  <h2 style="margin-top:0">Hogyan jelenik meg?</h2>
  <p>A főoldal <strong>„A kínálat"</strong> fejezetében minden aktív csomag egy kártyaként
     látszik (cím, egy soros leírás, ár). A <em>„A teljes kínálatot megnézem"</em> gombra
     megnyílik a teljes ajánlati lap, ahol minden csomag leírása, kiemelt mondata,
     ára és a <em>„Mit tartalmaz"</em> lista is szerepel.</p>
  <p>Ha egy csomagot <em>rejtettre</em> állítasz, azonnal eltűnik a weboldalról —
     és az ajánlatkérő űrlap választólistájából is.</p>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
