<?php
/**
 * Ajánlatkérések — what the website's enquiry form collected.
 *
 * Every submission is stored here, whether or not the notification
 * e-mail went out, so nothing can be lost to a mail server.
 */

require_once __DIR__ . '/../includes/auth.php';
require_login();

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = (string) ($_POST['action'] ?? '');
    $id     = (int) ($_POST['id'] ?? 0);

    if ($action === 'status' && $id > 0) {
        $status = (string) ($_POST['status'] ?? 'read');
        if (!in_array($status, ['new', 'read', 'archived'], true)) {
            $status = 'read';
        }
        $pdo->prepare('UPDATE enquiries SET status = :s WHERE id = :id')
            ->execute([':s' => $status, ':id' => $id]);
        redirect('enquiries.php' . (isset($_POST['show']) ? '?show=' . urlencode((string) $_POST['show']) : ''));
    }

    if ($action === 'delete' && $id > 0) {
        $pdo->prepare('DELETE FROM enquiries WHERE id = :id')->execute([':id' => $id]);
        flash_set('Ajánlatkérés törölve.');
        redirect('enquiries.php');
    }
}

// Which pile are we looking at? Default: everything except the archive.
$show  = (string) ($_GET['show'] ?? 'open');
$where = match ($show) {
    'new'      => "status = 'new'",
    'archived' => "status = 'archived'",
    'all'      => '1 = 1',
    default    => "status IN ('new','read')",
};

$rows = $pdo->query(
    "SELECT e.*, p.name_hu AS package_name
       FROM enquiries e LEFT JOIN packages p ON p.id = e.package_id
      WHERE $where
      ORDER BY e.created_at DESC, e.id DESC
      LIMIT 300"
)->fetchAll();

$counts = $pdo->query(
    "SELECT
       SUM(status = 'new')      AS c_new,
       SUM(status = 'read')     AS c_read,
       SUM(status = 'archived') AS c_arch,
       COUNT(*)                 AS c_all
     FROM enquiries"
)->fetch() ?: ['c_new' => 0, 'c_read' => 0, 'c_arch' => 0, 'c_all' => 0];

$recipient = trim((string) setting('enquiry_recipient', ''));

$pageTitle = 'Ajánlatkérések';
$current   = 'enquiries';
require __DIR__ . '/../includes/header.php';
?>

<?php if ($recipient === ''): ?>
<div class="flash err">Nincs beállítva értesítési e-mail cím, így új ajánlatkérésről nem
  kapsz levelet — csak itt látszik. Beállítás: <a href="settings.php">Beállítások → Ajánlatkérés</a>.</div>
<?php endif; ?>

<div class="filters">
  <a class="btn small <?= $show === 'open'     ? '' : 'ghost' ?>" href="enquiries.php">Nyitott (<?= (int) $counts['c_new'] + (int) $counts['c_read'] ?>)</a>
  <a class="btn small <?= $show === 'new'      ? '' : 'ghost' ?>" href="enquiries.php?show=new">Új (<?= (int) $counts['c_new'] ?>)</a>
  <a class="btn small <?= $show === 'archived' ? '' : 'ghost' ?>" href="enquiries.php?show=archived">Archivált (<?= (int) $counts['c_arch'] ?>)</a>
  <a class="btn small <?= $show === 'all'      ? '' : 'ghost' ?>" href="enquiries.php?show=all">Összes (<?= (int) $counts['c_all'] ?>)</a>
</div>

<?php if (!$rows): ?>
<div class="panel"><p class="muted" style="margin:0">Ebben a nézetben nincs ajánlatkérés.</p></div>
<?php endif; ?>

<?php foreach ($rows as $r): ?>
<div class="panel">
  <div style="display:flex;flex-wrap:wrap;gap:0.6rem;align-items:baseline;justify-content:space-between">
    <h2 style="margin:0">
      <?= e($r['name']) ?>
      <?php if ($r['status'] === 'new'): ?><span class="pill new">új</span><?php endif; ?>
      <?php if ($r['status'] === 'archived'): ?><span class="pill">archivált</span><?php endif; ?>
    </h2>
    <span class="muted"><?= e(date('Y. m. d. H:i', strtotime((string) $r['created_at']))) ?></span>
  </div>

  <div class="grid2" style="margin-top:0.8rem">
    <p style="margin:0"><strong>E-mail:</strong>
      <a href="mailto:<?= e($r['email']) ?>?subject=<?= e(rawurlencode('Salone Medici — ajánlat')) ?>"><?= e($r['email']) ?></a></p>
    <p style="margin:0"><strong>Telefon:</strong>
      <?php if (!empty($r['phone'])): ?><a href="tel:<?= e(preg_replace('/\s+/', '', (string) $r['phone'])) ?>"><?= e($r['phone']) ?></a><?php else: ?><span class="muted">—</span><?php endif; ?></p>
    <p style="margin:0"><strong>Cég:</strong> <?= !empty($r['company']) ? e($r['company']) : '<span class="muted">—</span>' ?></p>
    <p style="margin:0"><strong>Csomag:</strong> <?= !empty($r['package_name']) ? e($r['package_name']) : '<span class="muted">nem választott</span>' ?></p>
    <p style="margin:0"><strong>Időpont:</strong> <?= $r['event_date'] !== null ? e(format_date_hu((string) $r['event_date'])) : '<span class="muted">—</span>' ?></p>
    <p style="margin:0"><strong>Létszám:</strong> <?= $r['guests'] !== null ? (int) $r['guests'] . ' fő' : '<span class="muted">—</span>' ?></p>
  </div>

  <?php if (!empty($r['message'])): ?>
  <p style="margin:0.9rem 0 0"><strong>Üzenet</strong></p>
  <div class="msg-box"><?= e($r['message']) ?></div>
  <?php endif; ?>

  <p style="margin:1rem 0 0">
    <?php foreach ([['new', 'Újként jelöl'], ['read', 'Olvasottnak jelöl'], ['archived', 'Archivál']] as [$st, $label]):
        if ($r['status'] === $st) { continue; } ?>
    <form method="post" class="inline"><?= csrf_field() ?>
      <input type="hidden" name="action" value="status">
      <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
      <input type="hidden" name="status" value="<?= e($st) ?>">
      <input type="hidden" name="show" value="<?= e($show) ?>">
      <button class="btn small ghost"><?= e($label) ?></button></form>
    <?php endforeach; ?>
    <form method="post" class="inline" onsubmit="return confirm('Biztosan véglegesen törlöd ezt az ajánlatkérést?')"><?= csrf_field() ?>
      <input type="hidden" name="action" value="delete">
      <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
      <button class="btn small danger">Törlés</button></form>
  </p>
</div>
<?php endforeach; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
