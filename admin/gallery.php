<?php
/**
 * Gallery manager. Photos uploaded here appear on the public site as
 * full-screen "fly-through" pages on the journey, between the salon's
 * introduction and the chapter about the space.
 *
 * Every photo added lengthens the flight by one beat — the journey is
 * computed from the number of chapters, so nothing gets crowded.
 */

require_once __DIR__ . '/../includes/auth.php';
require_login();

$pdo = db();

/**
 * Renumber gallery rows to 10, 20, 30… following a given id order.
 *
 * @param int[] $orderedIds
 */
function renumber_gallery(PDO $pdo, array $orderedIds): void
{
    $stmt = $pdo->prepare('UPDATE gallery SET sort_order = :o WHERE id = :id');
    foreach (array_values($orderedIds) as $index => $id) {
        $stmt->execute([':o' => ($index + 1) * 10, ':id' => $id]);
    }
}

// ---------------------------------------------------------------------------
// POST actions
// ---------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = (string) ($_POST['action'] ?? '');
    $id     = (int) ($_POST['id'] ?? 0);

    if ($action === 'upload') {
        $caption = trim((string) ($_POST['caption'] ?? ''));
        $up = handle_image_upload($_FILES['image'] ?? [], UPLOAD_GALLERY_DIR);
        if (!$up['ok']) {
            flash_set($up['error'], 'err');
        } else {
            $pdo->prepare('INSERT INTO gallery (caption, image, sort_order) VALUES (:c, :i, 9999)')
                ->execute([':c' => $caption !== '' ? $caption : null, ':i' => $up['filename']]);
            flash_set('Fotó feltöltve — már látható a weboldalon.');
        }
        redirect('gallery.php');
    }

    if ($action === 'caption' && $id > 0) {
        $caption = trim((string) ($_POST['caption'] ?? ''));
        $pdo->prepare('UPDATE gallery SET caption = :c WHERE id = :id')
            ->execute([':c' => $caption !== '' ? $caption : null, ':id' => $id]);
        flash_set('Képaláírás mentve.');
        redirect('gallery.php');
    }

    if ($action === 'toggle' && $id > 0) {
        $pdo->prepare('UPDATE gallery SET is_active = 1 - is_active WHERE id = :id')
            ->execute([':id' => $id]);
        flash_set('Láthatóság átállítva.');
        redirect('gallery.php');
    }

    if ($action === 'delete' && $id > 0) {
        $stmt = $pdo->prepare('SELECT image FROM gallery WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        if ($row) {
            delete_upload(UPLOAD_GALLERY_DIR, $row['image']);
            $pdo->prepare('DELETE FROM gallery WHERE id = :id')->execute([':id' => $id]);
            flash_set('Fotó törölve.');
        }
        redirect('gallery.php');
    }

    if (($action === 'up' || $action === 'down') && $id > 0) {
        $ids = array_map('intval', $pdo->query('SELECT id FROM gallery ORDER BY sort_order, id')
            ->fetchAll(PDO::FETCH_COLUMN));
        $pos  = array_search($id, $ids, true);
        $swap = $action === 'up' ? $pos - 1 : $pos + 1;
        if ($pos !== false && isset($ids[$swap])) {
            [$ids[$pos], $ids[$swap]] = [$ids[$swap], $ids[$pos]];
            renumber_gallery($pdo, $ids);
        }
        redirect('gallery.php');
    }
}

$photos = $pdo->query('SELECT * FROM gallery ORDER BY sort_order, id')->fetchAll();

$pageTitle = 'Galéria';
$current   = 'gallery';
require __DIR__ . '/../includes/header.php';
?>

<div class="panel">
  <h2 style="margin-top:0">Új fotó feltöltése</h2>
  <form method="post" action="gallery.php" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="upload">
    <div class="grid2">
      <p><label for="g-img">Kép * (JPG / PNG / WEBP, max. 5 MB — álló vagy fekvő kép — teljes képernyőn jelenik meg)</label>
        <input type="file" id="g-img" name="image" accept=".jpg,.jpeg,.png,.webp" required></p>
      <p><label for="g-cap">Képaláírás (a fotó alján jelenik meg, elhagyható)</label>
        <input type="text" id="g-cap" name="caption" maxlength="190" placeholder="pl. A szalonszint — gyertyafény és kristály"></p>
    </div>
    <p style="margin-bottom:0"><button class="btn red" type="submit">Feltöltés</button></p>
  </form>
</div>

<table>
  <thead>
    <tr><th style="width:5.5rem">Sorrend</th><th>Kép</th><th>Képaláírás</th><th>Látható</th><th class="actions">Műveletek</th></tr>
  </thead>
  <tbody>
  <?php if (!$photos): ?>
    <tr><td colspan="5" class="muted">Még nincs fotó a galériában.</td></tr>
  <?php endif; ?>
  <?php foreach ($photos as $photo): ?>
    <tr class="<?= $photo['is_active'] ? '' : 'item-off' ?>">
      <td>
        <form method="post" class="inline"><?= csrf_field() ?>
          <input type="hidden" name="action" value="up"><input type="hidden" name="id" value="<?= (int) $photo['id'] ?>">
          <button class="btn small ghost" title="Feljebb">▲</button></form>
        <form method="post" class="inline"><?= csrf_field() ?>
          <input type="hidden" name="action" value="down"><input type="hidden" name="id" value="<?= (int) $photo['id'] ?>">
          <button class="btn small ghost" title="Lejjebb">▼</button></form>
      </td>
      <td><img class="thumb" style="width:110px;height:70px" src="<?= e(upload_url('gallery', $photo['image'], true)) ?>" alt=""></td>
      <td>
        <form method="post" class="inline" style="display:flex;gap:0.4rem"><?= csrf_field() ?>
          <input type="hidden" name="action" value="caption"><input type="hidden" name="id" value="<?= (int) $photo['id'] ?>">
          <input type="text" name="caption" maxlength="190" value="<?= e($photo['caption'] ?? '') ?>" style="max-width:18rem">
          <button class="btn small ghost">Ment</button></form>
      </td>
      <td>
        <form method="post" class="inline"><?= csrf_field() ?>
          <input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $photo['id'] ?>">
          <button class="btn small <?= $photo['is_active'] ? '' : 'ghost' ?>"><?= $photo['is_active'] ? 'Látható' : 'Rejtve' ?></button></form>
      </td>
      <td class="actions">
        <form method="post" class="inline" onsubmit="return confirm('Biztosan törlöd a fotót?')"><?= csrf_field() ?>
          <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $photo['id'] ?>">
          <button class="btn small danger">Törlés</button></form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>

<?php require __DIR__ . '/../includes/footer.php'; ?>
